<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/Installation.php';
require __DIR__.'/../src/InboxSync.php';
require __DIR__.'/../src/Pulse.php';
require __DIR__.'/../src/PushDelivery.php';
require __DIR__.'/../src/ExternalUserDirectory.php';
// One bounded pass, suitable for a once-per-minute CyberPanel cron as site user.
// It is the only cron Ágora needs: it finds new messages, prepares their notices
// and delivers them. send-push.php still exists, but nothing depends on it.

// An update may have arrived since the last web visit: bring the database up to
// date first, so nothing below fails quietly against a table that is not there.
Installation::ensureSchema(Installation::checks(false));

$trace=function(array $value):void {
    try{query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['sweep_last_run',json_encode($value+['at'=>gmdate('c')])]);}catch(Throwable $e){}
};
if((int)query("SELECT GET_LOCK('agora-inbox-cron',0)")->fetchColumn()!==1){
    // Even a pass that steps aside leaves its trace: silence would read as "not running".
    $trace(['skipped'=>'previous_pass_running']);
    echo "Previous sync pass still running\n";exit(0);
}
// The same pass the open screens trigger. Running it here is what lets a new
// message be discovered, and a notice prepared, while every phone is asleep.
$rows=query("SELECT c.* FROM ghl_connections c JOIN ghl_sync_state s ON s.connection_id=c.id WHERE s.enabled=TRUE AND c.status='connected' AND s.request_not_before<=UNIX_TIMESTAMP() ORDER BY s.pulse_next_at LIMIT 20")->fetchAll();
$trace(['connections'=>count($rows)]);
$failed=0;$pulse=new Pulse();
foreach($rows as $row) {
    try {echo ($pulse->sweep($row)?'Swept connection ':'Already current ').(int)$row['id'].PHP_EOL;}
    catch(GhlCooldown $e) {echo 'Deferred connection '.(int)$row['id'].PHP_EOL;}
    catch(Throwable $e) {$failed++;fwrite(STDERR,'Failed connection '.(int)$row['id'].'; inspect safe status in app'.PHP_EOL);}
}
$teamRows=query("SELECT c.* FROM ghl_connections c JOIN ghl_sync_state s ON s.connection_id=c.id WHERE s.enabled=TRUE AND c.status='connected' AND s.users_next_sync_at<=UNIX_TIMESTAMP() AND s.request_not_before<=UNIX_TIMESTAMP() ORDER BY s.users_next_sync_at LIMIT 20")->fetchAll();
foreach($teamRows as $row){
    try{(new ExternalUserDirectory())->sync($row);echo 'Synced team '.(int)$row['id'].PHP_EOL;}
    catch(GhlCooldown $e){echo 'Deferred team '.(int)$row['id'].PHP_EOL;}
    catch(Throwable $e){$failed++;query('UPDATE ghl_sync_state SET users_next_sync_at=?,users_last_error=? WHERE connection_id=?',[time()+300,'users_sync_failed',$row['id']]);fwrite(STDERR,'Failed team '.(int)$row['id'].'; inspect safe status in app'.PHP_EOL);}
}
query("SELECT RELEASE_LOCK('agora-inbox-cron')");

// What was just discovered goes out in the same minute. The delivery lock is
// the one send-push.php takes, so the two can never send the same notice.
echo PushDelivery::deliverPending().PHP_EOL;
exit($failed?1:0);
