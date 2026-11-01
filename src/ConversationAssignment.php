<?php
declare(strict_types=1);
require_once __DIR__.'/ContactActions.php';
require_once __DIR__.'/OpportunityActions.php';

/**
 * Asignar una conversación. La cuenta no deja asignar una conversación por sí
 * sola: depende del responsable de su contacto. Así que asignar es cambiar el
 * responsable del contacto y, con él, el de sus oportunidades abiertas, para
 * que lo que cierre esa persona cuente en sus ventas.
 *
 * Sólo lo hacen los administradores de la subcuenta y el propietario de Ágora.
 */
final class ConversationAssignment {
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function assign(array $connection,array $conversation,array $membership,array $input):array {
        if(($membership['can_assign']??false)!==true)throw new ActionProblem('assign_forbidden',403,'Sólo los administradores de esta subcuenta pueden asignar conversaciones.');
        $request=$input['requestId']??null;
        if(!is_string($request)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$request))throw new ActionProblem('invalid_request_key',422,'Vuelve a cargar la pantalla antes de repetir la acción.');
        $contact=$conversation['contactId']??null;
        if(!is_string($contact)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contact)||($conversation['locationId']??$connection['location_id'])!==$connection['location_id'])
            throw new ActionProblem('invalid_contact',422,'Actualiza la conversación antes de asignarla.');
        $assignee=$input['assignedTo']??'';
        if($assignee===null)$assignee='';
        $name=null;
        if($assignee!=='') {
            $row=is_string($assignee)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$assignee)?query('SELECT display_name FROM external_users WHERE connection_id=? AND external_user_id=? AND active=TRUE LIMIT 1',[$connection['id'],$assignee])->fetchColumn():false;
            if($row===false)throw new ActionProblem('unknown_assignee',422,'Ese compañero no pertenece al equipo de esta cuenta.');
            $name=(string)$row;
        }
        // Quien asigna es administrador: las acciones internas se hacen con esa autoridad.
        $admin=$membership;
        $admin['capabilities']=array_replace($membership['capabilities']??[],['editContacts'=>true,'viewContacts'=>true,'editOpportunities'=>true,'viewOpportunities'=>true,'assignedDataOnly'=>false]);
        $admin['can_assign']=true;

        // 1) El contacto, de quien depende la conversación.
        try {(new ContactActions($this->gateway))->contact($connection,$contact,$admin,['fields'=>['assignedTo'=>$assignee],'requestId'=>$request]);}
        catch(ActionProblem $e){if($e->reason!=='nothing_to_change')throw $e;}

        // 2) Sus oportunidades abiertas.
        $updated=0;$failed=0;
        try {
            $found=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->contactOpportunities($connection['location_id'],$contact,20),false);
            $actions=new OpportunityActions($this->gateway);
            foreach(is_array($found['opportunities']??null)?$found['opportunities']:[] as $opportunity) {
                if(!is_array($opportunity)||($opportunity['status']??null)!=='open'||!is_string($opportunity['id']??null))continue;
                if(($opportunity['assignedTo']??'')===$assignee)continue;
                try {
                    $actions->update($connection,$opportunity['id'],$admin,['assignedTo'=>$assignee,'requestId'=>substr(hash('sha256',$request.'|'.$opportunity['id']),0,32)]);
                    $updated++;
                } catch(ActionProblem $e) {if($e->reason!=='nothing_to_change')$failed++;}
            }
        } catch(Throwable $e) {$failed++;}

        // «Te asignaron una conversación»: se avisa a la persona elegida antes de que el índice refleje el cambio.
        if($assignee!=='') {
            try {
                require_once __DIR__.'/PushQueue.php';
                $row=query('SELECT payload,assigned_to FROM conversation_index WHERE connection_id=? AND conversation_id=?',[$connection['id'],$conversation['id']])->fetch();
                $payload=$row?json_decode((string)$row['payload'],true):null;
                if(is_array($payload)&&($row['assigned_to']??null)!==$assignee)
                    PushQueue::queue(['id'=>(int)$connection['id']],[array_merge($payload,['id'=>$conversation['id'],'assignedTo'=>$assignee,'unreadCount'=>0])],[$conversation['id']=>0],[$conversation['id']=>$row['assigned_to']]);
            } catch(Throwable $e) {error_log('Assignment notice failed: '.get_class($e));}
        }
        // La lista de conversaciones y las pantallas guardadas reflejan el cambio ya.
        if($assignee==='')query("UPDATE conversation_index SET payload=JSON_REMOVE(payload,'\$.assignedTo'),assigned_to=NULL,content_hash=NULL,content_changed_at=CURRENT_TIMESTAMP(3) WHERE connection_id=? AND conversation_id=?",[$connection['id'],$conversation['id']]);
        else query("UPDATE conversation_index SET payload=JSON_SET(payload,'\$.assignedTo',?),assigned_to=?,content_hash=NULL,content_changed_at=CURRENT_TIMESTAMP(3) WHERE connection_id=? AND conversation_id=?",[$assignee,$assignee,$connection['id'],$conversation['id']]);
        query('DELETE FROM contact_profile_cache WHERE connection_id=? AND contact_id=?',[$connection['id'],$contact]);
        query('DELETE FROM opportunity_cache WHERE connection_id=? AND contact_id=?',[$connection['id'],$contact]);
        return ['assigned'=>true,'assignedTo'=>$assignee===''?null:$assignee,'assignedName'=>$name,'opportunities'=>['updated'=>$updated,'failed'=>$failed]];
    }
}
