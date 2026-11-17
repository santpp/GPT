<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/Attachments.php';
/**
 * 1.10.17 · Snippets (respuestas guardadas) de la subcuenta, para usarlos en la bandeja.
 *
 * Se leen de la cuenta (plantillas de tipo texto, GET /locations/{id}/templates?type=sms) y se
 * guardan 10 minutos. Al enviarlos por la API la cuenta no rellena los campos {{contact.…}}, así
 * que aquí se rellenan con la ficha del contacto y el nombre de quien escribe; lo que no se
 * conoce queda marcado para que la persona lo complete antes de enviar.
 */
final class Snippets {
    private const TTL=600;private const PAGES=5;
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}

    /** @return list<array{id:string,name:string,body:string,attachments:list<string>}> */
    public function list(array $connection,bool $fresh=false):array {
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM directory_cache WHERE connection_id=? AND cache_key=?',[$connection['id'],'snippets'])->fetch();
        if($row&&!$fresh&&(int)$row['fetched']>time()-self::TTL){$v=json_decode($row['payload'],true);if(is_array($v))return $v;}
        $raw=[];
        for($page=0;$page<self::PAGES;$page++){
            $data=$this->gateway->execute($connection,fn(GhlClient $c)=>$c->snippets($connection['location_id'],$page*100),false);
            $items=is_array($data['templates']??null)?array_values(array_filter($data['templates'],'is_array')):[];
            $raw=array_merge($raw,$items);
            $total=$data['totalCount']??null;
            if(count($items)<100||(is_numeric($total)&&count($raw)>=(int)$total))break;
        }
        $out=[];
        foreach($raw as $t){$n=self::normalize($t);if($n)$out[]=$n;}
        usort($out,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
        query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],'snippets',json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        return $out;
    }

    public static function normalize(array $t):?array {
        $type=strtolower((string)($t['type']??'sms'));
        if($type!=='sms'||!empty($t['deleted']))return null;
        $id=$t['id']??($t['_id']??null);
        if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))return null;
        $body=$t['template']['body']??($t['body']??'');
        if(!is_string($body))$body='';
        $files=[];
        foreach([$t['urlAttachments']??[],$t['template']['attachments']??[]] as $list){
            if(!is_array($list))continue;
            array_walk_recursive($list,function($v)use(&$files){if(is_string($v)&&Attachments::isUrl($v)&&!in_array($v,$files,true))$files[]=$v;});
        }
        if(trim($body)===''&&!$files)return null;
        $name=is_string($t['name']??null)&&trim($t['name'])!==''?mb_substr(trim($t['name']),0,120):'Sin nombre';
        return ['id'=>$id,'name'=>$name,'body'=>mb_substr($body,0,4000),'attachments'=>array_slice($files,0,Attachments::PER_MESSAGE)];
    }

    /**
     * Rellena los campos conocidos. Devuelve el texto y la lista de campos que quedaron sin dato.
     * @param array{firstName?:?string,lastName?:?string,name?:?string,email?:?string,phone?:?string,companyName?:?string} $contact
     */
    public static function render(string $body,array $contact,array $user,string $location):array {
        $first=trim((string)($contact['firstName']??''));$last=trim((string)($contact['lastName']??''));
        $full=trim((string)($contact['name']??''))?:trim($first.' '.$last);
        if($first===''&&$full!=='')$first=explode(' ',$full)[0];
        $uFull=trim((string)($user['name']??''));$uParts=preg_split('/\s+/u',$uFull)?:[''];
        $known=[
            'contact.first_name'=>$first,'contact.firstname'=>$first,'contact.last_name'=>$last,'contact.lastname'=>$last,
            'contact.name'=>$full,'contact.full_name'=>$full,'contact.email'=>(string)($contact['email']??''),'contact.phone'=>(string)($contact['phone']??''),
            'contact.company_name'=>(string)($contact['companyName']??''),
            'user.name'=>$uFull,'user.full_name'=>$uFull,'user.first_name'=>$uParts[0]??'','user.last_name'=>count($uParts)>1?implode(' ',array_slice($uParts,1)):'',
            'user.email'=>(string)($user['email']??''),'location.name'=>$location,
        ];
        $missing=[];
        $text=preg_replace_callback('/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/u',function(array $m)use($known,&$missing):string{
            $k=strtolower($m[1]);
            if(isset($known[$k])&&trim($known[$k])!=='')return $known[$k];
            if(!in_array($m[0],$missing,true))$missing[]=$m[0];
            return $m[0];
        },$body)??$body;
        return ['text'=>$text,'missing'=>$missing];
    }
}

final class QuickReplyProblem extends RuntimeException {
    public function __construct(public readonly string $reason,public readonly int $httpStatus,string $message){parent::__construct($message);}
}
/**
 * 1.10.18 · Respuestas guardadas que crean las personas en Ágora (la cuenta no permite crearlas por su API).
 * Cada persona tiene las suyas; quien administra la subcuenta puede compartir una con todo el equipo.
 */
