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
