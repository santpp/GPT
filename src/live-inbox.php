<?php
declare(strict_types=1);
require_once __DIR__.'/InboxSync.php';
require_once __DIR__.'/InboxFeed.php';
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
    $record=InboxFeed::known($id,$conversation);
    if(!$record||!Actor::filterConversations([$record],$membership))respond(['error'=>'Conversación no disponible en esta cuenta'],404);
    $known=[$record];$position=0;
    $contactId=$record['contactId']??'';if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'La conversación no contiene un contacto válido'],422);
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
    $record=InboxFeed::known($id,$conversation);
    if(!$record||!Actor::filterConversations([$record],$membership))respond(['error'=>'Conversación no disponible en esta cuenta'],404);
    $known=[$record];$position=0;
    $contactId=$record['contactId']??'';if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'La conversación no contiene un contacto válido'],422);
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
// Opportunities: open one for a contact, edit it, move its stage, change its
// status or reassign it. Each route names its own subject, never a conversation.
if(preg_match('~^/api/workspace/opportunity/(new|edit|status)$~',$path,$opportunityMatch)&&$method==='POST'){
    if(!($membership['capabilities']['viewOpportunities']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar oportunidades.'],403);
    require_once __DIR__.'/OpportunityActions.php';
    $actions=new OpportunityActions();$input=body();
    try {
        if($opportunityMatch[1]==='new'){
            $contactId=$_GET['contactId']??'';
            if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'Contacto inválido'],422);
            // The contact must exist in this very account before anything is opened for it.
            require_once __DIR__.'/ContactProfile.php';
            $owner=(new ContactProfile())->get($connection,$contactId);
            if(($membership['capabilities']['assignedDataOnly']??false)===true&&$owner['assignedTo']!==($membership['external_user_id']??null))
                respond(['error'=>'Este contacto no está asignado a tu usuario.'],403);
            respond($actions->create($connection,$contactId,$membership,$input));
        }
        $opportunityId=$_GET['opportunityId']??'';
        if(!is_string($opportunityId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$opportunityId))respond(['error'=>'Oportunidad inválida'],422);
        respond($opportunityMatch[1]==='edit'
            ?$actions->update($connection,$opportunityId,$membership,$input)
            :$actions->status($connection,$opportunityId,$membership,$input));
    }
    catch(ActionProblem $e){if($e->retryAfter)header('Retry-After: '.$e->retryAfter);respond(['error'=>$e->getMessage(),'code'=>$e->reason,'retryAfter'=>$e->retryAfter],$e->httpStatus);}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La acción está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo completar la acción sobre la oportunidad.','code'=>'opportunity_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'La respuesta recibida no tiene el formato esperado.','code'=>'opportunity_invalid_response'],424);}
}
// The pipelines and stages a write may name, with the team that may be made
// responsible: the form offers only what the account really publishes.
if($path==='/api/workspace/pipelines'&&$method==='GET'){
    if(!($membership['capabilities']['viewOpportunities']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar oportunidades.'],403);
    require_once __DIR__.'/PipelineDirectory.php';
    try{
        $names=(new PipelineDirectory())->get($connection);
        respond([
            'pipelines'=>array_values(array_map(fn(string $id):array=>[
                'id'=>$id,
                'name'=>$names[$id]['name'],
                'stages'=>array_values(array_map(fn(string $stage):array=>['id'=>$stage,'name'=>$names[$id]['stages'][$stage]],array_keys($names[$id]['stages']))),
            ],array_keys($names))),
            'assignees'=>($membership['capabilities']['editOpportunities']??false)?query('SELECT external_user_id AS id,display_name AS name FROM external_users WHERE connection_id=? AND active=TRUE ORDER BY display_name',[$connection['id']])->fetchAll():[],
            'canEdit'=>($membership['capabilities']['editOpportunities']??false)===true,
        ]);
    }
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'Los embudos están en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudieron consultar los embudos. Revisa el permiso de oportunidades.','code'=>'pipelines_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'Los embudos recibidos no tienen el formato esperado.','code'=>'pipelines_invalid_response'],424);}
}
// La rentabilidad del mes y el desempeño del equipo. Lo cerrado sale de la
// cuenta; lo invertido, los costos y las metas los escribe el propietario.
if($path==='/api/workspace/board-month'&&in_array($method,['GET','POST'],true)){
    if(!($membership['capabilities']['viewOpportunities']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar oportunidades.'],403);
    require_once __DIR__.'/Board.php';
    $period=Board::period($_GET['period']??null);
    if($method==='POST'){
        // Sólo quien administra las conexiones fija las cifras del negocio.
        if(($u['identity_type']??'native')!=='native'||($u['role']??null)!=='administrator')respond(['error'=>'Sólo el administrador puede fijar las cifras del mes.'],403);
        respond(['settings'=>Board::save($id,$period,body()),'period'=>$period]);
    }
    try{respond((new Board())->month($connection,$period,$membership)+['canEdit'=>($u['identity_type']??'native')==='native'&&($u['role']??null)==='administrator']);}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'El resumen del mes está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo leer lo cerrado del mes. Revisa el permiso de oportunidades.','code'=>'board_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'El resumen del mes recibido no tiene el formato esperado.','code'=>'board_invalid_response'],424);}
}
if($path==='/api/workspace/dashboard'&&$method==='GET'){
    require_once __DIR__.'/Dashboard.php';
    try{respond((new Dashboard())->get($connection,$membership));}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'El tablero está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo leer el tablero. Revisa los permisos de la cuenta.','code'=>'dashboard_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'El tablero recibido no tiene el formato esperado.','code'=>'dashboard_invalid_response'],424);}
}
// The contacts screen opens a record directly, without going through a chat.
if($path==='/api/workspace/record'&&in_array($method,['GET','POST'],true)){
    if(!($membership['capabilities']['viewContacts']??false))respond(['error'=>'Tu usuario no tiene permiso para consultar contactos.'],403);
    $contactId=$_GET['contactId']??'';
    if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'Contacto inválido'],422);
    require_once __DIR__.'/ContactProfile.php';
    require_once __DIR__.'/ContactFieldDirectory.php';
    if($method==='POST'){
        require_once __DIR__.'/ContactActions.php';
        try{
            // The record is read first so the edit can never touch another account,
            // nor a contact that is not this member's to edit.
            $current=(new ContactProfile())->get($connection,$contactId);
            if(($membership['capabilities']['assignedDataOnly']??false)===true&&$current['assignedTo']!==($membership['external_user_id']??null))
                respond(['error'=>'Este contacto no está asignado a tu usuario.'],403);
            respond((new ContactActions())->contact($connection,$contactId,$membership,body()));
        }
        catch(ActionProblem $e){if($e->retryAfter)header('Retry-After: '.$e->retryAfter);respond(['error'=>$e->getMessage(),'code'=>$e->reason,'retryAfter'=>$e->retryAfter],$e->httpStatus);}
        catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La ficha está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
        catch(GhlApiException $e){respond(['error'=>'No se pudo abrir la ficha de ese contacto.','code'=>'record_upstream_'.$e->status],424);}
        catch(Throwable $e){respond(['error'=>'La ficha recibida no tiene el formato esperado.','code'=>'record_invalid_response'],424);}
    }
    try{
        $profile=(new ContactProfile())->get($connection,$contactId,($_GET['refresh']??'')==='1');
        // A member restricted to assigned data only opens their own contacts.
        if(($membership['capabilities']['assignedDataOnly']??false)===true&&$profile['assignedTo']!==($membership['external_user_id']??null))
            respond(['error'=>'Este contacto no está asignado a tu usuario.'],403);
        $canEdit=($membership['capabilities']['editContacts']??false)===true;
        $directory=new ContactFieldDirectory();
        $fields=$directory->cached($connection);
        if($fields===null){try{$fields=$directory->get($connection);}catch(Throwable $e){$fields=[];}}
        respond([
            'contact'=>$profile,
            'canEdit'=>$canEdit,
            'fields'=>$fields,
            'assignees'=>$canEdit?query('SELECT external_user_id AS id,display_name AS name FROM external_users WHERE connection_id=? AND active=TRUE ORDER BY display_name',[$connection['id']])->fetchAll():[],
        ]);
    }
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La ficha está en espera para respetar la cuota.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo abrir la ficha de ese contacto.','code'=>'record_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'La ficha recibida no tiene el formato esperado.','code'=>'record_invalid_response'],424);}
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
    $record=InboxFeed::known($id,$conversation);
    if(!$record||!Actor::filterConversations([$record],$membership))respond(['error'=>'Conversación no disponible en esta cuenta'],404);
    $known=[$record];$position=0;
    $contactId=$record['contactId']??'';if(!is_string($contactId)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contactId))respond(['error'=>'La conversación no contiene un contacto válido'],422);
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
    session_write_close();
    $state=query('SELECT last_success_at,last_error,next_sync_at,request_not_before,enabled,requests_today,quota FROM ghl_sync_state WHERE connection_id=?',[$id])->fetch();
    $requested=filter_var($_GET['limit']??null,FILTER_VALIDATE_INT);
    $limit=in_array($requested,[20,50,100],true)?$requested:(int)(query('SELECT inbox_limit FROM connection_preferences WHERE connection_id=?',[$id])->fetchColumn()?:20);
    if(!in_array($limit,[20,50,100],true))$limit=20;
    query('INSERT INTO connection_preferences(connection_id,inbox_limit) VALUES(?,?) ON DUPLICATE KEY UPDATE inbox_limit=VALUES(inbox_limit)',[$id,$limit]);
    ['filters'=>$filters]=InboxFeed::readFilters($_GET,$membership);
    // The position to continue from is the one the previous page handed back.
    $cursor=$_GET['startAfter']??null;
    if($cursor!==null&&(!is_string($cursor)||!preg_match('/^[0-9]{1,16}$/D',$cursor)))respond(['error'=>'Referencia de página inválida'],422);
    try{
        $feed=(new InboxFeed())->load($connection,$limit,$filters,$membership,$cursor!==null?$cursor:null);
        respond($feed+['syncIntervalSeconds'=>max(60,(int)$connection['sync_interval_seconds']),'limit'=>$limit,'state'=>$state?:null]);
    }
    catch(GhlApiException $e){respond(['error'=>'No se pudo consultar la bandeja. Revisa los permisos de lectura.','code'=>'inbox_upstream_'.$e->status],424);}
    catch(InvalidArgumentException $e){respond(['error'=>'Comprueba la conexión antes de consultar la bandeja.'],422);}
    catch(Throwable $e){respond(['error'=>'La bandeja recibida no tiene el formato esperado.','code'=>'inbox_invalid_response'],424);}
}
// Near-live updates: the browser asks Ágora, never the account. The answer is a
// token that only changes when the conversations changed, and the account itself
// is read by one shared pass per connection however many people are looking.
if($path==='/api/workspace/pulse'&&$method==='GET'){
    session_write_close();
    require_once __DIR__.'/Pulse.php';
    try{
        $beat=(new Pulse())->read($connection,$membership,($_GET['refresh']??'1')!=='0');
        // Si hay avisos esperando, quien tenga una pantalla abierta los hace salir
        // en el acto, una vez respondido: no depende de que el cron corra.
        $waiting=(int)query("SELECT COUNT(*) FROM push_queue WHERE connection_id=? AND state='pending' AND not_before<=UNIX_TIMESTAMP()",[$id])->fetchColumn();
        if($waiting>0){require_once __DIR__.'/PushDelivery.php';respondThen($beat,fn()=>PushDelivery::deliverPending(5));}
        respond($beat);
    }
    catch(Throwable $e){respond(['error'=>'No se pudo comprobar si hay novedades.','code'=>'pulse_failed'],424);}
}
if($path==='/api/workspace/read'&&$method==='POST'){
    $conversation=$_GET['conversationId']??'';
    if(!is_string($conversation)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$conversation))respond(['error'=>'Conversación inválida'],422);
    $known=InboxFeed::known($id,$conversation);
    if(!$known||!Actor::filterConversations([$known],$membership))respond(['error'=>'Conversación no disponible en esta cuenta'],404);
    try{(new InboxFeed())->markRead($connection,$known);respond(['read'=>true]);}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La marca de leído está en espera.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>'No se pudo marcar como leída. Revisa el permiso de escritura de conversaciones.','code'=>'read_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'No se pudo marcar como leída.','code'=>'read_failed'],424);}
}
// Plantillas aprobadas de WhatsApp de esta cuenta, para escribir pasadas las 24 horas.
if($path==='/api/workspace/templates'&&$method==='GET') {
    if(!($membership['capabilities']['sendMessages']??false))respond(['error'=>'Tu usuario no tiene permiso para responder.'],403);
    require_once __DIR__.'/WhatsappTemplates.php';
    try {respond(['items'=>(new WhatsappTemplates())->list($connection,($_GET['refresh']??'')==='1'),'configured'=>true]);}
    catch(TemplateProblem $e){if($e->reason==='number_missing')respond(['items'=>[],'configured'=>false,'error'=>$e->getMessage()]);respond(['error'=>$e->getMessage(),'code'=>$e->reason],$e->httpStatus);}
    catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'La lista de plantillas está en espera. Vuelve a intentarlo en unos segundos.','retryAfter'=>$e->retryAfter],429);}
    catch(GhlApiException $e){respond(['error'=>in_array($e->status,[401,403],true)?'La clave de la cuenta no permite leer plantillas. Hay que añadirle el permiso de plantillas.':'No se pudieron leer las plantillas de la cuenta.','code'=>'templates_upstream_'.$e->status],424);}
    catch(Throwable $e){respond(['error'=>'No se pudieron leer las plantillas de la cuenta.','code'=>'templates_failed'],424);}
}
if((in_array($path,['/api/workspace/messages','/api/ghl/messages'],true) && $method==='GET')||($path==='/api/workspace/send'&&$method==='POST')) {
    $conversation=$_GET['conversationId']??'';
    if(!is_string($conversation)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$conversation))respond(['error'=>'Conversación inválida'],422);
    $record=InboxFeed::known($id,$conversation);
    if(!$record||!Actor::filterConversations([$record],$membership))respond(['error'=>'Conversación no disponible en esta subcuenta'],404);
    $known=[$record];
    if($path==='/api/workspace/send'){
        if(!($membership['capabilities']['sendMessages']??false))respond(['error'=>'Tu usuario tiene acceso de lectura, pero no permiso para responder.'],403);
        require_once __DIR__.'/MessageSender.php';
        $contact=$record;
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
