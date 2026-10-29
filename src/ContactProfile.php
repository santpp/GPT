<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

final class ContactProfile {
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}

    public function get(array $connection,string $contactId,bool $refresh=false):array {
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))throw new InvalidArgumentException('Invalid contact id');
        $cached=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM contact_profile_cache WHERE connection_id=? AND contact_id=?',[$connection['id'],$contactId])->fetch();
        if(!$refresh&&$cached&&time()-(int)$cached['fetched']<60){$value=json_decode($cached['payload'],true);if(is_array($value))return $value;}
        $response=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->contact($contactId),false);
        $profile=$this->normalize($response['contact']??null,$connection,$contactId);
        query('INSERT INTO contact_profile_cache(connection_id,contact_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],$contactId,json_encode($profile,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $profile;
    }

    private function normalize(mixed $contact,array $connection,string $expectedId):array {
        if(!is_array($contact)||($contact['id']??null)!==$expectedId||($contact['locationId']??null)!==$connection['location_id'])throw new RuntimeException('Contact mismatch');
        $assigned=$this->text($contact['assignedTo']??null,80);
        $assignedName=$assigned?query('SELECT display_name FROM external_users WHERE connection_id=? AND external_user_id=? ORDER BY active DESC LIMIT 1',[$connection['id'],$assigned])->fetchColumn():false;
        $tags=[];if(is_array($contact['tags']??null))foreach(array_slice($contact['tags'],0,100) as $tag){$tag=$this->text($tag,100);if($tag!==null)$tags[]=$tag;}
        // Every value the account holds is kept, including the empty ones, so the
        // panel can show a defined field that nobody has filled in yet.
        $fields=[];if(is_array($contact['customFields']??null))foreach(array_slice($contact['customFields'],0,200) as $field){
            if(!is_array($field)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',(string)($field['id']??'')))continue;
            $raw=$field['value']??$field['field_value']??null;
            if(is_array($raw)){
                $clean=[];foreach(array_slice($raw,0,50) as $item){$item=$this->text($item,300);if($item!==null)$clean[]=$item;}
                $fields[]=['id'=>$field['id'],'label'=>'Campo personalizado','value'=>implode(', ',$clean),'values'=>$clean];
                continue;
            }
            if(is_bool($raw)){$fields[]=['id'=>$field['id'],'label'=>'Campo personalizado','value'=>$raw?'Sí':'No','checked'=>$raw];continue;}
            $value=$this->text($raw,1000);
            $fields[]=['id'=>$field['id'],'label'=>'Campo personalizado','value'=>$value??''];
        }
        $name=$this->text($contact['name']??null,190)??trim(($this->text($contact['firstName']??null,90)??'').' '.($this->text($contact['lastName']??null,90)??''));
        return [
            'id'=>$expectedId,'name'=>$name!==''?$name:'Contacto','firstName'=>$this->text($contact['firstName']??null,90),'lastName'=>$this->text($contact['lastName']??null,90),
            'email'=>$this->text($contact['email']??null,190),'phone'=>$this->text($contact['phone']??null,60),'companyName'=>$this->text($contact['companyName']??null,190),
            'source'=>$this->text($contact['source']??null,190),'assignedTo'=>$assigned,'assignedName'=>is_string($assignedName)?$assignedName:null,
            'lastActivity'=>$this->text($contact['lastActivity']??$contact['dateUpdated']??null,80),'dateAdded'=>$this->text($contact['dateAdded']??null,80),
            'timezone'=>$this->text($contact['timezone']??null,80),
            // Each part of the address travels on its own so it can be edited;
            // the joined line stays for reading.
            'address1'=>$this->text($contact['address1']??null,190),'city'=>$this->text($contact['city']??null,120),
            'state'=>$this->text($contact['state']??null,120),'postalCode'=>$this->text($contact['postalCode']??null,40),
            'country'=>$this->text($contact['country']??null,80),'website'=>$this->text($contact['website']??null,250),
            'dateOfBirth'=>$this->birthday($contact['dateOfBirth']??null),
            'dnd'=>($contact['dnd']??null)===true,
            'address'=>$this->address($contact),'tags'=>array_values(array_unique($tags)),'customFields'=>$fields,
        ];
    }
    /** Birthdays arrive in several published formats; only a plain date is kept. */
    private function birthday(mixed $value):?string {
        $value=$this->text($value,40);
        if($value===null)return null;
        if(preg_match('/^(\d{4})-(\d{2})-(\d{2})/',$value,$m))return $m[1].'-'.$m[2].'-'.$m[3];
        return null;
    }
    private function address(array $contact):?string {
        $parts=[];foreach(['address1','city','state','postalCode','country'] as $key){$value=$this->text($contact[$key]??null,190);if($value!==null)$parts[]=$value;}return $parts?implode(', ',$parts):null;
    }
    private function text(mixed $value,int $max):?string {
        if(!is_scalar($value))return null;$value=trim((string)$value);return $value!==''&&mb_strlen($value)<=$max?$value:null;
    }
}
