<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Sweep.php';
// One bounded pass, suitable for a once-per-minute CyberPanel cron as site user.
// It finds new messages, prepares their notices and delivers them. When this
// cron cannot run, the secret address created in Connections does the same.
// --once does a single pass and says what it did, for checking it by hand.
if(in_array('--once',$argv,true)){
    $result=Sweep::run('cron');
    if($result['skipped']){echo "Another check is already running\n";exit(0);}
    printf("Checked %d of %d connections. %s\n",$result['swept'],$result['connections'],$result['delivered']);
    exit(0);
}
// The cron fires once a minute; staying to check every 10 seconds until the
// next one is what brings a notice down from a minute to a few seconds.
$passes=Sweep::loop('cron',50);
echo $passes?"Checked $passes times\n":"Another check is already running\n";
