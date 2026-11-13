<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

/**
 * The contacts screen of one account. An assigned-only member sees only the
 * contacts assigned to them, so the screen reports that the list is restricted
 * instead of presenting a short list as if it were the whole account.
 */
final class ContactDirectory {
    private const TTL=60;
    private const LIMIT=50;
    private const MAX_PAGE=40;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function get(array $connection,?string $search,array $membership,bool $refresh=false,int $page=1):array {
        $query=self::search($search);
        $page=max(1,min(self::MAX_PAGE,$page));
        $restricted=($membership['capabilities']['assignedDataOnly']??false)===true;
        $assignee=$membership['external_user_id']??null;
        if($restricted)return $this->mine($connection,$query,is_string($assignee)?$assignee:'',$page);
        $key='contacts:'.substr(hash('sha256',(string)$query),0,40);
        try {$records=$this->store($connection,$key,$this->fetch($connection,$query));}
        catch(GhlCooldown $e) {
            // Only a paused budget serves the stored list, so the screen still opens.
            $records=$this->cached($connection,$key,false);
            if($records===null)throw $e;
        }
        return [
            'items'=>array_map(fn(array $row):array=>$row+['assignedName'=>$this->member($connection,$row['assignedTo'])],$records),
            'total'=>count($records),
            'query'=>$query,
            'restricted'=>false,
            'partial'=>count($records)>=self::LIMIT,
            'page'=>1,'hasMore'=>false,'exact'=>false,
        ];
    }

