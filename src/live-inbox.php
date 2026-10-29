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
    catch(Throwable $e) {respond(['error'=>'El historial recibido no tiene el formato esperado. Código: messages_invalid_response.','code'=>'messages_invalid_response'],424);}
}
respond(['error'=>'Not found'],404);
