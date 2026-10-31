<?php
declare(strict_types=1);
require_once __DIR__.'/PushDelivery.php';

/**
 * Notifications to a browser, on the phone or on the desktop, with no store
 * and no third party: the standard Web Push, exactly as RFC 8291 and RFC 8292
 * describe it.
 *
 * The server holds one key pair of its own, generated the first time and kept
 * outside the document root. The public half is what a browser needs to
 * subscribe and is meant to be public; the private half signs every send and
 * never leaves the server. Each notice is encrypted for the one subscription
 * it is addressed to, so the push service forwards something it cannot read.
 */
final class WebPush {
    private const TTL=86400;
    private const RECORD=4096;

    /** @param ?Closure(string,array,string):array $transport only tests inject this */
    public function __construct(private readonly ?Closure $transport=null,private readonly ?string $keyFile=null) {}

    public function configured():bool {
        try {$this->keys();return true;}
        catch(Throwable $e){return false;}
    }

    /** What a browser needs to subscribe. Public by design. */
    public function publicKey():string {
        return $this->keys()['public'];
    }

    /**
     * Sends one notice to one subscription.
     *
     * @param array{token:string,p256dh:?string,auth:?string} $device
     */
    public function send(array $device,array $notice):void {
        $endpoint=(string)($device['token']??'');
        if(!preg_match('~^https://[A-Za-z0-9.-]+/~',$endpoint))throw new PushProblem('unregistered','La suscripción de ese navegador no es válida.');
        $payload=json_encode([
            'title'=>(string)$notice['title'],
            'body'=>(string)$notice['body'],
            // Lo pendiente de quien lo recibe, para el contador del icono.
            'unread'=>max(0,(int)($notice['unread']??0)),
            'data'=>['path'=>$notice['conversation_id']==='prueba'?'/bandeja':'/conversations/'.$notice['conversation_id'],'conversationId'=>(string)$notice['conversation_id'],'connectionId'=>(string)$notice['connection_id']],
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $body=$this->encrypt($payload,(string)($device['p256dh']??''),(string)($device['auth']??''));
        $origin=parse_url($endpoint,PHP_URL_SCHEME).'://'.parse_url($endpoint,PHP_URL_HOST);
        $headers=[
            'Authorization: vapid t='.$this->assertion($origin).', k='.$this->keys()['public'],
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: '.self::TTL,
            'Urgency: high',
        ];
        $response=$this->call($endpoint,$headers,$body);
        $status=(int)($response['status']??0);
        if($status>=200&&$status<300)return;
        // A subscription the service has dropped is gone for good.
        if(in_array($status,[400,401,403,404,410],true))throw new PushProblem('unregistered','Ese navegador ya no acepta avisos.');
        throw new PushProblem('unavailable','El servicio de avisos del navegador respondió '.$status.'.');
    }

    /**
     * RFC 8291: one ephemeral key pair per message, a shared secret with the
     * browser's own key, and the notice encrypted with what both derive.
     */
    private function encrypt(string $payload,string $p256dh,string $auth):string {
        $clientPublic=self::decode($p256dh);
        $authSecret=self::decode($auth);
        if(strlen($clientPublic)!==65||$clientPublic[0]!=="\x04"||strlen($authSecret)!==16)
            throw new PushProblem('unregistered','Las claves de esa suscripción no tienen el formato esperado.');
        $ephemeral=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
        if($ephemeral===false)throw new PushProblem('unavailable','No se pudo preparar el cifrado del aviso.');
        $serverPublic=self::point($ephemeral);
        $shared=openssl_pkey_derive(self::publicKeyFromPoint($clientPublic),$ephemeral,32);
        if($shared===false)throw new PushProblem('unavailable','No se pudo acordar la clave del aviso.');
        $salt=random_bytes(16);
        // The browser derives exactly the same three values from its own key.
        $ikm=self::hkdf($authSecret,$shared,"WebPush: info\x00".$clientPublic.$serverPublic,32);
        $cek=self::hkdf($salt,$ikm,"Content-Encoding: aes128gcm\x00",16);
        $nonce=self::hkdf($salt,$ikm,"Content-Encoding: nonce\x00",12);
        $tag='';
        $cipher=openssl_encrypt($payload."\x02",'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag,'',16);
        if($cipher===false)throw new PushProblem('unavailable','No se pudo cifrar el aviso.');
        return $salt.pack('N',self::RECORD).chr(strlen($serverPublic)).$serverPublic.$cipher.$tag;
    }

    /** RFC 8292: a short-lived assertion that this server sent the message. */
    private function assertion(string $origin):string {
        $keys=$this->keys();
        $claim=['aud'=>$origin,'exp'=>time()+43200,'sub'=>$keys['subject']];
        $unsigned=self::encode(json_encode(['typ'=>'JWT','alg'=>'ES256'],JSON_THROW_ON_ERROR)).'.'.self::encode(json_encode($claim,JSON_THROW_ON_ERROR));
        $der='';
        if(!openssl_sign($unsigned,$der,$keys['private'],OPENSSL_ALGO_SHA256))throw new PushProblem('unavailable','No se pudo firmar el aviso.');
        return $unsigned.'.'.self::encode(self::rawSignature($der));
    }

    /** The server's own key pair, generated once and kept outside the web root. */
    private function keys():array {
        static $cache=[];
        $file=$this->keyFile??(getenv('WEBPUSH_KEY_FILE')?:'');
        if($file==='')throw new PushProblem('unconfigured','Los avisos del navegador no están configurados en el servidor.');
        if(isset($cache[$file]))return $cache[$file];
        if(!is_file($file))throw new PushProblem('unconfigured','Los avisos del navegador no están configurados en el servidor.');
        $raw=@file_get_contents($file);
        $data=is_string($raw)?json_decode($raw,true):null;
        if(!is_array($data)||!is_string($data['privateKey']??null)||!is_string($data['publicKey']??null))
            throw new PushProblem('invalid_credentials','El archivo de claves de avisos no tiene el formato esperado.');
        $private=openssl_pkey_get_private($data['privateKey']);
        if($private===false)throw new PushProblem('invalid_credentials','La clave de avisos no se pudo leer.');
        return $cache[$file]=['private'=>$private,'public'=>$data['publicKey'],'subject'=>is_string($data['subject']??null)?$data['subject']:'mailto:soporte@agoradigital.app'];
    }

    /**
     * Creates the key pair if it is missing. Written with the same care as the
     * encryption key: private directory, 0600, and never replaced in place.
     */
    public static function ensureKeys(?string $file=null,?string $subject=null):bool {
        $file=$file??(getenv('WEBPUSH_KEY_FILE')?:'');
        if($file==='')return false;
        if(is_file($file))return true;
        $directory=dirname($file);
        if(!is_dir($directory)&&!@mkdir($directory,0700,true))return false;
        if(!is_writable($directory))return false;
        $key=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
        if($key===false)return false;
        openssl_pkey_export($key,$pem);
        $payload=json_encode([
            'privateKey'=>$pem,
            'publicKey'=>self::encode(self::point($key)),
            'subject'=>$subject??'mailto:soporte@agoradigital.app',
        ],JSON_THROW_ON_ERROR);
        $temporary=@tempnam($directory,'.agora-push-');
        if($temporary===false)return false;
        try {
            if(!chmod($temporary,0600)||file_put_contents($temporary,$payload,LOCK_EX)===false)return false;
            if(!@link($temporary,$file)&&!is_file($file))return false;
        } finally {@unlink($temporary);}
        return is_file($file);
    }

    private function call(string $url,array $headers,string $body):array {
        if($this->transport)return ($this->transport)($url,$headers,$body);
        $handle=curl_init($url);
        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>8]);
        $answer=curl_exec($handle);
        $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if($answer===false)throw new PushProblem('unavailable','No se pudo contactar con el servicio de avisos del navegador.');
        return ['status'=>$status,'body'=>$answer];
    }

    /** HKDF with SHA-256, which is all the specification uses. */
    public static function hkdf(string $salt,string $ikm,string $info,int $length):string {
        $prk=hash_hmac('sha256',$ikm,$salt,true);
        return substr(hash_hmac('sha256',$info."\x01",$prk,true),0,$length);
    }
    /** The uncompressed point of a key, which is how both sides exchange them. */
    public static function point($key):string {
        $details=openssl_pkey_get_details($key);
        return "\x04".str_pad($details['ec']['x'],32,"\x00",STR_PAD_LEFT).str_pad($details['ec']['y'],32,"\x00",STR_PAD_LEFT);
    }
    /** Wraps a raw point as a key the OpenSSL functions accept. */
    public static function publicKeyFromPoint(string $point) {
        $der=hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point;
        $pem="-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";
        $key=openssl_pkey_get_public($pem);
        if($key===false)throw new PushProblem('unregistered','La clave pública de esa suscripción no es válida.');
        return $key;
    }
    /** ES256 wants the two halves plain, not the structure OpenSSL returns. */
    public static function rawSignature(string $der):string {
        $offset=4;
        $rLength=ord($der[3]);
        $r=ltrim(substr($der,$offset,$rLength),"\x00");
        $offset+=$rLength+2;
        $sLength=ord($der[$offset-1]);
        $s=ltrim(substr($der,$offset,$sLength),"\x00");
        return str_pad($r,32,"\x00",STR_PAD_LEFT).str_pad($s,32,"\x00",STR_PAD_LEFT);
    }
    public static function encode(string $value):string {return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
    public static function decode(string $value):string {
        $value=strtr($value,'-_','+/');
        return (string)base64_decode($value.str_repeat('=',(4-strlen($value)%4)%4),true);
    }
}
