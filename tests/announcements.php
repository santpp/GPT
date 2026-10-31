<?php
declare(strict_types=1);
// Novedades: sólo de la propia empresa, una vez por persona, con caducidad y botón seguro.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Announcements.php';
$tenants=[];$checks=0;
function ann(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
try {
 foreach(['A','B'] as $n){query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Ann '.$n,'ann-'.$n.bin2hex(random_bytes(4))]);$tenants[$n]=(int)db()->lastInsertId();}
 $ana=['tenant_id'=>$tenants['A'],'identity_type'=>'external','id'=>101];$luis=['tenant_id'=>$tenants['A'],'identity_type'=>'external','id'=>102];$bea=['tenant_id'=>$tenants['B'],'identity_type'=>'external','id'=>201];
 $id=Announcements::create($tenants['A'],['title'=>"  Ágora 1.1  ",'body'=>"Incluye:\r\n- Uno\r\n\r\n\r\n\r\n- Dos"]);
 $p=Announcements::pending($ana);
 ann(count($p)===1&&$p[0]['title']==='Ágora 1.1'&&!str_contains($p[0]['body'],"\r")&&!str_contains($p[0]['body'],"\n\n\n"),'each person of the company sees it, tidied up');
 ann(Announcements::pending($bea)===[],'another company never sees it');
 Announcements::markRead($ana,$id);
 ann(Announcements::pending($ana)===[]&&count(Announcements::pending($luis))===1,'once read by one person it stays for the others');
 $failed=false;try{Announcements::markRead($bea,$id);}catch(AnnouncementProblem $e){$failed=true;}
 ann($failed,'another company cannot mark it as read');
 Announcements::markRead($ana,$id);ann(true,'marking twice does no harm');
 $short=Announcements::create($tenants['A'],['title'=>'Caduca','body'=>'x','days'=>1]);
 query('UPDATE announcements SET expires_at=? WHERE id=?',[time()-5,$short]);
 ann(!in_array($short,array_column(Announcements::pending($luis),'id'),true),'one that expired is no longer shown');
 ann(Announcements::list($tenants['A'])[1]['seen']===1,'the list says how many saw it');
 foreach([['title'=>str_repeat('x',121),'body'=>'x'],['title'=>'T','body'=>str_repeat('x',2001)],['title'=>'T','body'=>'x','linkLabel'=>'Ir'],['title'=>'T','body'=>'x','linkLabel'=>'Ir','linkUrl'=>'http://inseguro.example'],['title'=>'T','body'=>'x','linkLabel'=>'Ir','linkUrl'=>'//evil.example']] as $bad) {
  $f=false;try{Announcements::create($tenants['A'],$bad);}catch(AnnouncementProblem $e){$f=true;}
  ann($f,'an invalid novelty is refused: '.json_encode(array_keys($bad)));
 }
 ann(Announcements::create($tenants['A'],['title'=>'T','body'=>'x','linkLabel'=>'Abrir','linkUrl'=>'https://agoradigital.app/guia'])>0,'a button to an https address is accepted');
 ann(Announcements::remove($tenants['B'],$id)===false&&Announcements::remove($tenants['A'],$id)===true,'only its own company can withdraw it');
 echo "PASS $checks announcement checks (real MariaDB)\n";
} finally {foreach($tenants as $t){query('DELETE FROM announcements WHERE tenant_id=?',[$t]);query('DELETE FROM tenants WHERE id=?',[$t]);}}