final class QuickReplies {
    public const MAX_OWN=100;public const MAX_SHARED=200;
    /** Las propias de quien mira y las compartidas del equipo, con lo que puede hacer con cada una. */
    public static function list(int $connection,string $kind,int $actor,bool $admin):array {
        $rows=query('SELECT * FROM quick_replies WHERE connection_id=? AND (shared=1 OR (identity_type=? AND actor_id=?)) ORDER BY shared,name LIMIT 400',[$connection,$kind,$actor])->fetchAll();
        return array_map(function(array $r)use($kind,$actor,$admin):array{
            $mine=$r['identity_type']===$kind&&(int)$r['actor_id']===$actor;
            $files=json_decode((string)($r['attachments']??'[]'),true);
            return ['id'=>'q'.$r['id'],'name'=>$r['name'],'body'=>$r['body'],'shared'=>(bool)$r['shared'],'mine'=>$mine,
                'editable'=>$mine||((bool)$r['shared']&&$admin),'attachments'=>is_array($files)?array_values(array_filter($files,'is_string')):[]];
        },$rows);
    }
    /** Crea o cambia una respuesta. Los archivos solo pueden ser los ya subidos en la conversación desde la que se guarda. */
    public static function save(int $tenant,int $connection,string $conversation,string $kind,int $actor,bool $admin,array $in):array {
        $name=trim((string)($in['name']??''));$body=(string)($in['body']??'');
        if($name===''||mb_strlen($name)>120)throw new QuickReplyProblem('invalid_name',422,'Ponle un nombre de hasta 120 caracteres.');
        if(trim($body)===''||mb_strlen($body)>4000)throw new QuickReplyProblem('invalid_body',422,'Escribe el texto de la respuesta (hasta 4.000 caracteres).');
        $shared=($in['shared']??false)===true;
        if($shared&&!$admin)throw new QuickReplyProblem('share_forbidden',403,'Solo quien administra la subcuenta puede compartir respuestas con el equipo.');
        $files=$in['attachments']??[];
        if(!is_array($files)||!array_is_list($files)||count($files)>Attachments::PER_MESSAGE)throw new QuickReplyProblem('invalid_attachments',422,'Una respuesta puede llevar hasta '.Attachments::PER_MESSAGE.' archivos.');
        $id=null;$old=null;
        if(isset($in['id'])){
            if(!is_string($in['id'])||!preg_match('/^q(\d{1,18})$/D',$in['id'],$m))throw new QuickReplyProblem('not_found',404,'Esa respuesta ya no existe.');
            $id=(int)$m[1];
            $old=query('SELECT * FROM quick_replies WHERE id=? AND connection_id=?',[$id,$connection])->fetch();
            if(!$old)throw new QuickReplyProblem('not_found',404,'Esa respuesta ya no existe.');
            $mine=$old['identity_type']===$kind&&(int)$old['actor_id']===$actor;
            if(!$mine&&!((bool)$old['shared']&&$admin))throw new QuickReplyProblem('edit_forbidden',403,'Solo puedes cambiar tus respuestas.');
        }
        $known=array_merge(Attachments::allowed($connection,$conversation),$old?(json_decode((string)$old['attachments'],true)?:[]):[]);
        foreach($files as $f)if(!is_string($f)||!in_array($f,$known,true))throw new QuickReplyProblem('invalid_attachments',422,'Un archivo ya no está disponible. Vuelve a adjuntarlo.');
        $json=json_encode(array_values(array_unique($files)),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        if($id===null){
            $count=(int)query('SELECT COUNT(*) FROM quick_replies WHERE connection_id=? AND '.($shared?'shared=1':'shared=0 AND identity_type=? AND actor_id=?'),$shared?[$connection]:[$connection,$kind,$actor])->fetchColumn();
            if($count>=($shared?self::MAX_SHARED:self::MAX_OWN))throw new QuickReplyProblem('too_many',422,'Llegaste al máximo de respuestas guardadas. Borra alguna que ya no uses.');
            query('INSERT INTO quick_replies(tenant_id,connection_id,identity_type,actor_id,shared,name,body,attachments,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$tenant,$connection,$kind,$actor,$shared?1:0,$name,$body,$json,time(),time()]);
            $id=(int)db()->lastInsertId();
        } else query('UPDATE quick_replies SET name=?,body=?,shared=?,attachments=?,updated_at=? WHERE id=?',[$name,$body,$shared?1:0,$json,time(),$id]);
        return ['id'=>'q'.$id,'saved'=>true];
    }
    public static function delete(int $connection,string $kind,int $actor,bool $admin,mixed $ref):void {
        if(!is_string($ref)||!preg_match('/^q(\d{1,18})$/D',$ref,$m))throw new QuickReplyProblem('not_found',404,'Esa respuesta ya no existe.');
        $row=query('SELECT * FROM quick_replies WHERE id=? AND connection_id=?',[(int)$m[1],$connection])->fetch();
        if(!$row)throw new QuickReplyProblem('not_found',404,'Esa respuesta ya no existe.');
        $mine=$row['identity_type']===$kind&&(int)$row['actor_id']===$actor;
        if(!$mine&&!((bool)$row['shared']&&$admin))throw new QuickReplyProblem('edit_forbidden',403,'Solo puedes borrar tus respuestas.');
        query('DELETE FROM quick_replies WHERE id=?',[(int)$m[1]]);
    }
}
