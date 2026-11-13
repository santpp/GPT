<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

/**
 * Real pipeline and stage names for one account. The published stage schema
 * carries no item definition, so every entry is normalized defensively and an
 * unnamed stage is simply left out instead of being invented.
 */
final class PipelineDirectory {
    private const TTL=3600;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function get(array $connection):array {
        return $this->cached($connection) ?? $this->gateway->execute($connection,fn(GhlClient $client)=>$this->fromClient($client,$connection),false);
    }

    /** The stored directory while it is fresh, so a caller can skip the request. */
    public function cached(array $connection):?array {
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM pipeline_cache WHERE connection_id=?',[$connection['id']])->fetch();
        if(!$row||time()-(int)$row['fetched']>=self::TTL)return null;
        $items=json_decode($row['payload'],true);
        return is_array($items)?$items:null;
    }

    /** Reads and stores the directory through a client the caller already holds. */
    public function fromClient(GhlClient $client,array $connection):array {
        $data=$client->pipelines($connection['location_id']);
        $pipelines=$data['pipelines']??null;
        if(!is_array($pipelines)||!array_is_list($pipelines)||count($pipelines)>200)throw new RuntimeException('Invalid pipelines response');
        $items=[];
        foreach($pipelines as $pipeline) {
            if(!is_array($pipeline))continue;
            $id=self::identifier($pipeline['id']??null);
            $name=self::text($pipeline['name']??null,190);
            if($id===null||$name===null)continue;
            if(isset($pipeline['locationId'])&&$pipeline['locationId']!==$connection['location_id'])continue;
            $stages=[];
            if(is_array($pipeline['stages']??null))foreach(array_slice($pipeline['stages'],0,200) as $stage) {
                if(!is_array($stage))continue;
                $stageId=self::identifier($stage['id']??null);
                $stageName=self::text($stage['name']??null,190);
                if($stageId!==null&&$stageName!==null)$stages[$stageId]=$stageName;
            }
            $items[$id]=['name'=>$name,'stages'=>$stages];
        }
        query('INSERT INTO pipeline_cache(connection_id,payload) VALUES(?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $items;
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
