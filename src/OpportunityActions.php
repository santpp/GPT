<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/PipelineDirectory.php';
require_once __DIR__.'/ContactActions.php';

/**
 * Creating an opportunity, editing it, moving it to another stage, changing its
 * status and reassigning it. Writing is not idempotent upstream, so every
 * attempt is recorded under the key the browser generates and an identical
 * repeat with an unresolved result is refused instead of opening a second one.
 *
 * Every write names a pipeline and a stage the account really publishes, and a
 * responsible the account really has, so the browser cannot invent either.
 */
final class OpportunityActions {
    public const STATUSES=['open','won','lost','abandoned'];

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway(),private readonly ?PipelineDirectory $pipelines=null) {}

    /** Opens an opportunity for a contact the member is already allowed to see. */
    public function create(array $connection,string $contactId,array $membership,array $input):array {
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))throw new ActionProblem('invalid_contact',422,'Actualiza la ficha antes de continuar.');
        $name=self::text($input['name']??null,190);
        if($name===null)throw new ActionProblem('invalid_opportunity_name',422,'Escribe un nombre de hasta 190 caracteres para la oportunidad.');
        $names=$this->directory($connection);
        [$pipeline,$stage]=$this->place($names,$input['pipelineId']??null,$input['pipelineStageId']??null);
        $payload=[
            'locationId'=>$connection['location_id'],
            'contactId'=>$contactId,
            'pipelineId'=>$pipeline,
            'pipelineStageId'=>$stage,
            'name'=>$name,
            'status'=>self::state($input['status']??'open'),
        ];
        if(array_key_exists('monetaryValue',$input)) {
            $value=self::money($input['monetaryValue']);
            if($value===null)throw new ActionProblem('invalid_opportunity_value',422,'El valor debe ser un número de 0 a 999.999.999.');
            $payload['monetaryValue']=$value;
        }
        $assignee=$this->assignee($connection,$input,$membership,true);
        if($assignee!==null&&$assignee!=='')$payload['assignedTo']=$assignee;
        if(!isset($payload['assignedTo'])&&$payload['status']==='won'&&is_string($membership['self_id']??null))$payload['assignedTo']=$membership['self_id'];
        $result=$this->run($connection,$contactId,$membership,$input,'create',$payload,
            fn(GhlClient $client):array=>$client->createOpportunity($payload),$contactId);
        if(is_string($result['id']??null))self::remember($connection,$result['id'],$payload['status'],(float)($payload['monetaryValue']??0),$payload['assignedTo']??null,true);
        return $result;
    }

    /**
     * Edits an opportunity: name, pipeline, stage, value, responsible, status.
     * The record is read first, so an identifier from another account simply
     * does not exist for this key and a member limited to their own data cannot
     * touch an opportunity that is not theirs.
     */
    public function update(array $connection,string $opportunityId,array $membership,array $input):array {
        $current=$this->record($connection,$opportunityId,$membership);
        $payload=[];
        if(array_key_exists('name',$input)) {
            $name=self::text($input['name'],190);
            if($name===null)throw new ActionProblem('invalid_opportunity_name',422,'El nombre de la oportunidad no puede quedar vacío.');
            if($name!==($current['name']??null))$payload['name']=$name;
        }
        if(array_key_exists('pipelineId',$input)||array_key_exists('pipelineStageId',$input)) {
            $names=$this->directory($connection);
            [$pipeline,$stage]=$this->place($names,$input['pipelineId']??($current['pipelineId']??null),$input['pipelineStageId']??null);
            if($pipeline!==($current['pipelineId']??null))$payload['pipelineId']=$pipeline;
            if($stage!==($current['pipelineStageId']??null))$payload['pipelineStageId']=$stage;
        }
        if(array_key_exists('monetaryValue',$input)) {
            $value=self::money($input['monetaryValue']);
            if($value===null)throw new ActionProblem('invalid_opportunity_value',422,'El valor debe ser un número de 0 a 999.999.999.');
            if($value!==(float)($current['monetaryValue']??0))$payload['monetaryValue']=$value;
        }
        if(array_key_exists('assignedTo',$input)) {
            $assignee=$this->assignee($connection,$input,$membership,false);
            if($assignee!==($current['assignedTo']??''))$payload['assignedTo']=$assignee;
        }
        // «¿Venta ganada?»: si nadie la tenía asignada, pasa a quien la cierra,
        // para que cuente en sus ventas del mes.
        if(($input['claimIfUnassigned']??false)===true&&!array_key_exists('assignedTo',$payload)&&empty($current['assignedTo'])&&is_string($membership['self_id']??null))
            $payload['assignedTo']=$membership['self_id'];
        if(array_key_exists('status',$input)) {
            $status=self::state($input['status']);
            if($status!==($current['status']??null))$payload['status']=$status;
        }
        if($payload===[])throw new ActionProblem('nothing_to_change',422,'No has cambiado nada en la oportunidad.');
        $result=$this->run($connection,$opportunityId,$membership,$input,'update',$payload,
            fn(GhlClient $client):array=>$client->updateOpportunity($opportunityId,$payload),$current['contactId']??null);
        self::remember($connection,$opportunityId,$payload['status']??($current['status']??null),
            (float)($payload['monetaryValue']??($current['monetaryValue']??0)),$payload['assignedTo']??($current['assignedTo']??null),isset($payload['status']));
        return $result;
    }

    /**
     * Anota una venta ganada desde Ágora con su fecha, o la retira si deja de
     * estar ganada. Un cambio de valor o de responsable sin cambio de estado
     * actualiza la anotación sin mover su fecha.
     */
    private static function remember(array $connection,string $opportunityId,?string $status,float $value,?string $assignee,bool $statusChanged):void {
        try {
            if($status==='won') {
                if($statusChanged)query('INSERT INTO won_sales(connection_id,opportunity_id,assigned_to,value,won_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE assigned_to=VALUES(assigned_to),value=VALUES(value),won_at=CURRENT_TIMESTAMP',[$connection['id'],$opportunityId,$assignee,$value]);
                else query('UPDATE won_sales SET assigned_to=?,value=? WHERE connection_id=? AND opportunity_id=?',[$assignee,$value,$connection['id'],$opportunityId]);
            } elseif($statusChanged) query('DELETE FROM won_sales WHERE connection_id=? AND opportunity_id=?',[$connection['id'],$opportunityId]);
        } catch(PDOException $e) {}
    }

    /** Changes only the status, through the endpoint published for it. */
    public function status(array $connection,string $opportunityId,array $membership,array $input):array {
        $current=$this->record($connection,$opportunityId,$membership);
        $status=self::state($input['status']??null);
        if($status===($current['status']??null))throw new ActionProblem('nothing_to_change',422,'La oportunidad ya está en ese estado.');
        $payload=['status'=>$status];
        $result=$this->run($connection,$opportunityId,$membership,$input,'status',$payload,
            fn(GhlClient $client):array=>$client->updateOpportunityStatus($opportunityId,$payload),$current['contactId']??null);
        self::remember($connection,$opportunityId,$status,(float)($current['monetaryValue']??0),$current['assignedTo']??null,true);
        return $result;
    }

    /** The opportunity as the account has it, checked against this connection. */
    private function record(array $connection,string $opportunityId,array $membership):array {
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$opportunityId))throw new ActionProblem('invalid_opportunity',422,'Selecciona una oportunidad válida.');
        try {$response=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->opportunity($opportunityId),false);}
        catch(GhlCooldown $e){throw new ActionProblem('action_paused',429,'Acción pausada para respetar la cuota. Todavía no se ha aplicado.',$e->retryAfter);}
        catch(GhlApiException $e){throw new ActionProblem('unknown_opportunity',404,'Esa oportunidad no está disponible en esta cuenta.');}
        $record=$response['opportunity']??null;
        if(!is_array($record)||!is_string($record['id']??null)||$record['id']!==$opportunityId)throw new ActionProblem('invalid_opportunity_response',424,'La oportunidad recibida no tiene el formato esperado.');
        if(isset($record['locationId'])&&$record['locationId']!==$connection['location_id'])throw new ActionProblem('unknown_opportunity',404,'Esa oportunidad no está disponible en esta cuenta.');
        if(($membership['capabilities']['assignedDataOnly']??false)===true&&($record['assignedTo']??null)!==($membership['external_user_id']??null))
            throw new ActionProblem('opportunity_not_assigned',403,'Esa oportunidad no está asignada a tu usuario.');
        return $record;
    }

    private function directory(array $connection):array {
        $directory=$this->pipelines??new PipelineDirectory();
        try {$names=$directory->get($connection);}
        catch(GhlCooldown $e){throw new ActionProblem('action_paused',429,'Acción pausada para respetar la cuota. Todavía no se ha aplicado.',$e->retryAfter);}
        catch(Throwable $e){throw new ActionProblem('pipelines_unavailable',424,'No se pudieron leer los embudos de la cuenta. Inténtalo de nuevo.');}
        if($names===[])throw new ActionProblem('pipelines_unavailable',424,'Esta cuenta no tiene embudos disponibles.');
        return $names;
    }

    /** @return array{0:string,1:string} a pipeline and a stage the account publishes */
    private function place(array $names,mixed $pipelineId,mixed $stageId):array {
        if(!is_string($pipelineId)||!isset($names[$pipelineId]))throw new ActionProblem('unknown_pipeline',422,'Elige un embudo de los que tiene la cuenta.');
        $stages=$names[$pipelineId]['stages']??[];
        if($stages===[])throw new ActionProblem('unknown_stage',422,'Ese embudo no tiene etapas disponibles.');
        if($stageId===null||$stageId==='')return [$pipelineId,(string)array_key_first($stages)];
        if(!is_string($stageId)||!isset($stages[$stageId]))throw new ActionProblem('unknown_stage',422,'Elige una etapa de las que tiene ese embudo.');
        return [$pipelineId,$stageId];
    }

    /** Only a member imported from this very account may be made responsible. */
    private function assignee(array $connection,array $input,array $membership,bool $creating):?string {
        $assignee=$input['assignedTo']??null;
        if($assignee===null||$assignee==='')
            // A member limited to their own data would lose sight of what they open.
            return ($membership['capabilities']['assignedDataOnly']??false)===true&&$creating?(string)($membership['external_user_id']??''):'';
        if(!is_string($assignee)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$assignee)
            ||!query('SELECT 1 FROM external_users WHERE connection_id=? AND external_user_id=? AND active=TRUE LIMIT 1',[$connection['id'],$assignee])->fetchColumn())
            throw new ActionProblem('unknown_assignee',422,'Ese responsable no pertenece al equipo importado de esta cuenta.');
        if(($membership['capabilities']['assignedDataOnly']??false)===true&&$assignee!==($membership['external_user_id']??null))
            throw new ActionProblem('assignee_forbidden',403,'Tu usuario sólo puede asignarse a sí mismo.');
        return $assignee;
    }

    /**
     * Shared guard: permission, one attempt per key, no identical repeat while a
     * previous one is unresolved, and the stored context dropped on success.
     */
    private function run(array $connection,string $subject,array $membership,array $input,string $action,array $payload,Closure $operation,?string $contactId):array {
        if(($membership['capabilities']['editOpportunities']??false)!==true)throw new ActionProblem('action_forbidden',403,'Tu usuario puede consultar oportunidades, pero no modificarlas.');
        if($connection['status']!=='connected')throw new ActionProblem('connection_unverified',422,'Comprueba la conexión antes de continuar.');
        $request=$input['requestId']??null;
        if(!is_string($request)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$request))throw new ActionProblem('invalid_request_key',422,'Vuelve a cargar la pantalla antes de repetir la acción.');
        $id=(int)$connection['id'];
        $hash=hash('sha256',json_encode([$action,$subject,$payload],JSON_THROW_ON_ERROR));
        $lock='agora-opportunity:'.$id.':'.substr(hash('sha256',$subject),0,32);
        if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)throw new ActionProblem('action_busy',409,'Hay una acción en curso sobre esta oportunidad. Espera antes de repetir.',2);
        try {
            $attempt=query('SELECT * FROM opportunity_action_attempts WHERE connection_id=? AND request_id=?',[$id,$request])->fetch();
            if($attempt) {
                if(!hash_equals($attempt['request_hash'],$hash))throw new ActionProblem('request_conflict',409,'Este identificador corresponde a otra acción.');
                if($attempt['state']==='accepted')return ['state'=>'accepted','id'=>$attempt['result_id'],'requestId'=>$request];
                if(in_array($attempt['state'],['running','unknown'],true))throw $this->unknown();
                throw new ActionProblem('action_rejected',422,'La acción anterior fue rechazada. Revisa los datos y los permisos antes de repetirla.');
            }
            if(query("SELECT request_id FROM opportunity_action_attempts WHERE connection_id=? AND subject_id=? AND request_hash=? AND state IN ('running','unknown') LIMIT 1",[$id,$subject,$hash])->fetchColumn())throw $this->unknown();
            query("INSERT INTO opportunity_action_attempts(connection_id,request_id,subject_id,action,request_hash,state) VALUES(?,?,?,?,?,'running')",[$id,$request,$subject,$action,$hash]);
            try {
                $response=$this->gateway->execute($connection,$operation,false);
                $result=self::identifier($response['opportunity']['id']??null)??($action==='create'?null:$subject);
                query("UPDATE opportunity_action_attempts SET state='accepted',result_id=?,error_code=NULL WHERE connection_id=? AND request_id=?",[$result,$id,$request]);
                // The panel and the board must show the new state, not what was stored.
                if($contactId!==null&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))query('DELETE FROM opportunity_cache WHERE connection_id=? AND contact_id=?',[$id,$contactId]);
                query('DELETE FROM directory_cache WHERE connection_id=? AND cache_key LIKE ?',[$id,'board:%']);
                return ['state'=>'accepted','id'=>$result,'requestId'=>$request];
            } catch(GhlCooldown $e) {
                query('DELETE FROM opportunity_action_attempts WHERE connection_id=? AND request_id=?',[$id,$request]);
                throw new ActionProblem('action_paused',429,'Acción pausada para respetar la cuota. Todavía no se ha aplicado.',$e->retryAfter);
            } catch(GhlApiException $e) {
                if(in_array($e->status,[400,401,403,404,422],true)) {
                    query("UPDATE opportunity_action_attempts SET state='rejected',error_code=? WHERE connection_id=? AND request_id=?",['http_'.$e->status,$id,$request]);
                    throw new ActionProblem('action_rejected',422,in_array($e->status,[401,403],true)
                        ?'La cuenta rechazó la acción por permisos. Revisa el permiso de escritura de oportunidades.'
                        :'La cuenta rechazó la acción (código '.$e->status.'). Revisa los datos antes de repetirla.');
                }
                $this->markUnknown($id,$request);throw $this->unknown();
            } catch(Throwable $e) {$this->markUnknown($id,$request);throw $this->unknown();}
        } finally {query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    private function markUnknown(int $id,string $request):void {
        query("UPDATE opportunity_action_attempts SET state='unknown',error_code='unconfirmed' WHERE connection_id=? AND request_id=?",[$id,$request]);
    }
    private function unknown():ActionProblem {
        return new ActionProblem('action_unconfirmed',409,'No se pudo confirmar la acción. Actualiza la pantalla antes de repetirla: repetir lo mismo está bloqueado para evitar duplicados.');
    }

    private static function state(mixed $value):string {
        if(!is_string($value)||!in_array($value,self::STATUSES,true))throw new ActionProblem('invalid_status',422,'El estado debe ser abierta, ganada, perdida o abandonada.');
        return $value;
    }
    private static function money(mixed $value):?float {
        if($value===null||$value==='')return 0.0;
        if(is_bool($value))return null;
        if(is_string($value))$value=str_replace(',','.',trim($value));
        if(!is_numeric($value))return null;
        $value=(float)$value;
        return $value>=0&&$value<=999999999?round($value,2):null;
    }
    private static function identifier(mixed $value):?string {
        return is_string($value)&&preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$value)?$value:null;
    }
    private static function text(mixed $value,int $max):?string {
        if(!is_string($value))return null;
        $value=trim($value);
        return $value!==''&&mb_strlen($value)<=$max?$value:null;
    }
}
