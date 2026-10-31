<?php
declare(strict_types=1);
require_once __DIR__.'/PushQueue.php';
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/MessageHistory.php';

final class PushProblem extends RuntimeException {
    public function __construct(public readonly string $reason,string $message) {parent::__construct($message);}
}

/**
 * Delivery of the queued notices to Firebase Cloud Messaging, HTTP v1.
 *
 * The service account never reaches the browser and never enters Git: the file
 * lives outside the document root and its path comes from the private
 * configuration. A token the service says it does not know is deleted, so a
 * reinstalled phone stops costing a request on every pass.
 */
final class PushDelivery {
    private const TOKEN_URL='https://oauth2.googleapis.com/token';
    private const SCOPE='https://www.googleapis.com/auth/firebase.messaging';
    private const ATTEMPTS=5;

    private ?string $access=null;
    private int $expires=0;
    private ?WebPush $browser=null;

    /** @param ?Closure(string,array,array):array $transport only tests inject this */
    private array $texts=[];

    public function __construct(private readonly ?Closure $transport=null,private readonly ?string $credentialsFile=null,?WebPush $browser=null,private readonly ?GhlGateway $gateway=null) {$this->browser=$browser;}

    /** Either transport being ready is enough for the delivery pass to be worth running. */
    public static function configured():bool {
        return self::firebaseConfigured()||self::browserConfigured();
    }
    public static function firebaseConfigured():bool {
        $file=getenv('FCM_CREDENTIALS_FILE');
        return is_string($file)&&$file!==''&&is_file($file);
    }
    public static function browserConfigured():bool {
        require_once __DIR__.'/WebPush.php';
        return (new WebPush())->configured();
    }

    /**
     * Una pasada de entrega, segura desde cualquier sitio: el cron, el otro
     * cron o una visita. El candado es único, así que dos a la vez no envían
     * el mismo aviso, y quien llega tarde se aparta sin esperar.
     */
    public static function deliverPending(int $batch=20):string {
        if(!self::configured()) {
            self::remember('push_last_run',['at'=>gmdate('c'),'configured'=>false]);
            return 'Notifications are not configured; nothing to deliver';
        }
        if((int)query("SELECT GET_LOCK('agora-push-cron',0)")->fetchColumn()!==1)return 'Delivery pass already running';
        try {
            $result=(new self())->run($batch);
            // Nada del contenido, de los tokens ni de las claves sale por aquí.
            return sprintf('Notices %d, sent %d, retried %d, devices dropped %d',$result['notices'],$result['sent'],$result['retried'],$result['dropped']);
        } catch(Throwable $e) {
            self::remember('push_last_run',['at'=>gmdate('c'),'error'=>'unexpected: '.get_class($e)]);
            return 'Delivery pass failed; inspect the queue state in the app';
        } finally {query("SELECT RELEASE_LOCK('agora-push-cron')");}
    }

