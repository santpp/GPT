<?php
declare(strict_types=1);
// Agora New App · conexión de una subcuenta con su cuenta: cifrado, validaciones y «Probar conexión».
// Uso: AGORA_CONFIG=/ruta/config.php php tests/ghl-conexion.php  (base MySQL de pruebas, nunca la real)
require dirname(__DIR__).'/servidor/bootstrap.php';
use Agora\Core\{DB,HttpError};use Agora\Services\{GhlConnection,TokenVault};
$db=new DB($config['db']);$n=0;
function ok(bool $c,string $m):void{global $n;if(!$c)throw new RuntimeException('FALLA: '.$m);$n++;echo "PASS $m\n";}
function falla(callable $f):?string{try{$f();return null;}catch(HttpError $e){return $e->getMessage();}}
$code='T'.strtoupper(bin2hex(random_bytes(3)));
$db->run("INSERT INTO agora_tenants(name,subaccount,code) VALUES(?,?,?)",['Prueba conexión','Prueba',$code]);$t=(int)$db->pdo->lastInsertId();
try{
 $g=new GhlConnection($db,$config);
 ok($g->status($t)['estado']==='sin_conexion','una subcuenta nueva no tiene conexión');
 ok(str_contains((string)falla(fn()=>$g->save($t,['locationId'=>'LOC1234567890abc','token'=>''])),'Escribe el token'),'la primera vez el token es obligatorio');
 ok(str_contains((string)falla(fn()=>$g->save($t,['locationId'=>'mal id','token'=>'pit-xxxxxxxxxxxxxxxxxxxxxxxx'])),'Location ID'),'un Location ID con espacios se rechaza');
 ok(str_contains((string)falla(fn()=>$g->save($t,['locationId'=>'LOC1234567890abc','token'=>'corto'])),'token'),'un token con formato raro se rechaza');
 $tok='pit-0f1e2d3c-aaaa-bbbb-cccc-1234567890ab';
 $g->save($t,['locationId'=>'LOC1234567890abc','token'=>$tok]);
 $row=$db->one('SELECT * FROM agora_ghl_connections WHERE tenant_id=?',[$t]);
 ok(!str_contains($row['token_ciphertext'],'pit-')&&$row['token_hint']==='90ab','el token se guarda cifrado; solo quedan sus 4 últimos caracteres');
 ok(!str_contains(json_encode($g->status($t)),$tok),'el estado que ve la pantalla no contiene el token');
 ok($g->credentials($t)['token']===$tok,'el servidor sí puede abrirlo para hablar con la cuenta');
 $v=new TokenVault($config);$otro=0;try{$v->open($row['token_ciphertext'],$t+1,'LOC1234567890abc');}catch(RuntimeException){$otro=1;}
 ok($otro===1,'copiado a otra subcuenta, el token cifrado no se abre');
 $g->save($t,['locationId'=>'LOCnuevo1234567','token'=>'']);
 ok($g->credentials($t)===['token'=>$tok,'location_id'=>'LOCnuevo1234567'],'cambiar el Location ID sin escribir el token conserva el token');
 $calls=[];
 $GLOBALS['agoraGhlTransport']=function($m,$p,$token,$b)use(&$calls){$calls[]=[$m,$p,$token];return [200,['location'=>['id'=>'LOCnuevo1234567','name'=>'ASOCIM Admisiones']]];};
 $r=$g->test($t);
 ok($r['estado']==='conectada'&&$r['ubicacion']==='ASOCIM Admisiones','«Probar conexión» lee la ubicación y la da por conectada');
 ok($calls[0]===['GET','/locations/LOCnuevo1234567',$tok],'pregunta por su propio Location ID con su token');
 ok(count($r['datos'])===count(GhlConnection::AREAS)&&$r['datos'][0]['estado']==='disponible','al conectar, dice qué áreas puede leer el token (contactos, conversaciones, anuncios, redes…)');
 ok(!str_contains(json_encode($r),$tok),'la respuesta de la prueba tampoco contiene el token');
 $GLOBALS['agoraGhlTransport']=fn()=>[401,['message'=>'Invalid JWT']];
 ok($g->test($t)['error']==='El token no es válido o fue revocado.','un token revocado se explica en palabras');
 $GLOBALS['agoraGhlTransport']=fn()=>[200,['location'=>['id'=>'OTRA0000000000','name'=>'Otra']]];
 ok($g->test($t)['estado']==='error','si la cuenta devuelve otra ubicación, no se da por conectada');
 $g->save($t,['quitar'=>true]);
 ok($g->status($t)['estado']==='sin_conexion','«Quitar la conexión» la borra');
 echo "$n comprobaciones\n";
}finally{unset($GLOBALS['agoraGhlTransport']);$db->run('DELETE FROM agora_ghl_connections WHERE tenant_id=?',[$t]);$db->run('DELETE FROM agora_tenants WHERE id=?',[$t]);}
