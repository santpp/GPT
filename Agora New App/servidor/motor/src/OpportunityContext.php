<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/PipelineDirectory.php';

/**
 * Opportunity context for one contact inside the conversation. The contact id
 * comes only from a conversation the member is allowed to see, the account's
 * locationId is checked on every record, and an assigned-only member sees only
 * the opportunities assigned to them.
 */
final class OpportunityContext {
    private const STATUSES=['open'=>'Abierta','won'=>'Ganada','lost'=>'Perdida','abandoned'=>'Abandonada'];
    private const LIMIT=20;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway(),private readonly ?PipelineDirectory $pipelines=null) {}

    public function get(array $connection,string $contactId,array $membership,bool $refresh=false):array {
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))throw new InvalidArgumentException('Invalid contact id');
        [$records,$names]=$this->read($connection,$contactId,$refresh);
        $restricted=($membership['capabilities']['assignedDataOnly']??false)===true;
        $assignee=$membership['external_user_id']??null;
        $visible=$restricted?array_values(array_filter($records,fn(array $record):bool=>$record['assignedTo']!==null&&$record['assignedTo']===$assignee)):$records;
        $items=array_map(fn(array $record):array=>$this->present($record,$names,$connection),$visible);
        return [
            'active'=>$items===[]?null:$items[$this->activeIndex($items)],
            'items'=>$items,
            'total'=>count($items),
            'restricted'=>$restricted,
            'canEdit'=>($membership['capabilities']['editOpportunities']??false)===true,
            // A truncated window is reported so the panel never implies a full list.
            'partial'=>count($records)>=self::LIMIT,
            'pipelineNamesAvailable'=>$names!==[],
        ];
    }

    /**
     * One gateway slot covers both reads, because the gateway spaces consecutive
     * slots by a second and the panel needs the names on the first view.
     *
     * @return array{0:list<array<string,mixed>>,1:array<string,mixed>}
     */
    private function read(array $connection,string $contactId,bool $refresh):array {
        $directory=$this->pipelines??new PipelineDirectory();
        $cached=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS fetched FROM opportunity_cache WHERE connection_id=? AND contact_id=?',[$connection['id'],$contactId])->fetch();
        $stored=$cached?json_decode($cached['payload'],true):null;
        $names=$directory->cached($connection);
        try {
        return $this->gateway->execute($connection,function(GhlClient $client)use($connection,$contactId,$directory,$names):array {
            $records=$this->search($client,$connection,$contactId);
            if($names===null) {
                // Pipeline names are optional context: without them no identifier is shown.
                try {$names=$directory->fromClient($client,$connection);}
                catch(Throwable $e){$names=[];}
            }
            return [$records,$names];
        },false);
        } catch(GhlCooldown $e) {
            // Only a paused budget serves what was stored, and only if there is any.
            if(is_array($stored))return [$stored,$names??[]];
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    private function search(GhlClient $client,array $connection,string $contactId):array {
        $data=$client->contactOpportunities($connection['location_id'],$contactId,self::LIMIT);
        $opportunities=$data['opportunities']??null;
        if(!is_array($opportunities)||!array_is_list($opportunities)||count($opportunities)>self::LIMIT)throw new RuntimeException('Invalid opportunities response');
        $records=[];
        foreach($opportunities as $opportunity)
            if(($record=$this->normalize($opportunity,$connection,$contactId))!==null)$records[]=$record;
        query('INSERT INTO opportunity_cache(connection_id,contact_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$connection['id'],$contactId,json_encode($records,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $records;
    }

    private function normalize(mixed $opportunity,array $connection,string $contactId):?array {
        if(!is_array($opportunity))return null;
        $id=self::identifier($opportunity['id']??null);
        if($id===null)return null;
        // A record of another account or another contact never reaches the panel.
        if(isset($opportunity['locationId'])&&$opportunity['locationId']!==$connection['location_id'])return null;
        if(isset($opportunity['contactId'])&&$opportunity['contactId']!==$contactId)return null;
        $value=$opportunity['monetaryValue']??null;
        return [
            'id'=>$id,
            'name'=>self::text($opportunity['name']??null,190),
            'status'=>is_string($opportunity['status']??null)&&isset(self::STATUSES[$opportunity['status']])?$opportunity['status']:null,
            'monetaryValue'=>is_int($value)||is_float($value)?(float)$value:(is_string($value)&&is_numeric($value)?(float)$value:null),
            'pipelineId'=>self::identifier($opportunity['pipelineId']??null),
            'pipelineStageId'=>self::identifier($opportunity['pipelineStageId']??null),
            'assignedTo'=>self::identifier($opportunity['assignedTo']??null),
            'updatedAt'=>self::text($opportunity['updatedAt']??$opportunity['lastStatusChangeAt']??null,80),
            'createdAt'=>self::text($opportunity['createdAt']??null,80),
            'source'=>self::text($opportunity['source']??null,190),
        ];
    }

    private function present(array $record,array $names,array $connection):array {
        $pipeline=$record['pipelineId']!==null?($names[$record['pipelineId']]??null):null;
        $stage=$pipeline&&$record['pipelineStageId']!==null?($pipeline['stages'][$record['pipelineStageId']]??null):null;
        $assigned=$record['assignedTo']!==null
            ?query('SELECT display_name FROM external_users WHERE connection_id=? AND external_user_id=? ORDER BY active DESC LIMIT 1',[$connection['id'],$record['assignedTo']])->fetchColumn()
            :false;
        return $record+[
            'pipelineName'=>$pipeline['name']??null,
            'stageName'=>$stage,
            'statusLabel'=>$record['status']!==null?self::STATUSES[$record['status']]:null,
            'assignedName'=>is_string($assigned)?$assigned:null,
        ];
    }

    /** The active opportunity is the newest open one, or the most recent record. */
    private function activeIndex(array $items):int {
        $best=0;
        foreach($items as $index=>$item) {
            $open=$item['status']==='open';
            $bestOpen=$items[$best]['status']==='open';
            if($open!==$bestOpen){if($open)$best=$index;continue;}
            if(strcmp((string)$item['updatedAt'],(string)$items[$best]['updatedAt'])>0)$best=$index;
        }
        return $best;
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
