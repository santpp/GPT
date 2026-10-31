<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/AccessChannel.php';
$s=bin2hex(random_bytes(4));$n=0;
function ak(bool $ok,string $l):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
query("INSERT INTO tenants(name,location_id) VALUES(?,?)",['Origin '.$s,'org-'.$s]);$t=(int)db()->lastInsertId();
try{
  $mk=function(string $name,string $status)use($t,$s){query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,?)",[$t,$name,'loc-'.$name.'-'.$s,'x',$status]);return (int)db()->lastInsertId();};
  $a=$mk('A','connected');$b=$mk('B','connected');$c=$mk('C','untested');
  $member=fn(int $conn,string $email,int $active)=>query("INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,active) VALUES(?,?,?,?,'{}','[]',?)",[$conn,'u'.$conn.$s,$email,'Persona',$active]);
  $member($b,'ana@example.test',1);$member($c,'luis@example.test',1);$member($a,'baja@example.test',0);
  ak((int)AccessChannel::connectionFor($t,'  ANA@example.test ')['id']===$b,'a team member\'s code is delivered from the sub-account they belong to');
  ak(AccessChannel::connectionFor($t,'luis@example.test')===null,'a sub-account that has not been verified is not used; the chosen channel is the fallback');
  ak(AccessChannel::connectionFor($t,'baja@example.test')===null,'an inactive member does not pick a sub-account');
  ak(AccessChannel::connectionFor($t,'nadie@example.test')===null,'an unknown address falls back to the chosen channel');
}finally{
  query('DELETE FROM external_users WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$t]);
  query('DELETE FROM ghl_connections WHERE tenant_id=?',[$t]);query('DELETE FROM tenants WHERE id=?',[$t]);
}
echo "PASS $n access origin checks\n";
