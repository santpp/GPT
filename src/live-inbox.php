<?php
declare(strict_types=1);
// The first live slice is admin-only until per-location seller mappings exist.
if($u['role']!=='administrator')respond(['error'=>'Inbox conectado disponible por ahora sólo para el administrador'],403);
require_once __DIR__.'/InboxSync.php';
require_once __DIR__.'/MessageHistory.php';
if(in_array($path,['/api/workspace/connections','/api/ghl/connections'],true) && $method==='GET') {
    respond(['items'=>query('SELECT id,name,location_id,status FROM ghl_connections WHERE tenant_id=? ORDER BY id',[$u['tenant_id']])->fetchAll()]);
}
$id=filter_var($_GET['connectionId']??null,FILTER_VALIDATE_INT);
if(!$id)respond(['error'=>'Selecciona una subcuenta'],422);
$connection=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$id,$u['tenant_id']])->fetch();
if(!$connection)respond(['error'=>'Subcuenta no encontrada'],404);
$row=query('SELECT * FROM ghl_inbox_snapshots WHERE connection_id=?',[$id])->fetch();
if(in_array($path,['/api/workspace/inbox','/api/ghl/inbox'],true) && $method==='GET') {
    $state=query('SELECT last_success_at,last_error,next_sync_at,request_not_before,enabled,requests_today,quota FROM ghl_sync_state WHERE connection_id=?',[$id])->fetch();
    $limit=(int)(query('SELECT inbox_limit FROM connection_preferences WHERE connection_id=?',[$id])->fetchColumn()?:20);
    respond(['limit'=>$limit,'items'=>$row?json_decode($row['payload'],true):[],'total'=>$row?(int)$row['total']:0,'syncedAt'=>$row['synced_at']??null,'partial'=>$row?(int)$row['total']>count(json_decode($row['payload'],true)):false,'state'=>$state?:null]);
}
if((in_array($path,['/api/workspace/messages','/api/ghl/messages'],true) && $method==='GET')||($path==='/api/workspace/send'&&$method==='POST')) {
    $conversation=$_GET['conversationId']??'';
    if(!is_string($conversation)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$conversation))respond(['error'=>'Conversación inválida'],422);
    $known=$row?json_decode($row['payload'],true):[];
    if(!in_array($conversation,array_column($known,'id'),true))respond(['error'=>'Conversación no disponible en esta subcuenta'],404);
    if($path==='/api/workspace/send'){
        require_once __DIR__.'/MessageSender.php';
        $contact=$known[array_search($conversation,array_column($known,'id'),true)];
        try {respond((new MessageSender())->send($connection,$contact,body()));}
        catch(SendProblem $e){respond(['error'=>$e->getMessage(),'code'=>$e->reason,'retryAfter'=>$e->retryAfter],$e->httpStatus);}
    }
    try {$cursor=MessageHistory::cursor($_GET['lastMessageId']??null);}
    catch(InvalidArgumentException $e){respond(['error'=>'Referencia de historial inválida'],422);}
    try {
        $data=(new GhlGateway())->execute($connection,fn(GhlClient $client)=>$client->conversationMessages($conversation,$cursor));
        respond(MessageHistory::page($data,$conversation,$connection['location_id'],$cursor));
    } catch(GhlCooldown $e) {header('Retry-After: '.$e->retryAfter);respond(['error'=>'Consulta pausada para respetar la cuota. Vuelve a intentarlo más tarde.','retryAfter'=>$e->retryAfter],429);}
    catch(Throwable $e) {respond(['error'=>'No se pudieron leer los mensajes. Revisa los permisos y el estado de la conexión.'],502);}
}
respond(['error'=>'Not found'],404);
