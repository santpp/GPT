<?php
declare(strict_types=1);
require_once __DIR__.'/MessageSender.php';

/**
 * Nota de voz por WhatsApp. El navegador manda el audio a Ágora (nunca a la
 * cuenta): aquí se comprueba que es un audio que WhatsApp acepta, se sube a la
 * conversación con la subida en dos pasos de la cuenta y se envía como adjunto,
 * con la misma protección contra duplicados que cualquier mensaje.
 *
 * WhatsApp sólo muestra como nota de voz el audio OGG con códec Opus; AAC (MP4)
 * y MP3 salen como archivo de audio. El audio WebM que graba Chrome no lo acepta
 * ningún canal: el navegador lo reempaqueta a OGG antes de enviarlo.
 */
final class VoiceNote {
    public const MAX_BYTES=4194304;
    private const TYPES=['audio/ogg'=>'ogg','audio/mp4'=>'m4a','audio/mpeg'=>'mp3'];

    /** @param null|Closure(string,string,string):array $put sube los bytes a la dirección firmada; para las pruebas */
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway(),private readonly ?Closure $put=null) {}

    public function send(array $connection,array $conversation,string $requestId,string $mime,string $bytes):array {
        if(!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$requestId))throw new SendProblem('invalid_message',422,'Vuelve a cargar la pantalla antes de enviar.');
        $mime=strtolower(trim(explode(';',$mime)[0]));
        self::check($mime,$bytes);
        $id=(int)$connection['id'];$conversationId=(string)($conversation['id']??'');
        // Ya enviada con esta misma clave: se devuelve lo que pasó, sin volver a subir nada.
        $attempt=query('SELECT state,message_id,delivery_status FROM message_send_attempts WHERE connection_id=? AND request_id=?',[$id,$requestId])->fetch();
        if($attempt&&$attempt['state']==='accepted')return ['state'=>'accepted','messageId'=>$attempt['message_id'],'deliveryStatus'=>$attempt['delivery_status'],'requestId'=>$requestId,'url'=>null];
        if($attempt&&in_array($attempt['state'],['sending','unknown'],true))throw new SendProblem('send_unconfirmed',409,'No se pudo confirmar el envío. Revisa el historial: repetir esta nota de voz está bloqueado para evitar duplicados.');
        if($connection['status']!=='connected')throw new SendProblem('connection_unverified',422,'Comprueba la conexión antes de enviar.');

        $url=$this->upload($connection,$conversationId,$mime,$bytes);
        try {
            $result=(new MessageSender($this->gateway))->send($connection,$conversation,['requestId'=>$requestId,'channel'=>'WhatsApp'],
                ['attachments'=>[$url],'fingerprint'=>hash('sha256',$conversationId.'|'.$bytes)]);
        } catch(SendProblem $e) {
            // Para diagnosticar: cómo sirve el almacén el archivo que la cuenta no pudo entregar.
            if($e->reason!=='send_rejected')throw $e;
            $served=$this->put?'':self::servedType($url);
            throw new SendProblem($e->reason,$e->httpStatus,$e->getMessage().($served!==''?' El archivo se sirve como «'.$served.'».':''),$e->retryAfter);
        }
        return $result+['url'=>$url];
    }

    /** Que sea de verdad un audio de un tipo que WhatsApp admite, y de tamaño razonable. */
    public static function check(string $mime,string $bytes):void {
        $size=strlen($bytes);
        if($size<200)throw new SendProblem('voice_empty',422,'La grabación está vacía. Vuelve a grabarla.');
        if($size>self::MAX_BYTES)throw new SendProblem('voice_too_large',413,'La nota de voz es demasiado larga. Grábala de nuevo, más corta.');
        if(str_starts_with($bytes,"\x1A\x45\xDF\xA3"))throw new SendProblem('voice_format',415,'Este navegador grabó en un formato que WhatsApp no acepta. Prueba con Chrome actualizado o con Safari.');
        $ok=match($mime){
            // OGG y con Opus: es lo que WhatsApp reconoce como nota de voz.
            'audio/ogg'=>str_starts_with($bytes,'OggS')&&str_contains(substr($bytes,0,120),'OpusHead'),
            'audio/mp4'=>substr($bytes,4,4)==='ftyp',
            'audio/mpeg'=>str_starts_with($bytes,'ID3')||(ord($bytes[0])===0xFF&&(ord($bytes[1])&0xE0)===0xE0),
            default=>false,
        };
        if(!$ok)throw new SendProblem('voice_format',415,'Ese audio no está en un formato que WhatsApp acepte.');
    }

    /** Subida en dos pasos: pedir la dirección, subir los bytes y confirmar para obtener la dirección pública. */
    private function upload(array $connection,string $conversationId,string $mime,string $bytes):string {
        $ext=self::TYPES[$mime];$name='nota-de-voz.'.$ext;$location=$connection['location_id'];
        // La subida con `; codecs=opus` fue rechazada por la cuenta; con el tipo simple sube bien.
        $label=$mime;
        try {
            $start=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->initiateUpload(['locationId'=>$location,'conversationId'=>$conversationId,'filename'=>$name,'contentType'=>$label,'fileSize'=>strlen($bytes),'channel'=>'WHATSAPP']),false);
            $signed=$start['uploadUrl']??null;$uploadId=$start['uploadId']??null;$path=$start['filePath']??null;
            if(!is_string($signed)||!self::signedHost($signed)||!is_string($uploadId)||!is_string($path))throw new RuntimeException('Unexpected upload answer');
            $this->putBytes($signed,$label,$bytes);
            $done=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->completeUpload(['uploadId'=>$uploadId,'filePath'=>$path,'locationId'=>$location,'conversationId'=>$conversationId,'filename'=>$name]),false);
        } catch(GhlCooldown $e) {
            throw new SendProblem('send_paused',429,'Envío pausado. La nota de voz todavía no se ha enviado.',$e->retryAfter);
        } catch(GhlApiException $e) {
            throw new SendProblem('voice_upload_rejected',in_array($e->status,[413,415],true)?$e->status:424,
                $e->status===413?'La cuenta no acepta un audio tan grande.':($e->status===415?'La cuenta no acepta este tipo de audio.':(in_array($e->status,[401,403],true)?'La clave de la cuenta no permite subir archivos a la conversación.':'La cuenta no pudo recibir el audio (código '.$e->status.($e->detail!==''?': '.$e->detail:'').'). Vuelve a intentarlo.')));
        } catch(SendProblem $e) {throw $e;}
        catch(Throwable $e) {throw new SendProblem('voice_upload_failed',424,'No se pudo subir el audio. Vuelve a intentarlo.');}
        $files=is_array($done['uploadedFiles']??null)?$done['uploadedFiles']:[];
        $url=$files[$name]??(reset($files)?:null);
        if(!is_string($url)||!preg_match('~^https://[^\s<>"]{8,500}$~D',$url))throw new SendProblem('voice_upload_failed',424,'La cuenta no devolvió la dirección del audio. Vuelve a intentarlo.');
        return $url;
    }

    /** La subida va a un almacén de Google, nunca a otro sitio: se comprueba la dirección. */
    public static function signedHost(string $url):bool {
        $parts=parse_url($url);
        return is_array($parts)&&($parts['scheme']??'')==='https'&&is_string($parts['host']??null)
            &&(strcasecmp($parts['host'],'storage.googleapis.com')===0||str_ends_with(strtolower($parts['host']),'.storage.googleapis.com'));
    }

    /** Tipo con el que se sirve la dirección pública del audio (sólo cabecera, sin descargarlo). */
    private static function servedType(string $url):string {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>6,CURLOPT_FOLLOWLOCATION=>false]);
        curl_exec($ch);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        return preg_replace('/[^\w\/.;= +-]/','',$type!==''?$type:'sin tipo (HTTP '.$status.')');
    }

    private function putBytes(string $url,string $mime,string $bytes):void {
        if($this->put){($this->put)($url,$mime,$bytes);return;}
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_POSTFIELDS=>$bytes,CURLOPT_HTTPHEADER=>['Content-Type: '.$mime],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>40]);
        curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($status<200||$status>=300)throw new RuntimeException('Signed upload failed: '.$status);
    }
}
