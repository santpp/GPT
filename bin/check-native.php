<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Installation.php';
echo 'Agora installation '.Installation::VERSION.PHP_EOL;
if(getenv('APP_ENV')!=='production'){fwrite(STDERR,"FAIL configure APP_ENV=production in private config/local.php\n");exit(1);}
$failed=false;
foreach(Installation::checks() as $check){
    echo ($check['ready']?'OK':'FAIL').' '.$check['name'].($check['code']?' ['.$check['code'].']':'').': '.$check['message'].PHP_EOL;
    $failed=$failed||!$check['ready'];
}
exit($failed?1:0);
