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
        // Cada campo trae el id de su carpeta (parentId). El nombre no lo entrega el listado: se pide por id
        // (una vez al día como mucho si la cuenta no lo permite) y el administrador puede ponerlo a mano.
        $state=self::folderState((int)$connection['id']);
        $names=$state['admin']+$state['auto'];
        foreach(self::parentOrder($data,$connection) as $parent) {
            if(isset($names[$parent]))continue;
            if($state['blockedUntil']>time())break;
            try {
                $r=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->customFieldById($parent),false);
                $found=$r['field']['name']??($r['folder']['name']??($r['name']??null));
                $found=is_string($found)?trim($found):'';
                if($found===''||mb_strlen($found)>120){$state['blockedUntil']=time()+86400;break;}
                $state['auto'][$parent]=$found;$names[$parent]=$found;
            } catch(Throwable $e) {$state['blockedUntil']=time()+86400;break;}
        }
        self::saveFolderState((int)$connection['id'],$state);
        $items=$this->normalize($data,$connection,null,$names);
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
        foreach($items as $item)if(!is_array($item)||!isset($item['dataType'])||!array_key_exists('folderId',$item))return null;
        return $items;
    }

    /** Nombre de carpeta por id, orden de las carpetas y carpeta de cada campo, leídos con tolerancia. */
    private static function folderMap(?array $folders):array {
        $names=[];$order=[];$parent=[];
        if(!$folders)return [$names,$order,$parent];
        $list=is_array($folders['folders']??null)?$folders['folders']:[];
        foreach($list as $i=>$folder) {
            if(!is_array($folder)||!is_string($folder['id']??null)||!is_string($folder['name']??null))continue;
            $name=trim($folder['name']);if($name===''||mb_strlen($name)>120)continue;
            $names[$folder['id']]=$name;$order[$folder['id']]=$i;
        }
        foreach(is_array($folders['fields']??null)?$folders['fields']:[] as $field)
            if(is_array($field)&&is_string($field['id']??null)&&is_string($field['parentId']??null))$parent[$field['id']]=$field['parentId'];
        return [$names,$order,$parent];
    }

    /** Ids de carpeta distintos, en el orden en que aparecen los campos (por posición). */
    public static function parentOrder(array $data,array $connection):array {
        $rows=[];
        foreach(is_array($data['customFields']??null)?$data['customFields']:[] as $field) {
            if(!is_array($field)||($field['locationId']??null)!==$connection['location_id']||($field['model']??'contact')!=='contact')continue;
            if(is_string($field['parentId']??null)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$field['parentId']))$rows[]=[is_numeric($field['position']??null)?(int)$field['position']:999,$field['parentId']];
        }
        usort($rows,fn($a,$b)=>$a[0]<=>$b[0]);
        return array_values(array_unique(array_column($rows,1)));
    }
    public static function folderState(int $connection):array {
        try{$raw=query('SELECT value FROM app_state WHERE name=?',['field-folders:'.$connection])->fetchColumn();}catch(PDOException $e){$raw=false;}
        $v=is_string($raw)?json_decode($raw,true):null;
        $clean=fn($m)=>is_array($m)?array_filter($m,fn($n,$k)=>is_string($k)&&is_string($n)&&$n!==''&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$k),ARRAY_FILTER_USE_BOTH):[];
        return ['admin'=>$clean($v['admin']??null),'auto'=>$clean($v['auto']??null),'blockedUntil'=>(int)($v['blockedUntil']??0)];
    }
    public static function saveFolderState(int $connection,array $state):void {
        try{query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['field-folders:'.$connection,json_encode($state,JSON_UNESCAPED_UNICODE)]);}catch(PDOException $e){}
    }

    /** @param array<string,string> $names nombres de carpeta por id, si se conocen */
    public function normalize(array $data,array $connection,?array $folders=null,array $names=[]):array {
        [$folderNames,$folderOrder,$folderParent]=self::folderMap($folders);
        $parents=self::parentOrder($data,$connection);
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
            $parentId=is_string($field['parentId']??null)?$field['parentId']:($folderParent[$id]??null);
            $items[]=[
                'folder'=>$parentId===null?null:($names[$parentId]??($folderNames[$parentId]??'Carpeta '.((int)array_search($parentId,$parents,true)+1))),
                'folderId'=>$parentId,
                'folderOrder'=>$parentId===null?9999:(isset($folderOrder[$parentId])?$folderOrder[$parentId]:(int)array_search($parentId,$parents,true)),
                'id'=>$id,
                'name'=>$name,
                'dataType'=>self::dataType($field['dataType']??null),
                'options'=>array_values(array_unique($options)),
                'placeholder'=>self::short($field['placeholder']??null,190),
                'position'=>is_numeric($field['position']??null)?(int)$field['position']:999,
            ];
        }
        usort($items,fn(array $a,array $b):int=>[$a['folder']===null,$a['folderOrder'],$a['position'],$a['name']]<=>[$b['folder']===null,$b['folderOrder'],$b['position'],$b['name']]);
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

    /** Las carpetas con su nombre actual y algunos campos de muestra, para que el administrador las reconozca y las nombre. */
    public function folders(array $connection):array {
        $items=$this->get($connection);
        $state=self::folderState((int)$connection['id']);
        $out=[];
        foreach($items as $item) {
            $id=$item['folderId']??null;if(!is_string($id))continue;
            $out[$id]??=['id'=>$id,'name'=>$item['folder'],'named'=>isset($state['admin'][$id])||isset($state['auto'][$id]),'byAdmin'=>isset($state['admin'][$id]),'samples'=>[],'count'=>0];
            $out[$id]['count']++;
            if(count($out[$id]['samples'])<4)$out[$id]['samples'][]=$item['name'];
        }
        return array_values($out);
    }
    /** Nombres puestos por el administrador (vacío = quitar el suyo). */
    public static function setAdminNames(array $connection,array $names):void {
        $state=self::folderState((int)$connection['id']);
        foreach($names as $id=>$name) {
            if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))continue;
            $name=is_string($name)?trim(preg_replace('/\s+/u',' ',$name)):'';
            if($name===''||mb_strlen($name)>80)unset($state['admin'][$id]);else $state['admin'][$id]=$name;
        }
        self::saveFolderState((int)$connection['id'],$state);
        query('DELETE FROM contact_field_cache WHERE connection_id=?',[$connection['id']]);
    }
}
