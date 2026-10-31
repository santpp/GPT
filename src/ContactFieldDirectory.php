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
        // Las carpetas son un extra: si no se pueden leer, los campos se muestran igual, sin agrupar.
        $folders=null;$status='ok';
        try {$folders=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->contactFolders($connection['location_id']),false);}
        catch(GhlCooldown $e){$status='paused';}
        catch(GhlApiException $e){$status='http_'.$e->status;}
        catch(Throwable $e){$status='unreadable';}
        $items=$this->normalize($data,$connection,is_array($folders)?$folders:null);
        foreach($items as &$item)$item['folderStatus']=$status;
        unset($item);
        $grouped=count(array_filter($items,fn($i)=>$i['folder']!==null));
        try {query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['fields-diag:'.$connection['id'],json_encode(['at'=>gmdate('c'),'fields'=>count($items),'inFolders'=>$grouped,'folders'=>is_array($folders)&&is_array($folders['folders']??null)?count($folders['folders']):0,'folderRead'=>$status,'parentInFields'=>count(array_filter($data['customFields']??[],fn($f)=>is_array($f)&&isset($f['parentId'])))])]);}catch(PDOException $e){}
        query('INSERT INTO contact_field_cache(connection_id,payload) VALUES(?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $items;
    }

    /** The stored directory while it is fresh, for a caller that must not spend a request. */
    public function cached(array $connection):?array {
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM contact_field_cache WHERE connection_id=?',[$connection['id']])->fetch();
        if(!$row||time()-(int)$row['fetched']>=3600)return null;
        $items=json_decode($row['payload'],true);
        if(!is_array($items))return null;
        // Sin ninguna carpeta se reintenta pronto: la lectura de carpetas pudo fallar sólo esa vez.
        if(time()-(int)$row['fetched']>=300&&!array_filter($items,fn($i)=>is_array($i)&&($i['folder']??null)!==null))return null;
        // A directory stored before field types existed is read as stale, so an
        // update refetches it instead of offering every field as plain text.
        foreach($items as $item)if(!is_array($item)||!isset($item['dataType'])||!array_key_exists('folder',$item))return null;
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

    public function normalize(array $data,array $connection,?array $folders=null):array {
        [$folderNames,$folderOrder,$folderParent]=self::folderMap($folders);
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
                'folder'=>$parentId!==null&&isset($folderNames[$parentId])?$folderNames[$parentId]:null,
                'folderOrder'=>$parentId!==null&&isset($folderOrder[$parentId])?$folderOrder[$parentId]:9999,
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

    /** Qué devuelve la cuenta para los campos y sus carpetas: estados, conteos y nombres de claves, nunca valores de contactos. Para el administrador. */
    public function diagnose(array $connection):array {
        $run=function(Closure $call)use($connection):array{
            try{return ['status'=>200,'data'=>$this->gateway->execute($connection,$call,false)];}
            catch(GhlCooldown $e){return ['status'=>429,'data'=>null];}
            catch(GhlApiException $e){return ['status'=>$e->status,'data'=>null,'detail'=>$e->detail];}
            catch(Throwable $e){return ['status'=>0,'data'=>null];}
        };
        $loc=$connection['location_id'];
        $fields=$run(fn(GhlClient $c)=>$c->contactFields($loc));
        $folders=$run(fn(GhlClient $c)=>$c->contactFolders($loc));
        $list=is_array($fields['data']['customFields']??null)?$fields['data']['customFields']:[];
        $first=$list[0]??null;
        $one=['status'=>null];
        if(is_array($first)&&is_string($first['id']??null)){
            $r=$run(fn(GhlClient $c)=>$c->customFieldById($first['id']));
            $f=is_array($r['data']['field']??null)?$r['data']['field']:(is_array($r['data'])?$r['data']:[]);
            $one=['status'=>$r['status'],'keys'=>array_keys($f),'hasParent'=>isset($f['parentId'])];
        }
        $fd=is_array($folders['data'])?$folders['data']:[];
        $fl=is_array($fd['folders']??null)?$fd['folders']:[];
        $ff=is_array($fd['fields']??null)?$fd['fields']:[];
        return [
            'fields'=>['status'=>$fields['status'],'count'=>count($list),'withParent'=>count(array_filter($list,fn($f)=>is_array($f)&&isset($f['parentId']))),'keys'=>is_array($first)?array_keys($first):[]],
            'folders'=>['status'=>$folders['status'],'detail'=>$folders['detail']??null,'topKeys'=>array_keys($fd),'count'=>count($fl),'names'=>array_values(array_filter(array_map(fn($f)=>is_array($f)&&is_string($f['name']??null)?mb_substr($f['name'],0,80):null,array_slice($fl,0,50)))),'itemKeys'=>is_array($fl[0]??null)?array_keys($fl[0]):[],'fieldCount'=>count($ff),'fieldsWithParent'=>count(array_filter($ff,fn($f)=>is_array($f)&&isset($f['parentId'])))],
            'single'=>$one,
        ];
    }
}
