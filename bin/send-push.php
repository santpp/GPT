<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/PushDelivery.php';
// One bounded pass, suitable for a once-per-minute CyberPanel cron as site user.
if(!PushDelivery::configured()){echo "Notifications are not configured; nothing to deliver\n";exit(0);}
if((int)query("SELECT GET_LOCK('agora-push-cron',0)")->fetchColumn()!==1){echo "Previous delivery pass still running\n";exit(0);}
try {
    $result=(new PushDelivery())->run(20);
    printf("Notices %d, sent %d, retried %d, devices dropped %d\n",$result['notices'],$result['sent'],$result['retried'],$result['dropped']);
    // Nothing of the service account, the tokens or the message bodies is printed.
    exit($result['retried']>0?1:0);
} catch(Throwable $e) {
    fwrite(STDERR,'Delivery pass failed; inspect the queue state in the database'.PHP_EOL);
    exit(1);
} finally {query("SELECT RELEASE_LOCK('agora-push-cron')");}
