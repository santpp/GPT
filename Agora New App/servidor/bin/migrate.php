<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
try{$db=new Agora\Core\DB($config['db']);$applied=Agora\Core\Installer::migrate($db);echo json_encode(['ok'=>true,'applied'=>$applied]),PHP_EOL;}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
