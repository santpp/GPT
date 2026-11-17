<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
/**
 * 1.10.17 · Archivos adjuntos para enviar por la bandeja (WhatsApp y los demás canales).
 *
 * El navegador manda el archivo a nuestro servidor; el servidor lo sube a la cuenta y
 * recibe su dirección. Esa dirección queda anotada para la conversación (directory_cache,
 * clave «adjuntos:<conversación>») y sólo las anotadas se pueden adjuntar al enviar:
 * nadie puede mandar una dirección cualquiera desde el navegador.
 */
final class AttachmentProblem extends RuntimeException {
    public function __construct(public readonly string $reason,public readonly int $httpStatus,string $message,public readonly int $retryAfter=0){parent::__construct($message);}
}
final class Attachments {
    /** Hasta 5 MB por la subida directa; por WhatsApp, hasta 16 MB con la subida en dos pasos. */
    public const SIMPLE=5*1024*1024;
    public const MAX_WHATSAPP=16*1024*1024;
    public const PER_MESSAGE=5;
    /** Los tipos que la cuenta acepta, con el tipo real que puede traer cada uno. */
    public const TYPES=[
        'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],
        'pdf'=>['application/pdf'],
        'doc'=>['application/msword','application/vnd.ms-office','application/octet-stream'],
        'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'],
        'txt'=>['text/plain'],
        'mp4'=>['video/mp4','application/mp4'],'mpeg'=>['video/mpeg','audio/mpeg'],
        'mp3'=>['audio/mpeg','audio/mp3'],'wav'=>['audio/wav','audio/x-wav','audio/wave'],
        'zip'=>['application/zip','application/x-zip-compressed'],'rar'=>['application/x-rar','application/vnd.rar','application/x-rar-compressed'],
    ];
    private const SEND_AS=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf','doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','txt'=>'text/plain','mp4'=>'video/mp4','mpeg'=>'video/mpeg',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','zip'=>'application/zip','rar'=>'application/vnd.rar'];

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}

    /** Sube un archivo ya recibido por el servidor y devuelve su dirección en la cuenta. */
    public function upload(array $connection,array $conversation,string $path,string $original,int $size,string $channel):array {
        $name=self::cleanName($original);
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!isset(self::TYPES[$ext]))throw new AttachmentProblem('file_type',415,'Ese tipo de archivo no se puede enviar. Sirven imágenes (JPG, PNG), PDF, Word, texto, audio (MP3, WAV), video (MP4) y comprimidos (ZIP, RAR).');
        if($size<=0||!is_file($path))throw new AttachmentProblem('file_empty',422,'El archivo está vacío o no llegó completo.');
        $limit=$channel==='WhatsApp'?self::MAX_WHATSAPP:self::SIMPLE;
        if($size>$limit)throw new AttachmentProblem('file_too_large',413,$channel==='WhatsApp'?'El archivo pasa de 16 MB, el máximo para enviar por WhatsApp.':'El archivo pasa de 5 MB, el máximo para este canal. Por WhatsApp se pueden enviar hasta 16 MB.');
        $real=(new finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';
        if(!in_array($real,self::TYPES[$ext],true))throw new AttachmentProblem('file_mismatch',415,'El contenido del archivo no corresponde a su extensión (.'.$ext.'). Guárdalo de nuevo y vuelve a intentarlo.');
        $contact=$conversation['contactId']??null;$id=$conversation['id']??null;
        if(!is_string($contact)||!is_string($id)||($conversation['locationId']??null)!==$connection['location_id'])throw new AttachmentProblem('invalid_contact',422,'Actualiza la conversación antes de adjuntar.');
        $bytes=(string)file_get_contents($path);$mime=self::SEND_AS[$ext];
        try {
            if($size<=self::SIMPLE){
                $data=$this->gateway->execute($connection,fn(GhlClient $c)=>$c->uploadAttachment($connection['location_id'],$id,$contact,$name,$mime,$bytes),false);
            } else {
                // Más de 5 MB (sólo WhatsApp): dirección firmada, subida y confirmación.
                $start=$this->gateway->execute($connection,fn(GhlClient $c)=>$c->initiateUpload(['locationId'=>$connection['location_id'],'conversationId'=>$id,'filename'=>$name,'contentType'=>$mime,'fileSize'=>$size,'channel'=>'WHATSAPP']),false);
                foreach(['uploadUrl','uploadId','filePath'] as $k)if(!is_string($start[$k]??null)||$start[$k]==='')throw new RuntimeException('Unexpected upload answer');
                $this->gateway->execute($connection,function(GhlClient $c)use($start,$mime,$bytes):array{$c->putSigned($start['uploadUrl'],$mime,$bytes);return [];},false);
                $data=$this->gateway->execute($connection,fn(GhlClient $c)=>$c->completeUpload(['uploadId'=>$start['uploadId'],'filePath'=>$start['filePath'],'locationId'=>$connection['location_id'],'conversationId'=>$id,'filename'=>$name]),false);
            }
        }
        catch(GhlCooldown $e){throw new AttachmentProblem('upload_paused',429,'La subida está en espera para respetar la cuota. Vuelve a intentarlo en unos segundos.',$e->retryAfter);}
        catch(GhlApiException $e){
            if($e->status===413)throw new AttachmentProblem('file_too_large',413,'La cuenta no aceptó el archivo por su tamaño.');
            if(in_array($e->status,[401,403],true))throw new AttachmentProblem('upload_forbidden',424,'La clave de la cuenta no permite subir archivos. Hace falta el permiso de escritura de mensajes.');
            throw new AttachmentProblem('upload_rejected',424,'La cuenta no aceptó el archivo (HTTP '.$e->status.($e->detail!==''?': '.$e->detail:'').').');
        }
        catch(AttachmentProblem $e){throw $e;}
        catch(Throwable $e){throw new AttachmentProblem('upload_failed',424,'No se pudo subir el archivo. Vuelve a intentarlo.');}
        $url=self::urlFrom($data,$name);
        if($url===null)throw new AttachmentProblem('upload_unconfirmed',424,'La cuenta no devolvió la dirección del archivo. Vuelve a intentarlo.');
        self::allow((int)$connection['id'],$id,[$url]);
        return ['url'=>$url,'name'=>$name,'size'=>$size,'type'=>$mime];
    }

    /** La dirección que devuelve la cuenta: uploadedFiles es un mapa nombre → dirección. */
    public static function urlFrom(array $data,string $name):?string {
        $files=$data['uploadedFiles']??null;if(!is_array($files))return null;
        $pick=$files[$name]??null;
        if(!is_string($pick))foreach($files as $v){if(is_string($v)){$pick=$v;break;}if(is_array($v)&&is_string($v['url']??null)){$pick=$v['url'];break;}}
        return is_string($pick)&&self::isUrl($pick)?$pick:null;
    }
    public static function isUrl(string $url):bool {return strlen($url)<=2000&&parse_url($url,PHP_URL_SCHEME)==='https'&&is_string(parse_url($url,PHP_URL_HOST));}

    /** Anota direcciones que se pueden adjuntar en esta conversación (las últimas 40). */
    public static function allow(int $connection,string $conversation,array $urls):void {
        $key='adjuntos:'.$conversation;
        $have=self::allowed($connection,$conversation);
        foreach($urls as $u)if(is_string($u)&&self::isUrl($u)&&!in_array($u,$have,true))$have[]=$u;
        $have=array_slice($have,-40);
        query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection,$key,json_encode($have,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)]);
    }
    public static function allowed(int $connection,string $conversation):array {
        $row=query('SELECT payload FROM directory_cache WHERE connection_id=? AND cache_key=?',[$connection,'adjuntos:'.$conversation])->fetchColumn();
        $list=is_string($row)?json_decode($row,true):null;
        return is_array($list)?array_values(array_filter($list,'is_string')):[];
    }
    /** Las direcciones pedidas para un envío: como mucho 5, y todas anotadas para esta conversación. */
    public static function forSend(int $connection,string $conversation,mixed $input):array {
        if($input===null||$input===[])return [];
        if(!is_array($input)||!array_is_list($input)||count($input)>self::PER_MESSAGE)throw new SendProblem('invalid_attachments',422,'Puedes adjuntar hasta '.self::PER_MESSAGE.' archivos por mensaje.');
        $allowed=self::allowed($connection,$conversation);
        foreach($input as $u)if(!is_string($u)||!in_array($u,$allowed,true))throw new SendProblem('invalid_attachments',422,'Un adjunto ya no está disponible. Quítalo y vuelve a adjuntarlo.');
        return array_values(array_unique($input));
    }
    public static function cleanName(string $name):string {
        $name=basename(str_replace('\\','/',$name));
        $name=preg_replace('/[^\p{L}\p{N}._ -]+/u','_',$name)??'archivo';
        $name=trim(preg_replace('/\s+/u',' ',$name)??'',' .');
        return mb_substr($name!==''?$name:'archivo',-120);
    }
}
