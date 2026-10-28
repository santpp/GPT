<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
$once = in_array('--once',$argv,true);
do {
    unset($event);
    try {
        db()->beginTransaction();
        $event=query("SELECT * FROM webhook_events WHERE status='pending' ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if (!$event) { db()->commit(); if (!$once) usleep(500000); continue; }
        $e=json_decode($event['payload'],true,32,JSON_THROW_ON_ERROR);
        // Partial events merge into read models; message events do not fabricate a full contact.
        $kind=match(true) {
            str_starts_with($e['type'],'Contact')=>'contact',
            str_starts_with($e['type'],'Opportunity')=>'opportunity',
            str_starts_with($e['type'],'Appointment')=>'appointment',
            $e['type']==='ConversationUnreadUpdate'=>'conversation',
            in_array($e['type'],['InboundMessage','OutboundMessage'],true)=>'message',
            default=>null
        };
        $id = $kind==='message' ? ($e['messageId'] ?? $e['emailMessageId'] ?? null) : ($e['id'] ?? null);
        if ($kind && $id) {
            if (str_ends_with($e['type'],'Delete')) query('DELETE FROM resources WHERE tenant_id=? AND kind=? AND ghl_id=?',[$event['tenant_id'],$kind,$id]);
            else query('INSERT INTO resources(tenant_id,kind,ghl_id,assigned_to,payload) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE assigned_to=COALESCE(VALUES(assigned_to),assigned_to),payload=JSON_MERGE_PATCH(payload,VALUES(payload)),updated_at=CURRENT_TIMESTAMP(6)',[$event['tenant_id'],$kind,$id,$e['assignedTo'] ?? null,json_encode($e,JSON_THROW_ON_ERROR)]);
        }
        if ($e['type']==='InboundMessage' && !empty($e['conversationId'])) query('INSERT IGNORE INTO notifications(tenant_id,event_id,conversation_id) VALUES(?,?,?)',[$event['tenant_id'],$event['id'],$e['conversationId']]);
        query("UPDATE webhook_events SET status='processed',processed_at=CURRENT_TIMESTAMP(6),attempts=attempts+1 WHERE id=?",[$event['id']]);
        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        error_log('Worker failure: '.get_class($error));
        if (isset($event['id'])) query("UPDATE webhook_events SET attempts=attempts+1,status=IF(attempts>=5,'failed','pending') WHERE id=?",[$event['id']]);
        if ($once) exit(1);
        sleep(1);
    }
} while (!$once);
