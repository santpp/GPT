<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/ContactActivity.php';

final class ActionProblem extends RuntimeException {
    public function __construct(public readonly string $reason,public readonly int $httpStatus,string $message,public readonly int $retryAfter=0) {
        parent::__construct($message);
    }
}

/**
 * Quick actions on the contact of an open conversation: create a note, create a
 * task, complete or reopen a task. Creating is not idempotent upstream, so each
 * attempt is recorded under a browser-supplied key and identical content is
 * never sent twice, the same way message sending works.
 */
final class ContactActions {
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function note(array $connection,string $contactId,array $membership,array $input):array {
        $body=self::text($input['body']??null,5000);
        if($body===null)throw new ActionProblem('invalid_note',422,'Escribe una nota de hasta 5.000 caracteres.');
        return $this->run($connection,$contactId,$membership,$input,'note',['body'=>$body],
            fn(GhlClient $client):array=>$client->createContactNote($contactId,['body'=>$body]),'note');
    }

    public function task(array $connection,string $contactId,array $membership,array $input):array {
        $title=self::text($input['title']??null,190);
        if($title===null)throw new ActionProblem('invalid_task',422,'Escribe un título de hasta 190 caracteres.');
        $due=self::due($input['dueDate']??null);
        if($due===null)throw new ActionProblem('invalid_due_date',422,'Indica una fecha de vencimiento válida.');
        $note=self::text($input['body']??null,2000);
        // The published contract marks title, dueDate and completed as required.
        $payload=['title'=>$title,'dueDate'=>$due,'completed'=>false]+($note!==null?['body'=>$note]:[]);
        return $this->run($connection,$contactId,$membership,$input,'task',$payload,
            fn(GhlClient $client):array=>$client->createContactTask($contactId,$payload),'task');
    }

