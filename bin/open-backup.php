<?php
declare(strict_types=1);
// Descifra una copia: php open-backup.php agora-AAAAMMDD-HHMMSS.agora-backup salida.zip
// La frase se pide por teclado (o BACKUP_PASSPHRASE). No necesita la base de datos.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/Backup.php';
[$self,$in,$out]=array_pad($argv,3,null);
if(!$in||!$out){fwrite(STDERR,"Uso: php open-backup.php COPIA.agora-backup SALIDA.zip".PHP_EOL);exit(2);}
$pass=getenv('BACKUP_PASSPHRASE');
if(!$pass){fwrite(STDOUT,'Frase de la copia: ');system('stty -echo 2>/dev/null');$pass=trim((string)fgets(STDIN));system('stty echo 2>/dev/null');fwrite(STDOUT,PHP_EOL);}
try{file_put_contents($out,Backup::open((string)file_get_contents($in),$pass));@chmod($out,0600);echo "Listo: $out".PHP_EOL;}
catch(InvalidArgumentException $e){fwrite(STDERR,($e->getMessage()==='wrong_passphrase'?'La frase no es correcta o la copia está dañada.':'Ese archivo no es una copia de Ágora.').PHP_EOL);exit(1);}