    /** Sends up to $batch pending notices. Returns what happened, for the cron. */
    public function run(int $batch=20):array {
        $sent=0;$failed=0;$dropped=0;$notices=0;
        $rows=query("SELECT * FROM push_queue WHERE state='pending' AND not_before<=UNIX_TIMESTAMP() ORDER BY id LIMIT ".max(1,min(100,$batch)))->fetchAll();
        foreach($rows as $notice) {
            $notices++;
            $recipients=PushQueue::recipients($notice);
            if($recipients===[]) {
                // Nobody has a device registered: the notice is done, not failed.
                query("UPDATE push_queue SET state='sent',sent_at=CURRENT_TIMESTAMP,attempts=attempts+1 WHERE id=?",[$notice['id']]);
                continue;
            }
            $notice=$this->withText($notice);
            $retry=false;$reason=null;
            foreach($recipients as $recipient) {
                try {
                    $this->deliver($recipient,$notice);
                    query('UPDATE push_devices SET last_sent_at=CURRENT_TIMESTAMP WHERE id=?',[$recipient['device']]);
                    $sent++;
                } catch(PushProblem $e) {
                    if($e->reason==='unregistered'){query('DELETE FROM push_devices WHERE id=?',[$recipient['device']]);$dropped++;continue;}
                    $retry=true;$reason=$e->reason.': '.mb_substr($e->getMessage(),0,60);
                } catch(Throwable $e) {
                    // Nada imprevisto puede tumbar la pasada entera: queda anotado y sigue.
                    $retry=true;$reason='unexpected: '.get_class($e);
                }
            }
            if($reason!==null)query('UPDATE push_queue SET last_error=? WHERE id=?',[mb_substr($reason,0,80),$notice['id']]);
            if($retry) {
                $attempts=(int)$notice['attempts']+1;
                $failed++;
                // Backs off so a service that is down is not hammered.
                query($attempts>=self::ATTEMPTS
                    ?"UPDATE push_queue SET state='failed',attempts=? WHERE id=?"
                    :"UPDATE push_queue SET attempts=?,not_before=UNIX_TIMESTAMP()+LEAST(900,POW(3,?)) WHERE id=?",
                    $attempts>=self::ATTEMPTS?[$attempts,$notice['id']]:[$attempts,$attempts,$notice['id']]);
                continue;
            }
            query("UPDATE push_queue SET state='sent',sent_at=CURRENT_TIMESTAMP,attempts=attempts+1 WHERE id=?",[$notice['id']]);
        }
        $result=['notices'=>$notices,'sent'=>$sent,'retried'=>$failed,'dropped'=>$dropped];
        self::remember('push_last_run',$result+['at'=>gmdate('c')]);
        return $result;
    }

    /**
     * Un aviso de prueba a los dispositivos de una persona, entregado en el acto.
     * Separa de un golpe las dos mitades de la cadena: si llega, el transporte, el
     * service worker y el teléfono funcionan, y el fallo está en descubrir el
     * mensaje o en el cron; si no llega, el resultado de cada dispositivo dice por qué.
     *
     * @return list<array{device:int,label:?string,platform:string,result:string}>
     */
    public function test(int $tenantId,string $identityType,int $actorId):array {
        $devices=query('SELECT id,token,platform,p256dh,auth,label FROM push_devices WHERE tenant_id=? AND identity_type=? AND actor_id=?',[$tenantId,$identityType,$actorId])->fetchAll();
        $notice=['title'=>'Ágora','body'=>'Aviso de prueba: si lo ves, este dispositivo recibe los avisos.','conversation_id'=>'prueba','connection_id'=>0,'unread'=>0];
        $results=[];
        foreach($devices as $device) {
            $recipient=['token'=>$device['token'],'device'=>(int)$device['id'],'platform'=>$device['platform'],'p256dh'=>$device['p256dh'],'auth'=>$device['auth']];
            try {
                $this->deliver($recipient,$notice);
                query('UPDATE push_devices SET last_sent_at=CURRENT_TIMESTAMP WHERE id=?',[$device['id']]);
                $result='sent';
            } catch(PushProblem $e) {
                if($e->reason==='unregistered')query('DELETE FROM push_devices WHERE id=?',[$device['id']]);
                $result=$e->reason;
            } catch(Throwable $e) {$result='unexpected';}
            $results[]=['device'=>(int)$device['id'],'label'=>$device['label'],'platform'=>$device['platform'],'result'=>$result];
        }
        return $results;
    }

