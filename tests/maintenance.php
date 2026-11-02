<?php
declare(strict_types=1);
// La pausa de mantenimiento: estado, quién pasa durante la pausa y la ruta pública de estado.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Maintenance.php';
$n=0;function check(bool $ok,string $m):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $m\n");exit(1);}echo "PASS $m\n";$n++;}
$before=query("SELECT value FROM app_state WHERE name='maintenance'")->fetchColumn();
try{
 query("DELETE FROM app_state WHERE name='maintenance'");
 check(Maintenance::on()===false,'with no state saved Ágora is not paused');
 $s=Maintenance::set(true);check($s['on']===true&&$s['since']!==null&&Maintenance::on(),'the owner can pause it, and it records since when');
 $s=Maintenance::set(false);check($s['on']===false&&$s['since']===null&&!Maintenance::on(),'and turn it off again');
 check(Maintenance::passes(['identity_type'=>'native','role'=>'administrator']),'the owner (native administrator) is let through during the pause');
 check(!Maintenance::passes(['identity_type'=>'external','role'=>'administrator']),'an external user is not, even with an administrator role');
 check(!Maintenance::passes(['identity_type'=>'native','role'=>'team']),'nor is a native non-administrator');
 // La ruta pública de estado, contra el servidor de desarrollo.
 $get=fn(string $p)=>json_decode((string)@file_get_contents('http://127.0.0.1:8080'.$p,false,stream_context_create(['http'=>['ignore_errors'=>true]])),true);
 Maintenance::set(true);$r=$get('/api/status');check(($r['maintenance']??null)===true,'the public status route reports the pause without a session');
 Maintenance::set(false);$r=$get('/api/status');check(($r['maintenance']??null)===false&&count($r)===1,'and reports no pause, exposing nothing else');
}finally{
 if($before===false)query("DELETE FROM app_state WHERE name='maintenance'");else query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['maintenance',$before]);
}
echo "maintenance checks: $n\n";
