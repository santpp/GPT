<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

/**
 * Every custom field the account defines for contacts, with the shape needed to
 * show and edit it. The directory is the definition; a contact only carries the
 * values it has, so the panel can offer an empty field to fill in.
 */
final class ContactFieldDirectory {
    // The schema publishes dataType as a free string. These are the shapes Ágora
    // knows how to edit; anything else is shown as read-only text.
    public const EDITABLE_TYPES=['TEXT','LARGE_TEXT','NUMERICAL','MONETORY','PHONE','EMAIL','DATE','CHECKBOX','SINGLE_OPTIONS','RADIO','MULTIPLE_OPTIONS','TEXTBOX_LIST'];

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}

    public function get(array $connection):array {
        $cached=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM contact_field_cache WHERE connection_id=?',[$connection['id']])->fetch();
        if($cached&&time()-(int)$cached['fetched']<3600){$items=json_decode($cached['payload'],true);if(is_array($items))return $items;}
        $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->contactFields($connection['location_id']),false);
        $items=$this->normalize($data,$connection);
        query('INSERT INTO contact_field_cache(connection_id,payload) VALUES(?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $items;
    }

    /** The stored directory while it is fresh, for a caller that must not spend a request. */
    public function cached(array $connection):?array {
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM contact_field_cache WHERE connection_id=?',[$connection['id']])->fetch();
        if(!$row||time()-(int)$row['fetched']>=3600)return null;
        $items=json_decode($row['payload'],true);
        if(!is_array($items))return null;
        // A directory stored before field types existed is read as stale, so an
        // update refetches it instead of offering every field as plain text.
        foreach($items as $item)if(!is_array($item)||!isset($item['dataType']))return null;
        return $items;
    }

    public function normalize(array $data,array $connection):array {
        if(!is_array($data['customFields']??null)||!array_is_list($data['customFields'])||count($data['customFields'])>1000)throw new RuntimeException('Invalid custom fields');
        $items=[];
        foreach($data['customFields'] as $field) {
            if(!is_array($field)||($field['locationId']??null)!==$connection['location_id']||($field['model']??'contact')!=='contact')continue;
            $id=(string)($field['id']??'');
            $name=trim((string)($field['name']??''));
            if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id)||$name===''||mb_strlen($name)>190)continue;
            $options=[];
            foreach(is_array($field['picklistOptions']??null)?array_slice($field['picklistOptions'],0,200):[] as $option) {
                if(is_array($option))$option=$option['value']??$option['label']??null;
                if(!is_scalar($option))continue;
                $option=trim((string)$option);
                if($option!==''&&mb_strlen($option)<=300)$options[]=$option;
            }
            $items[]=[
                'id'=>$id,
                'name'=>$name,
                'dataType'=>self::dataType($field['dataType']??null),
                'options'=>array_values(array_unique($options)),
                'placeholder'=>self::short($field['placeholder']??null,190),
                'position'=>is_numeric($field['position']??null)?(int)$field['position']:999,
            ];
        }
        usort($items,fn(array $a,array $b):int=>[$a['position'],$a['name']]<=>[$b['position'],$b['name']]);
        return $items;
    }

    private static function dataType(mixed $value):string {
        $value=is_string($value)?strtoupper(trim($value)):'';
        if($value===''||!preg_match('/^[A-Z_]{1,40}$/D',$value))return 'TEXT';
        return $value;
    }
    private static function short(mixed $value,int $max):?string {
        if(!is_scalar($value))return null;
        $value=trim((string)$value);
        return $value!==''&&mb_strlen($value)<=$max?$value:null;
    }
}
