<?php
declare(strict_types=1);
/** 1.10.30 · «Asignar a mí» se lo asignó a otra vendedora: quién es «yo» en la cuenta se decide primero por la elección manual
 *  del propietario, y un dato viejo del perfil o un correo repetido nunca lo hacen caer en otra persona. */
require __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/Actor.php';
$suffix=bin2hex(random_bytes(6));$checks=0;$tenant=null;
function selfCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Self test','self-'.$suffix]);$tenant=(int)db()->lastInsertId();
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,'duena-'.$suffix.'@example.test',password_hash('x',PASSWORD_DEFAULT)]);$uid=(int)db()->lastInsertId();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta','loc-'.$suffix,'not-used']);$c=(int)db()->lastInsertId();
 $mail='duena-'.$suffix.'@example.test';
 foreach([['u-duena','Dueña',$mail],['u-carla','Carla','carla@example.test'],['u-ana','Ana','ana@example.test']] as [$eid,$name,$email])
  query("INSERT INTO external_users(connection_id,external_user_id,display_name,email,permissions,scopes) VALUES(?,?,?,?,'{}','[]')",[$c,$eid,$name,$email]);
 $owner=['id'=>$uid,'identity_type'=>'native','email'=>$mail,'ghl_user_id'=>null];$m=['external_user_id'=>null];

 selfCheck(Actor::selfId($owner,$m,$c)==='u-duena','sin elegir nada, la dueña se reconoce por su correo');
 // El dato viejo del perfil apunta a Carla, pero la dueña eligió quién es: manda su elección.
 $stale=$owner+[];$stale['ghl_user_id']='u-carla';
 query('INSERT INTO owner_links(user_id,connection_id,external_user_id) VALUES(?,?,?)',[$uid,$c,'u-duena']);
 selfCheck(Actor::selfId($stale,$m,$c)==='u-duena','la elección manual va antes que el dato guardado en el perfil (antes se lo asignaba a otra vendedora)');
 query('DELETE FROM owner_links WHERE user_id=?',[$uid]);
 // Un dato del perfil que no es de esta subcuenta no se usa.
 $foreign=$owner+[];$foreign['ghl_user_id']='u-de-otra-subcuenta';
 selfCheck(Actor::selfId($foreign,$m,$c)==='u-duena','un usuario del perfil que no existe en esta subcuenta se ignora');
 // Correo repetido entre dos personas: no se adivina.
 query('UPDATE external_users SET email=? WHERE connection_id=? AND external_user_id=?',[$mail,$c,'u-ana']);
 selfCheck(Actor::selfId($owner,$m,$c)===null,'si el correo coincide con dos personas, no se adivina quién eres');
 // Un usuario sincronizado (vendedora) es siempre ella misma.
 selfCheck(Actor::selfId(['id'=>0,'identity_type'=>'external','email'=>'x'],['external_user_id'=>'u-carla'],$c)==='u-carla','una vendedora es siempre ella misma');
 echo "$checks comprobaciones".PHP_EOL;
} finally {
 if($tenant){query('DELETE FROM owner_links WHERE user_id IN (SELECT id FROM users WHERE tenant_id=?)',[$tenant]);query('DELETE FROM external_users WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$tenant]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
}
