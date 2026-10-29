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

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function get(array $connection,?string $search,array $membership,bool $refresh=false):array {
        $query=self::search($search);
        $key='contacts:'.substr(hash('sha256',(string)$query),0,40);
        $records=$this->cached($connection,$key,$refresh)
            ?? $this->store($connection,$key,$this->fetch($connection,$query));
        $restricted=($membership['capabilities']['assignedDataOnly']??false)===true;
        $assignee=$membership['external_user_id']??null;
        $visible=$restricted?array_values(array_filter($records,fn(array $row):bool=>$row['assignedTo']!==null&&$row['assignedTo']===$assignee)):$records;
        return [
            'items'=>array_map(fn(array $row):array=>$row+['assignedName'=>$this->member($connection,$row['assignedTo'])],$visible),
            'total'=>count($visible),
            'query'=>$query,
            'restricted'=>$restricted,
            'partial'=>count($records)>=self::LIMIT,
        ];
    }

    private function fetch(array $connection,?string $query):array {
        $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->locationContacts($connection['location_id'],$query,self::LIMIT),false);
        $contacts=$data['contacts']??null;
        if(!is_array($contacts)||!array_is_list($contacts))throw new RuntimeException('Invalid contacts response');
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
        if($refresh)return null;
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM directory_cache WHERE connection_id=? AND cache_key=?',[$connection['id'],$key])->fetch();
        if(!$row||time()-(int)$row['fetched']>=self::TTL)return null;
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
