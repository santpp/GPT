<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Sweep.php';
// One bounded pass, suitable for a once-per-minute CyberPanel cron as site user.
// It finds new messages, prepares their notices and delivers them. When this
// cron cannot run, the secret address created in Connections does the same.
$result=Sweep::run('cron');
if($result['skipped']){echo "Previous sync pass still running\n";exit(0);}
printf("Swept %d of %d connections. %s\n",$result['swept'],$result['connections'],$result['delivered']);
