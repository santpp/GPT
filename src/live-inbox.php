<?php
declare(strict_types=1);
require_once __DIR__.'/InboxSync.php';
require_once __DIR__.'/MessageHistory.php';
require_once __DIR__.'/MessageFeed.php';
if(($u['identity_type']??'native')==='native'&&$u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
if(in_array($path,['/api/workspace/connections','/api/ghl/connections'],true) && $method==='GET') {
    if(($u['identity_type']??'native')==='native')$items=query('SELECT id,name,location_id,status FROM ghl_connections WHERE tenant_id=? ORDER BY id',[$u['tenant_id']])->fetchAll();
    else $items=query('SELECT DISTINCT c.id,c.name,c.location_id,c.status FROM ghl_connections c JOIN external_users eu ON eu.connection_id=c.id AND eu.active=TRUE JOIN external_identity_memberships m ON m.external_user_row_id=eu.id WHERE c.tenant_id=? AND m.identity_id=? ORDER BY c.id',[$u['tenant_id'],$u['id']])->fetchAll();
    $items=array_values(array_filter($items,fn(array $item)=>(Actor::connectionMembership($u,(int)$item['id'])['capabilities']['viewConversations']??false)===true));
    respond(['items'=>$items]);
}
$id=filter_var($_GET['connectionId']??null,FILTER_VALIDATE_INT);
if(!$id)respond(['error'=>'Selecciona una subcuenta'],422);
$connection=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$id,$u['tenant_id']])->fetch();
if(!$connection)respond(['error'=>'Subcuenta no encontrada'],404);
$membership=Actor::connectionMembership($u,(int)$id);
if(!$membership||!($membership['capabilities']['viewConversations']??false))respond(['error'=>'No tienes permiso para consultar esta cuenta.'],403);
if($path==='/api/workspace/sync'&&$method==='POST'){
    $requested=filter_var($_GET['limit']??null,FILTER_VALIDATE_INT);
    if(!in_array($requested,[20,50,100],true))respond(['error'=>'Selecciona 20, 50 o 100 conversaciones'],422);
    try{$result=(new InboxSync())->sync($connection,$requested);respond(($u['identity_type']??'native')==='native'?['synced'=>$result]:['synced'=>true]);}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La actualización está en espera para respetar el intervalo.','retryAfter'=>$e->retryAfter],429);}
    catch(InvalidArgumentException $e){respond(['error'=>'Comprueba la conexión antes de actualizar.'],422);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo actualizar la bandeja. Revisa los permisos de lectura.','code'=>'inbox_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'La bandeja recibida no tiene el formato esperado.','code'=>'inbox_invalid_response'],424);}
}
$row=query('SELECT * FROM ghl_inbox_snapshots WHERE connection_id=?',[$id])->fetch();
if($path==='/api/workspace/contact'&&$method==='GET'){
    if(!($membership['capabilities']['viewContacts']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar contactos.'],403);
    $conversation=$_GET['conversationId']??'';
    if(!is_string($conversation)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$conversation))respond(['error'=>'Conversación inválida'],422);
    $known=Actor::filterConversations($row?json_decode($row['payload'],true):[],$membership);$position=array_search($conversation,array_column($known,'id'),true);
    if($position===false)respond(['error'=>'Conversación no disponible en esta cuenta'],404);
    $contactId=$known[$position]['contactId']??'';if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'La conversación no contiene un contacto válido'],422);
    require_once __DIR__.'/ContactProfile.php';
    require_once __DIR__.'/MessageSender.php';
    require_once __DIR__.'/ContactFieldDirectory.php';
    try{
        $profile=(new ContactProfile())->get($connection,$contactId,($_GET['refresh']??'')==='1');
        $canEdit=($membership['capabilities']['editContacts']??false)===true;
        $directory=new ContactFieldDirectory();
        $fieldDirectory=$directory->cached($connection);
        if($fieldDirectory===null) {
            // Costs one request an hour at most, and only a failure leaves it empty.
            try {$fieldDirectory=$directory->get($connection);}
            catch(Throwable $e){$fieldDirectory=[];}
        }
        respond([
            'contact'=>$profile,
            'channels'=>($membership['capabilities']['sendMessages']??false)?MessageSender::availableChannels($connection,$known[$position]):[],
            'canEdit'=>$canEdit,
            // The definitions come from the stored directory so the sheet costs no
            // extra request; the separate route refreshes it when it has expired.
            // Definitions must always reach the sheet: an empty directory would
            // hide every custom field the contact has not filled in yet.
            'fields'=>$fieldDirectory,
            'assignees'=>$canEdit?query('SELECT external_user_id AS id,display_name AS name FROM external_users WHERE connection_id=? AND active=TRUE ORDER BY display_name',[$connection['id']])->fetchAll():[],
        ]);
    }
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La ficha está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo consultar la ficha. Revisa el permiso de contactos.','code'=>'contact_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'La ficha recibida no tiene el formato esperado.','code'=>'contact_invalid_response'],424);}
}
if(preg_match('~^/api/workspace/activity(/note|/task|/task-state|/contact)?$~',$path,$activityMatch)&&in_array($method,['GET','POST'],true)){
    if(!($membership['capabilities']['viewContacts']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar la ficha del contacto.'],403);
    $conversation=$_GET['conversationId']??'';
    if(!is_string($conversation)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$conversation))respond(['error'=>'Conversación inválida'],422);
    $known=Actor::filterConversations($row?json_decode($row['payload'],true):[],$membership);$position=array_search($conversation,array_column($known,'id'),true);
    if($position===false)respond(['error'=>'Conversación no disponible en esta cuenta'],404);
    $contactId=$known[$position]['contactId']??'';if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'La conversación no contiene un contacto válido'],422);
    require_once __DIR__.'/ContactActivity.php';
    $action=$activityMatch[1]??'';
    if($action!==''&&$method==='POST'){
        require_once __DIR__.'/ContactActions.php';
        $actions=new ContactActions();$input=body();
        try {
            respond(match($action){
                '/note'=>$actions->note($connection,$contactId,$membership,$input),
                '/task'=>$actions->task($connection,$contactId,$membership,$input),
                '/task-state'=>$actions->completeTask($connection,$contactId,$membership,$input),
                '/contact'=>$actions->contact($connection,$contactId,$membership,$input),
            });
        } catch(ActionProblem $e){if($e->retryAfter)header('Retry-After: '.$e->retryAfter);respond(['error'=>$e->getMessage(),'code'=>$e->reason,'retryAfter'=>$e->retryAfter],$e->httpStatus);}
    }
    if($action!==''||$method!=='GET')respond(['error'=>'Not found'],404);
    try{respond((new ContactActivity())->get($connection,$contactId,$membership,($_GET['refresh']??'')==='1'));}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La actividad está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo consultar la actividad del contacto. Revisa el permiso de contactos.','code'=>'activity_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'La actividad recibida no tiene el formato esperado.','code'=>'activity_invalid_response'],424);}
}
if($path==='/api/workspace/contacts'&&$method==='GET'){
    if(!($membership['capabilities']['viewContacts']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar contactos.'],403);
    require_once __DIR__.'/ContactDirectory.php';
    $search=$_GET['query']??null;
    if($search!==null&&(!is_string($search)||mb_strlen($search)>100))respond(['error'=>'Búsqueda demasiado larga'],422);
    try{respond((new ContactDirectory())->get($connection,$search,$membership,($_GET['refresh']??'')==='1'));}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La lista está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo consultar la lista de contactos. Revisa el permiso de contactos.','code'=>'contacts_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'La lista recibida no tiene el formato esperado.','code'=>'contacts_invalid_response'],424);}
}
if($path==='/api/workspace/board'&&$method==='GET'){
    if(!($membership['capabilities']['viewOpportunities']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar oportunidades.'],403);
    require_once __DIR__.'/OpportunityBoard.php';
    $pipeline=$_GET['pipelineId']??null;
    if($pipeline!==null&&(!is_string($pipeline)||!preg_match('/^[A-Za-z0-9_-]{0,80}$/D',$pipeline)))respond(['error'=>'Pipeline inválido'],422);
    try{respond((new OpportunityBoard())->get($connection,$pipeline!==null&&$pipeline!==''?$pipeline:null,$membership,($_GET['refresh']??'')==='1'));}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'El tablero está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo consultar el tablero. Revisa el permiso de oportunidades.','code'=>'board_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'El tablero recibido no tiene el formato esperado.','code'=>'board_invalid_response'],424);}
}
if($path==='/api/workspace/opportunities'&&$method==='GET'){
    if(!($membership['capabilities']['viewOpportunities']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar oportunidades.'],403);
    $conversation=$_GET['conversationId']??'';
    if(!is_string($conversation)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$conversation))respond(['error'=>'Conversación inválida'],422);
    $known=Actor::filterConversations($row?json_decode($row['payload'],true):[],$membership);$position=array_search($conversation,array_column($known,'id'),true);
    if($position===false)respond(['error'=>'Conversación no disponible en esta cuenta'],404);
    $contactId=$known[$position]['contactId']??'';if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'La conversación no contiene un contacto válido'],422);
    require_once __DIR__.'/OpportunityContext.php';
    try{respond((new OpportunityContext())->get($connection,$contactId,$membership,($_GET['refresh']??'')==='1'));}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'Las oportunidades están en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudieron consultar las oportunidades. Revisa el permiso de oportunidades.','code'=>'opportunities_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'Las oportunidades recibidas no tienen el formato esperado.','code'=>'opportunities_invalid_response'],424);}
}
if($path==='/api/workspace/contact-fields'&&$method==='GET'){
    if(!($membership['capabilities']['viewContacts']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar contactos.'],403);
    require_once __DIR__.'/ContactFieldDirectory.php';
    try{respond(['items'=>(new ContactFieldDirectory())->get($connection)]);}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'Los campos están en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudieron consultar los nombres de los campos.','code'=>'contact_fields_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'Los campos recibidos no tienen el formato esperado.','code'=>'contact_fields_invalid_response'],424);}
}
if(in_array($path,['/api/workspace/inbox','/api/ghl/inbox'],true) && $method==='GET') {
    $state=query('SELECT last_success_at,last_error,next_sync_at,request_not_before,enabled,requests_today,quota FROM ghl_sync_state WHERE connection_id=?',[$id])->fetch();
    $limit=(int)(query('SELECT inbox_limit FROM connection_preferences WHERE connection_id=?',[$id])->fetchColumn()?:20);
    $raw=$row?json_decode($row['payload'],true):[];$items=Actor::filterConversations($raw,$membership);$restricted=(bool)($membership['capabilities']['assignedDataOnly']??false);
    respond(['syncIntervalSeconds'=>max(60,(int)$connection['sync_interval_seconds']),'limit'=>$limit,'items'=>$items,'total'=>$restricted?count($items):($row?(int)$row['total']:0),'syncedAt'=>$row['synced_at']??null,'partial'=>$row?((int)$row['total']>count($raw)):false,'state'=>$state?:null]);
}
if((in_array($path,['/api/workspace/messages','/api/ghl/messages'],true) && $method==='GET')||($path==='/api/workspace/send'&&$method==='POST')) {
    $conversation=$_GET['conversationId']??'';
    if(!is_string($conversation)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$conversation))respond(['error'=>'Conversación inválida'],422);
    $known=Actor::filterConversations($row?json_decode($row['payload'],true):[],$membership);
    if(!in_array($conversation,array_column($known,'id'),true))respond(['error'=>'Conversación no disponible en esta subcuenta'],404);
    if($path==='/api/workspace/send'){
        if(!($membership['capabilities']['sendMessages']??false))respond(['error'=>'Tu usuario tiene acceso de lectura, pero no permiso para responder.'],403);
        require_once __DIR__.'/MessageSender.php';
        $contact=$known[array_search($conversation,array_column($known,'id'),true)];
        try {respond((new MessageSender())->send($connection,$contact,body()));}
        catch(SendProblem $e){respond(['error'=>$e->getMessage(),'code'=>$e->reason,'retryAfter'=>$e->retryAfter],$e->httpStatus);}
    }
    try {$cursor=MessageHistory::cursor($_GET['lastMessageId']??null);}
    catch(InvalidArgumentException $e){respond(['error'=>'Referencia de historial inválida'],422);}
    try {
        $feed=new MessageFeed();
        respond($cursor?$feed->older($connection,$conversation,$cursor):$feed->recent($connection,$conversation,($_GET['refresh']??'')==='1'));
    } catch(GhlCooldown $e) {header('Retry-After: '.$e->retryAfter);respond(['error'=>'Consulta pausada para respetar la cuota. Vuelve a intentarlo más tarde.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudieron leer los mensajes. Código del canal: '.$e->status.'. Revisa los permisos de lectura.','code'=>'messages_upstream_'.$e->status],424);}
    catch(HistoryFormatException $e) {respond(['error'=>'El historial recibido no tiene el formato esperado. Código: messages_'.$e->reason.'.','code'=>'messages_'.$e->reason],424);}
    catch(Throwable $e) {respond(['error'=>'El historial recibido no tiene el formato esperado. Código: messages_invalid_response.','code'=>'messages_invalid_response'],424);}
}
respond(['error'=>'Not found'],404);
