<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/InboxSync.php';
// One bounded pass, suitable for a once-per-minute CyberPanel cron as site user.
if((int)query("SELECT GET_LOCK('agora-inbox-cron',0)")->fetchColumn()!==1){echo "Previous sync pass still running\n";exit(0);}
$rows=query("SELECT c.* FROM ghl_connections c JOIN ghl_sync_state s ON s.connection_id=c.id WHERE s.enabled=TRUE AND c.status='connected' AND s.next_sync_at<=UNIX_TIMESTAMP() AND s.request_not_before<=UNIX_TIMESTAMP() ORDER BY s.next_sync_at LIMIT 20")->fetchAll();
$failed=0;
foreach($rows as $row) {
    try {(new InboxSync())->sync($row);echo 'Synced connection '.(int)$row['id'].PHP_EOL;}
    catch(GhlCooldown $e) {echo 'Deferred connection '.(int)$row['id'].PHP_EOL;}
    catch(Throwable $e) {$failed++;fwrite(STDERR,'Failed connection '.(int)$row['id'].'; inspect safe status in app'.PHP_EOL);}
}
query("SELECT RELEASE_LOCK('agora-inbox-cron')");
exit($failed?1:0);