    public function completeTask(array $connection,string $contactId,array $membership,array $input):array {
        $task=$input['taskId']??null;
        if(!is_string($task)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$task))throw new ActionProblem('invalid_task_id',422,'Selecciona una tarea válida.');
        $completed=$input['completed']??null;
        if(!is_bool($completed))throw new ActionProblem('invalid_task_state',422,'Indica si la tarea queda completada.');
        return $this->run($connection,$contactId,$membership,$input,'task-state',['taskId'=>$task,'completed'=>$completed],
            fn(GhlClient $client):array=>$client->completeContactTask($contactId,$task,$completed),'task');
    }

    /** Fields the panel may edit, each written only when the person changed it. */
    private const FIELDS=[
        'firstName'=>90,'lastName'=>90,'name'=>190,'email'=>190,'phone'=>60,'companyName'=>190,
        'address1'=>190,'city'=>120,'state'=>120,'postalCode'=>40,'country'=>80,'website'=>250,'source'=>190,'timezone'=>80,
    ];

    /**
     * Edits the contact record and its tags. Tags travel through the dedicated
     * add and remove endpoints, never through the update field, whose own
     * documentation says it overwrites every tag the contact has.
     */
    public function contact(array $connection,string $contactId,array $membership,array $input):array {
        $fields=$input['fields']??[];
        if(!is_array($fields)||($fields!==[]&&array_is_list($fields)))throw new ActionProblem('invalid_contact_fields',422,'Revisa los datos de la ficha.');
        $payload=[];
        foreach(self::FIELDS as $field=>$max) {
            if(!array_key_exists($field,$fields))continue;
            $value=$fields[$field];
            if($value===null||(is_string($value)&&trim($value)===''))  {$payload[$field]='';continue;}
            if(!is_string($value)||mb_strlen(trim($value))>$max)throw new ActionProblem('invalid_contact_fields',422,'Revisa los datos de la ficha: hay un campo demasiado largo o con un formato inesperado.');
            $payload[$field]=trim($value);
        }
        if(($payload['email']??'')!==''&&!filter_var($payload['email'],FILTER_VALIDATE_EMAIL))throw new ActionProblem('invalid_contact_email',422,'El correo no tiene un formato válido.');
        if(($payload['phone']??'')!==''&&!preg_match('/^\+?[0-9 ()\-.]{5,60}$/D',$payload['phone']))throw new ActionProblem('invalid_contact_phone',422,'El teléfono sólo puede tener números y los signos + ( ) - . y espacios.');
        $custom=[];
        foreach(is_array($fields['customFields']??null)?array_slice($fields['customFields'],0,100):[] as $field) {
            if(!is_array($field)||!is_string($field['id']??null)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$field['id']))continue;
            $value=$field['value']??'';
            if(!is_scalar($value)||mb_strlen((string)$value)>1000)throw new ActionProblem('invalid_contact_fields',422,'Un campo personalizado supera el tamaño admitido.');
            // The account reads custom values as "value" but writes them as "field_value".
            $custom[]=['id'=>$field['id'],'field_value'=>(string)$value];
        }
        if($custom)$payload['customFields']=$custom;
        $add=self::tagList($input['addTags']??[]);
        $remove=self::tagList($input['removeTags']??[]);
        if($payload===[]&&$add===[]&&$remove===[])throw new ActionProblem('nothing_to_change',422,'No has cambiado nada en la ficha.');
        return $this->run($connection,$contactId,$membership,$input,'contact',['fields'=>$payload,'add'=>$add,'remove'=>$remove],
            function(GhlClient $client)use($contactId,$payload,$add,$remove):array {
                $response=$payload!==[]?$client->updateContact($contactId,$payload):['contact'=>['id'=>$contactId]];
                if($add)$client->addContactTags($contactId,$add);
                if($remove)$client->removeContactTags($contactId,$remove);
                return $response;
            },'contact');
    }

    /** @return list<string> */
    private static function tagList(mixed $value):array {
        if(!is_array($value)||!array_is_list($value))return [];
        $tags=[];
        foreach(array_slice($value,0,25) as $tag) {
            if(!is_string($tag))continue;
            $tag=trim($tag);
            if($tag!==''&&mb_strlen($tag)<=100)$tags[]=$tag;
        }
        return array_values(array_unique($tags));
    }

    /**
     * Shared guard: permission, one attempt per key, no identical repeat while a
     * previous one is unresolved, and the stored activity invalidated on success.
     */
    private function run(array $connection,string $contactId,array $membership,array $input,string $action,array $payload,Closure $operation,string $resultKey):array {
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))throw new ActionProblem('invalid_contact',422,'Actualiza la conversación antes de continuar.');
        if(($membership['capabilities']['editContacts']??false)!==true)throw new ActionProblem('action_forbidden',403,'Tu usuario puede consultar, pero no modificar la ficha del contacto.');
        if($connection['status']!=='connected')throw new ActionProblem('connection_unverified',422,'Comprueba la conexión antes de continuar.');
        $request=$input['requestId']??null;
        if(!is_string($request)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$request))throw new ActionProblem('invalid_request_key',422,'Vuelve a cargar la conversación antes de repetir la acción.');
        $id=(int)$connection['id'];
        $hash=hash('sha256',json_encode([$action,$contactId,$payload],JSON_THROW_ON_ERROR));
        $lock='agora-action:'.$id.':'.substr(hash('sha256',$contactId),0,32);
        if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)throw new ActionProblem('action_busy',409,'Hay una acción en curso sobre este contacto. Espera antes de repetir.',2);
        try {
            $attempt=query('SELECT * FROM contact_action_attempts WHERE connection_id=? AND request_id=?',[$id,$request])->fetch();
            if($attempt) {
                if(!hash_equals($attempt['request_hash'],$hash))throw new ActionProblem('request_conflict',409,'Este identificador corresponde a otra acción.');
                if($attempt['state']==='accepted')return ['state'=>'accepted','id'=>$attempt['result_id'],'requestId'=>$request];
                if(in_array($attempt['state'],['running','unknown'],true))throw $this->unknown();
                if($attempt['state']==='rejected')throw new ActionProblem('action_rejected',422,'La acción anterior fue rechazada. Revisa los datos y los permisos antes de repetirla.');
            }
            // A new browser key must not repeat identical content with an uncertain result.
            if(query("SELECT request_id FROM contact_action_attempts WHERE connection_id=? AND contact_id=? AND request_hash=? AND state IN ('running','unknown') LIMIT 1",[$id,$contactId,$hash])->fetchColumn())throw $this->unknown();
            query("INSERT INTO contact_action_attempts(connection_id,request_id,contact_id,action,request_hash,state) VALUES(?,?,?,?,?,'running')",[$id,$request,$contactId,$action,$hash]);
            try {
                $response=$this->gateway->execute($connection,$operation,false);
                $result=self::identifier($response[$resultKey]['id']??null);
                query("UPDATE contact_action_attempts SET state='accepted',result_id=?,error_code=NULL WHERE connection_id=? AND request_id=?",[$result,$id,$request]);
                // The panel must show the new state, not the window cached a moment ago.
                ContactActivity::forget($id,$contactId);
                if($action==='contact')query('DELETE FROM contact_profile_cache WHERE connection_id=? AND contact_id=?',[$id,$contactId]);
                return ['state'=>'accepted','id'=>$result,'requestId'=>$request];
            } catch(GhlCooldown $e) {
                query('DELETE FROM contact_action_attempts WHERE connection_id=? AND request_id=?',[$id,$request]);
                throw new ActionProblem('action_paused',429,'Acción pausada para respetar la cuota. Todavía no se ha aplicado.',$e->retryAfter);
            } catch(GhlApiException $e) {
                if(in_array($e->status,[400,401,403,404,422],true)) {
                    query("UPDATE contact_action_attempts SET state='rejected',error_code=? WHERE connection_id=? AND request_id=?",['http_'.$e->status,$id,$request]);
                    throw new ActionProblem('action_rejected',422,in_array($e->status,[401,403],true)
                        ?'La cuenta rechazó la acción por permisos. Revisa el permiso de escritura de contactos.'
                        :'La cuenta rechazó la acción (código '.$e->status.'). Revisa los datos antes de repetirla.');
                }
                $this->markUnknown($id,$request);throw $this->unknown();
            } catch(Throwable $e) {$this->markUnknown($id,$request);throw $this->unknown();}
        } finally {query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    private function markUnknown(int $id,string $request):void {
        query("UPDATE contact_action_attempts SET state='unknown',error_code='unconfirmed' WHERE connection_id=? AND request_id=?",[$id,$request]);
    }
    private function unknown():ActionProblem {
        return new ActionProblem('action_unconfirmed',409,'No se pudo confirmar la acción. Actualiza la ficha antes de repetirla: repetir lo mismo está bloqueado para evitar duplicados.');
    }

    private static function identifier(mixed $value):?string {
        return is_string($value)&&preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$value)?$value:null;
    }
    private static function text(mixed $value,int $max):?string {
        if(!is_string($value))return null;
        $value=trim($value);
        return $value!==''&&mb_strlen($value)<=$max?$value:null;
    }
    /** Accepts a calendar date or a full timestamp and normalizes to ISO-8601 UTC. */
    private static function due(mixed $value):?string {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:?\d{2})?)?$/D',trim($value)))return null;
        $value=trim($value);
        try {$date=new DateTimeImmutable(strlen($value)===10?$value.'T12:00:00Z':$value,new DateTimeZone('UTC'));}
        catch(Exception $e){return null;}
        $year=(int)$date->format('Y');
        return $year>=2000&&$year<=2100?$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'):null;
    }
}