    /**
     * Los contactos de quien sólo ve lo suyo, pedidos a la cuenta ya filtrados por
     * su usuario: la lista son de verdad los suyos, con el total real y por páginas.
     * La forma del filtro y del orden no está publicada, así que se prueban las
     * variantes conocidas; una respuesta que trae contactos de otra persona se
     * descarta (la cuenta ignoró el filtro). Si ninguna sirve, se cae a lo de
     * antes (los suyos entre los más recientes de la cuenta) y la pantalla lo dice.
     */
    private function mine(array $connection,?string $query,string $assignee,int $page):array {
        $base=['items'=>[],'total'=>0,'query'=>$query,'restricted'=>true,'partial'=>false,'page'=>$page,'hasMore'=>false,'exact'=>true];
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$assignee))return $base;
        $key='contacts-mine:'.substr(hash('sha256',$assignee.'|'.$query.'|'.$page),0,40);
        $sort=[['field'=>'dateAdded','direction'=>'desc']];
        $attempts=[
            [[['field'=>'assignedTo','operator'=>'eq','value'=>$assignee]],$sort],
            [[['field'=>'assignedTo','operator'=>'eq','value'=>$assignee]],null],
            [[['field'=>'assignedTo','operator'=>'eq','value'=>[$assignee]]],null],
        ];
        try {
            $found=$this->gateway->execute($connection,function(GhlClient $client)use($connection,$query,$assignee,$page,$attempts):array {
                foreach($attempts as [$filters,$order]) {
                    try {$data=$client->searchContacts($connection['location_id'],$filters,$page,self::LIMIT,$query,$order);}
                    catch(GhlApiException $e){if(in_array($e->status,[400,422],true))continue;throw $e;}
                    $contacts=$data['contacts']??null;
                    if(!is_array($contacts)||!array_is_list($contacts))continue;
                    $items=$this->normalize($connection,$contacts);
                    foreach($items as $row)if($row['assignedTo']!==$assignee)continue 2;
                    $total=$data['total']??($data['meta']['total']??null);
                    return ['items'=>$items,'total'=>is_numeric($total)&&$total>=count($items)?(int)$total:null];
                }
                return ['unsupported'=>true];
            },false);
            if(isset($found['unsupported']))$found=null;else $this->store($connection,$key,$found);
        } catch(GhlCooldown $e) {
            $found=$this->cached($connection,$key,false);
            if($found===null)throw $e;
        }
        if($found===null) {
            // La cuenta no aceptó el filtro: los suyos entre los más recientes, como antes.
            $records=$this->fetch($connection,$query);
            $visible=array_values(array_filter($records,fn(array $row):bool=>$row['assignedTo']===$assignee));
            return ['items'=>array_map(fn(array $row):array=>$row+['assignedName'=>$this->member($connection,$row['assignedTo'])],$visible),
                'total'=>count($visible),'partial'=>count($records)>=self::LIMIT,'exact'=>false,'page'=>1,'hasMore'=>false]+$base;
        }
        $items=$found['items'];
        $total=$found['total']??(($page-1)*self::LIMIT+count($items));
        return [
            'items'=>array_map(fn(array $row):array=>$row+['assignedName'=>$this->member($connection,$row['assignedTo'])],$items),
            'total'=>$total,
            'partial'=>$total>$page*self::LIMIT,
            'hasMore'=>$page<self::MAX_PAGE&&($found['total']!==null?$total>$page*self::LIMIT:count($items)>=self::LIMIT),
        ]+$base;
    }

    private function fetch(array $connection,?string $query):array {
        $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->locationContacts($connection['location_id'],$query,self::LIMIT),false);
        $contacts=$data['contacts']??null;
        if(!is_array($contacts)||!array_is_list($contacts))throw new RuntimeException('Invalid contacts response');
        return $this->normalize($connection,$contacts);
    }

    private function normalize(array $connection,array $contacts):array {
        $items=[];
        foreach(array_slice($contacts,0,self::LIMIT) as $contact) {
            if(!is_array($contact))continue;
            $id=self::identifier($contact['id']??null);
            if($id===null)continue;
            if(isset($contact['locationId'])&&$contact['locationId']!==$connection['location_id'])continue;
            $name=self::text($contact['contactName']??$contact['name']??null,190)
                ??trim((self::text($contact['firstName']??null,90)??'').' '.(self::text($contact['lastName']??null,90)??''));
            $tags=[];
            if(is_array($contact['tags']??null))foreach(array_slice($contact['tags'],0,20) as $tag){$tag=self::text($tag,100);if($tag!==null)$tags[]=$tag;}
            $items[]=[
                'id'=>$id,
                'name'=>$name!==''?$name:'Contacto sin nombre',
                'email'=>self::text($contact['email']??null,190),
                'phone'=>self::text($contact['phone']??null,60),
                'companyName'=>self::text($contact['companyName']??null,190),
                'assignedTo'=>self::identifier($contact['assignedTo']??null),
                'dateAdded'=>self::text($contact['dateAdded']??null,80),
                'tags'=>array_values(array_unique($tags)),
            ];
        }
        return $items;
    }

    private function cached(array $connection,string $key,bool $refresh):?array {
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM directory_cache WHERE connection_id=? AND cache_key=?',[$connection['id'],$key])->fetch();
        if(!$row)return null;
        $value=json_decode($row['payload'],true);
        return is_array($value)?$value:null;
    }

    private function store(array $connection,string $key,array $records):array {
        query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],$key,json_encode($records,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $records;
    }

    private function member(array $connection,?string $externalId):?string {
        if($externalId===null)return null;
        $name=query('SELECT display_name FROM external_users WHERE connection_id=? AND external_user_id=? ORDER BY active DESC LIMIT 1',[$connection['id'],$externalId])->fetchColumn();
        return is_string($name)?$name:null;
    }

    private static function search(?string $value):?string {
        if($value===null)return null;
        $value=trim($value);
        if($value===''||mb_strlen($value)>100)return null;
        // Only a plain term reaches the account; no operator is forwarded.
        return preg_replace('/[^\p{L}\p{N}@._+\- ]/u','',$value)?:null;
    }
    private static function identifier(mixed $value):?string {
        return is_string($value)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$value)?$value:null;
    }
    private static function text(mixed $value,int $max):?string {
        if(!is_scalar($value))return null;
        $value=trim((string)$value);
        return $value!==''&&mb_strlen($value)<=$max?$value:null;
    }
}
