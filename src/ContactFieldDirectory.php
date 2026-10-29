<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

final class ContactFieldDirectory {
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}
    public function get(array $connection):array {
        $cached=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM contact_field_cache WHERE connection_id=?',[$connection['id']])->fetch();
        if($cached&&time()-(int)$cached['fetched']<3600){$items=json_decode($cached['payload'],true);if(is_array($items))return $items;}
        $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->contactFields($connection['location_id']),false);
        if(!is_array($data['customFields']??null)||!array_is_list($data['customFields'])||count($data['customFields'])>1000)throw new RuntimeException('Invalid custom fields');
        $items=[];foreach($data['customFields'] as $field){
            if(!is_array($field)||($field['locationId']??null)!==$connection['location_id']||($field['model']??'contact')!=='contact')continue;
            $id=(string)($field['id']??'');$name=trim((string)($field['name']??''));
            if(preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id)&&$name!==''&&mb_strlen($name)<=190)$items[]=['id'=>$id,'name'=>$name];
        }
        query('INSERT INTO contact_field_cache(connection_id,payload) VALUES(?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $items;
    }
}