    public static function remember(string $name,array $value):void {
        try {query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$name,json_encode($value,JSON_THROW_ON_ERROR)]);}
        catch(Throwable $e){}
    }
    public static function recall(string $name):?array {
        try {$raw=query('SELECT value FROM app_state WHERE name=?',[$name])->fetchColumn();}
        catch(Throwable $e){return null;}
        $value=is_string($raw)?json_decode($raw,true):null;
        return is_array($value)?$value:null;
    }

    /**
     * El texto del aviso, sacado del propio mensaje. El resumen de la
     * conversación que da la búsqueda puede ir por detrás del mensaje o venir
     * recortado: leído cada 10 segundos, era fácil encontrar la conversación ya
     * sin leer y su resumen todavía vacío. Una lectura por mensaje nuevo; si
     * falla, se queda el resumen que hubiera.
     */
    private function withText(array $notice):array {
        $conversation=(string)($notice['conversation_id']??'');
        if($conversation===''||$conversation==='prueba'||empty($notice['connection_id']))return $notice;
        if(!array_key_exists($conversation,$this->texts)) {
            $this->texts[$conversation]=null;
            try {
                $connection=query('SELECT * FROM ghl_connections WHERE id=?',[(int)$notice['connection_id']])->fetch();
                if($connection&&$connection['status']==='connected') {
                    $data=($this->gateway??new GhlGateway())->execute($connection,fn(GhlClient $client)=>$client->conversationMessages($conversation),false);
                    $items=MessageHistory::page($data,$conversation,$connection['location_id'],null)['items'];
                    $inbound=array_values(array_filter($items,fn($m)=>($m['direction']??null)==='inbound'));
                    $pool=$inbound?:$items;
                    usort($pool,fn($a,$b)=>strcmp((string)($b['dateAdded']??''),(string)($a['dateAdded']??'')));
                    $this->texts[$conversation]=$pool?self::say($pool[0]):null;
                }
            } catch(Throwable $e) {$this->texts[$conversation]=null;}
        }
        $text=$this->texts[$conversation];
        if($text===null)return $notice;
        // Lo que se dijo, conservando cuántos hay sin leer si eran varios.
        $waiting=preg_match('/ · \d+ mensajes sin leer$/u',(string)$notice['body'],$m)?$m[0]:'';
        $notice['body']=mb_substr($text,0,270).$waiting;
        try {query('UPDATE push_queue SET body=? WHERE id=?',[$notice['body'],$notice['id']]);} catch(Throwable $e){}
        return $notice;
    }

    /** Lo que dice un mensaje, o qué envió si no trae texto. */
    public static function say(array $message):?string {
        $text=$message['body']??null;
        $text=is_string($text)?trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags(str_ireplace(['<br>','<br/>','<br />','</p>'],' ',$text)),ENT_QUOTES|ENT_HTML5,'UTF-8'))):'';
        if($text!=='')return $text;
        $files=is_array($message['attachments']??null)?$message['attachments']:[];
        if(!$files)return null;
        $first=is_string($files[0])?$files[0]:(is_array($files[0])&&is_string($files[0]['url']??null)?$files[0]['url']:'');
        $path=strtolower((string)parse_url($first,PHP_URL_PATH));
        return match(true) {
            (bool)preg_match('/\.(png|jpe?g|gif|webp|avif|heic)$/',$path)=>count($files)>1?'Envió '.count($files).' fotos':'Envió una foto',
            (bool)preg_match('/\.(mp3|m4a|aac|ogg|oga|opus|wav|amr)$/',$path)=>'Envió una nota de voz',
            (bool)preg_match('/\.(mp4|mov|webm|3gp|m4v)$/',$path)=>'Envió un vídeo',
            str_ends_with($path,'.pdf')=>'Envió un documento',
            default=>'Envió un archivo',
        };
    }

    /** Each device by the road its own system uses. */
    private function deliver(array $recipient,array $notice):void {
        if(isset($recipient['unread']))$notice['unread']=$recipient['unread'];
        if(($recipient['platform']??'android')==='web') {
            require_once __DIR__.'/WebPush.php';
            ($this->browser??=new WebPush())->send($recipient,$notice);
            return;
        }
        $this->send((string)$recipient['token'],$notice);
    }

    /** One message, in the shape the published HTTP v1 request declares. */
    public function send(string $token,array $notice):void {
        $credentials=$this->credentials();
        $payload=['message'=>[
            'token'=>$token,
            'notification'=>['title'=>(string)$notice['title'],'body'=>(string)$notice['body']],
            // The app opens the conversation from this; no message content here.
            'data'=>[
                'conversationId'=>(string)$notice['conversation_id'],
                'connectionId'=>(string)$notice['connection_id'],
                'path'=>'/conversations/'.$notice['conversation_id'],
            ],
            'android'=>['priority'=>'high','notification'=>['default_sound'=>true,'tag'=>(string)$notice['conversation_id']]],
        ]];
        $response=$this->call(
            'https://fcm.googleapis.com/v1/projects/'.rawurlencode($credentials['project_id']).'/messages:send',
            ['Authorization: Bearer '.$this->accessToken($credentials),'Content-Type: application/json'],
            $payload);
        $status=(int)($response['status']??0);
        if($status>=200&&$status<300)return;
        $body=json_decode((string)($response['body']??''),true);
        $error=is_array($body)?(string)($body['error']['status']??''):'';
        if($status===404||$status===403||in_array($error,['UNREGISTERED','NOT_FOUND','INVALID_ARGUMENT','SENDER_ID_MISMATCH'],true))
            throw new PushProblem('unregistered','El servicio no reconoce ese dispositivo.');
        throw new PushProblem('unavailable','El servicio de notificaciones respondió '.$status.'.');
    }

    /** The service account, read from outside the document root. */
    private function credentials():array {
        $file=$this->credentialsFile??(getenv('FCM_CREDENTIALS_FILE')?:'');
        if($file===''||!is_file($file)||!is_readable($file))throw new PushProblem('unconfigured','Las notificaciones no están configuradas en el servidor.');
        $raw=@file_get_contents($file);
        $data=is_string($raw)?json_decode($raw,true):null;
        if(!is_array($data)||!is_string($data['client_email']??null)||!is_string($data['private_key']??null)||!is_string($data['project_id']??null))
            throw new PushProblem('invalid_credentials','El archivo de credenciales de notificaciones no tiene el formato esperado.');
        return $data;
    }

    /** A short-lived access token, minted from the service account key. */
    private function accessToken(array $credentials):string {
        if($this->access!==null&&$this->expires>time()+60)return $this->access;
        $now=time();
        $claim=['iss'=>$credentials['client_email'],'scope'=>self::SCOPE,'aud'=>self::TOKEN_URL,'iat'=>$now,'exp'=>$now+3600];
        $unsigned=self::base64(json_encode(['alg'=>'RS256','typ'=>'JWT'],JSON_THROW_ON_ERROR)).'.'.self::base64(json_encode($claim,JSON_THROW_ON_ERROR));
        $key=openssl_pkey_get_private($credentials['private_key']);
        if($key===false)throw new PushProblem('invalid_credentials','La clave de notificaciones no se pudo leer.');
        $signature='';
        if(!openssl_sign($unsigned,$signature,$key,OPENSSL_ALGO_SHA256))throw new PushProblem('invalid_credentials','La clave de notificaciones no pudo firmar la petición.');
        $assertion=$unsigned.'.'.self::base64($signature);
        $response=$this->call(self::TOKEN_URL,['Content-Type: application/x-www-form-urlencoded'],
            ['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$assertion],true);
        $body=json_decode((string)($response['body']??''),true);
        if((int)($response['status']??0)!==200||!is_array($body)||!is_string($body['access_token']??null))
            throw new PushProblem('unavailable','No se pudo autenticar contra el servicio de notificaciones.');
        $this->access=$body['access_token'];
        $this->expires=$now+max(60,(int)($body['expires_in']??3600));
        return $this->access;
    }

    private function call(string $url,array $headers,array $payload,bool $form=false):array {
        if($this->transport)return ($this->transport)($url,$headers,$payload);
        $handle=curl_init($url);
        curl_setopt_array($handle,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_POSTFIELDS=>$form?http_build_query($payload):json_encode($payload,JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>8,
        ]);
        $body=curl_exec($handle);
        $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        // A transport failure is a retry, never a dropped device.
        if($body===false)throw new PushProblem('unavailable','No se pudo contactar con el servicio de notificaciones.');
        return ['status'=>$status,'body'=>$body];
    }

    private static function base64(string $value):string {
        return rtrim(strtr(base64_encode($value),'+/','-_'),'=');
    }
}
