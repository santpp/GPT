<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/PipelineDirectory.php';

/**
 * The opportunities board of one account: the open opportunities of a pipeline
 * grouped by its real stages. Pipelines and opportunities share one gateway
 * slot so the board arrives complete on the first view.
 */
final class OpportunityBoard {
    private const TTL=60;
    private const LIMIT=100;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway(),private readonly ?PipelineDirectory $pipelines=null) {}

    public function get(array $connection,?string $pipelineId,array $membership,bool $refresh=false):array {
        $pipelineId=$pipelineId!==null&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$pipelineId)?$pipelineId:null;
        [$names,$records]=$this->read($connection,$pipelineId,$refresh);
        if($names===[])return ['pipelines'=>[],'pipelineId'=>null,'stages'=>[],'total'=>0,'restricted'=>false,'partial'=>false,'unavailable'=>'pipelines'];
        $selected=$pipelineId!==null&&isset($names[$pipelineId])?$pipelineId:array_key_first($names);
        $restricted=($membership['capabilities']['assignedDataOnly']??false)===true;
        $assignee=$membership['external_user_id']??null;
        $visible=array_values(array_filter($records,fn(array $row):bool=>
            $row['pipelineId']===$selected&&(!$restricted||($row['assignedTo']!==null&&$row['assignedTo']===$assignee))));
        $stages=[];
        foreach($names[$selected]['stages'] as $stageId=>$stageName)$stages[]=['id'=>$stageId,'name'=>$stageName,'items'=>[],'total'=>0,'value'=>0.0];
        $index=array_flip(array_column($stages,'id'));
        $loose=['id'=>null,'name'=>'Sin etapa','items'=>[],'total'=>0,'value'=>0.0];
        foreach($visible as $row) {
            $card=$row+['assignedName'=>$this->member($connection,$row['assignedTo'])];
            $position=$row['pipelineStageId']!==null?($index[$row['pipelineStageId']]??null):null;
            if($position===null){$loose['items'][]=$card;$loose['total']++;$loose['value']+=$row['monetaryValue']??0;continue;}
            $stages[$position]['items'][]=$card;$stages[$position]['total']++;$stages[$position]['value']+=$row['monetaryValue']??0;
        }
        if($loose['total']>0)$stages[]=$loose;
        return [
            'pipelines'=>array_map(fn(string $id):array=>['id'=>$id,'name'=>$names[$id]['name']],array_keys($names)),
            'pipelineId'=>$selected,
            'stages'=>$stages,
            'total'=>count($visible),
            'restricted'=>$restricted,
            'partial'=>count($records)>=self::LIMIT,
        ];
    }

    /** @return array{0:array<string,mixed>,1:list<array<string,mixed>>} */
    private function read(array $connection,?string $pipelineId,bool $refresh):array {
        $directory=$this->pipelines??new PipelineDirectory();
        $key='board:'.substr(hash('sha256',(string)$pipelineId),0,40);
        $names=$directory->cached($connection);
        $records=null;
        if(!$refresh) {
            $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM directory_cache WHERE connection_id=? AND cache_key=?',[$connection['id'],$key])->fetch();
            if($row&&time()-(int)$row['fetched']<self::TTL){$value=json_decode($row['payload'],true);if(is_array($value))$records=$value;}
        }
        if($records!==null&&$names!==null)return [$names,$records];
        return $this->gateway->execute($connection,function(GhlClient $client)use($connection,$directory,$names,$records,$pipelineId,$key):array {
            if($names===null) {
                try {$names=$directory->fromClient($client,$connection);}
                catch(Throwable $e){$names=[];}
            }
            if($records===null) {
                $records=$this->fetch($client,$connection,$pipelineId);
                query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],$key,json_encode($records,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            }
            return [$names,$records];
        },false);
    }

    private function fetch(GhlClient $client,array $connection,?string $pipelineId):array {
        $data=$client->locationOpportunities($connection['location_id'],$pipelineId,self::LIMIT);
        $opportunities=$data['opportunities']??null;
        if(!is_array($opportunities)||!array_is_list($opportunities))throw new RuntimeException('Invalid opportunities response');
        $items=[];
        foreach(array_slice($opportunities,0,self::LIMIT) as $opportunity) {
            if(!is_array($opportunity))continue;
            $id=self::identifier($opportunity['id']??null);
            if($id===null)continue;
            if(isset($opportunity['locationId'])&&$opportunity['locationId']!==$connection['location_id'])continue;
            $value=$opportunity['monetaryValue']??null;
            $items[]=[
                'id'=>$id,
                'name'=>self::text($opportunity['name']??null,190)??'Oportunidad sin nombre',
                'contactId'=>self::identifier($opportunity['contactId']??null),
                'contactName'=>self::text($opportunity['contact']['name']??null,190),
                'monetaryValue'=>is_int($value)||is_float($value)?(float)$value:(is_string($value)&&is_numeric($value)?(float)$value:null),
                'pipelineId'=>self::identifier($opportunity['pipelineId']??null),
                'pipelineStageId'=>self::identifier($opportunity['pipelineStageId']??null),
                'assignedTo'=>self::identifier($opportunity['assignedTo']??null),
                'updatedAt'=>self::text($opportunity['updatedAt']??null,80),
            ];
        }
        return $items;
    }

    private function member(array $connection,?string $externalId):?string {
        if($externalId===null)return null;
        $name=query('SELECT display_name FROM external_users WHERE connection_id=? AND external_user_id=? ORDER BY active DESC LIMIT 1',[$connection['id'],$externalId])->fetchColumn();
        return is_string($name)?$name:null;
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
