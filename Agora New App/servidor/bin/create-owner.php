<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
try{$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);Agora\Core\Installer::owner(new Agora\Core\DB($config['db']),$input);echo "Dueño creado. No se muestran credenciales.\n";}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
