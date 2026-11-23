<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http,Errores};use Agora\Security\Auth;
/*
 * 2.19.0 · Bitácora de errores y reportes (pedido del usuario).
 *  POST reportes                      Cualquier persona con sesión reporta un problema: su texto, la pantalla, la versión y
 *                                     los últimos errores que vio (código y referencia). Nada de claves, tokens ni datos de clientes.
 *  GET  agency/errores                Solo dueños: reportes y errores del servidor, cada código con su razón, y qué errores del
 *                                     servidor coinciden con las referencias de cada reporte.
 *  PUT  agency/errores/reporte        Solo dueños: marca un reporte como nuevo, revisado o resuelto.
 *  GET  agency/errores/diccionario    Solo dueños: el diccionario completo (áreas, tipos y errores concretos).
 */
final class Bitacora {
 public function __construct(private DB $db,private Auth $auth){}
 public function reportar(array $b):array{
  $u=$this->auth->requireUser();$this->auth->rate('reporte:'.$u['id'],10,3600);
  $txt=static fn($v,int $max):string=>mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',is_string($v)?$v:'')),0,$max);
  $errores=[];foreach(array_slice(is_array($b['errores']??null)?$b['errores']:[],0,20) as $e){if(!is_array($e))continue;
   $cod=(string)($e['codigo']??'');if(!preg_match('/^AG-[A-Z]{3}-\d{3}[A-Z]?$/D',$cod))$cod='AG-NAV-900';
   $ref=(string)($e['referencia']??'');if(!preg_match('/^[a-f0-9]{8,32}$/D',$ref))$ref='';
   $errores[]=['codigo'=>$cod,'referencia'=>$ref,'mensaje'=>$txt($e['mensaje']??'',300),'ruta'=>$txt($e['ruta']??'',160),'hora'=>$txt($e['hora']??'',40),'status'=>(int)($e['status']??0)];}
  $lentas=[];foreach(array_slice(is_array($b['lentas']??null)?$b['lentas']:[],0,15) as $l){if(!is_array($l))continue;$lentas[]=['ruta'=>$txt($l['ruta']??'',160),'ms'=>max(0,min(600000,(int)($l['ms']??0))),'status'=>(int)($l['status']??0)];}
  $desc=$txt($b['descripcion']??'',1000);
  if($desc===''&&!$errores)Http::fail('Cuéntanos qué pasó o espera a que aparezca el error para reportarlo.',422);
  $this->db->run('INSERT INTO agora_error_reports(user_id,tenant_id,descripcion,pantalla,version_app,dispositivo,errores,lentas,ip) VALUES(?,?,?,?,?,?,?,?,?)',
   [$u['id'],$this->auth->tenant?:($u['local_tenant_id']?(int)$u['local_tenant_id']:null),$desc,$txt($b['pantalla']??'',160),$txt($b['version']??'',20),$txt($b['dispositivo']??'',160),DB::json($errores),DB::json($lentas),Auth::ipCliente()]);
  $id=(int)$this->db->pdo->lastInsertId();
  \Agora\Services\Notificaciones::aDuenos($this->db,'reporte','Reporte de un problema (R-'.$id.')',($u['name']??'Alguien').': '.($desc!==''?mb_substr($desc,0,120):'sin descripción'),'/agencia/errores','reporte:'.$id); // 2.20.0
  if(random_int(1,50)===1)$this->db->run("DELETE FROM agora_error_reports WHERE created_at<UTC_TIMESTAMP()-INTERVAL 180 DAY AND estado='resuelto'");
  return ['ok'=>true,'id'=>$id,'referencia'=>'R-'.$id];
 }
 private static function dias():int{$d=(int)($_GET['dias']??7);return in_array($d,[1,7,30,90,180],true)?$d:7;}
 public function listar():array{
  $this->auth->agency();$dias=self::dias();$q=trim(mb_substr((string)($_GET['q']??''),0,80));$like='%'.addcslashes($q,'%_\\').'%';
  $w="r.created_at>=UTC_TIMESTAMP()-INTERVAL $dias DAY";$a=[];
  $est=(string)($_GET['estado']??'');if($est!==''){if(!in_array($est,['nuevo','revisado','resuelto'],true))Http::fail('Filtro no válido.',422);$w.=' AND r.estado=?';$a[]=$est;}
  if($q!==''){$w.=' AND (r.descripcion LIKE ? OR r.errores LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR t.name LIKE ? OR r.pantalla LIKE ? OR CONCAT(\'R-\',r.id)=?)';array_push($a,$like,$like,$like,$like,$like,$like,$q);}
  $reps=$this->db->all("SELECT r.*,u.name nombre,u.email correo,u.role rol,t.name subcuenta FROM agora_error_reports r LEFT JOIN agora_users u ON u.id=r.user_id LEFT JOIN agora_tenants t ON t.id=r.tenant_id WHERE $w ORDER BY r.id DESC LIMIT 101",$a);
  $masR=count($reps)>100;if($masR)array_pop($reps);
  // Errores del servidor que coinciden con las referencias de los reportes.
  $refs=[];foreach($reps as &$r){$r['errores']=json_decode((string)$r['errores'],true)?:[];$r['lentas']=json_decode((string)$r['lentas'],true)?:[];foreach($r['errores'] as $e)if($e['referencia']!=='')$refs[$e['referencia']]=1;}unset($r);
  $hall=[];if($refs){$ph=implode(',',array_fill(0,count($refs),'?'));foreach($this->db->all("SELECT * FROM agora_error_log WHERE referencia IN ($ph)",array_keys($refs)) as $l)$hall[$l['referencia']]=$l;}
  $w2="l.created_at>=UTC_TIMESTAMP()-INTERVAL $dias DAY";$a2=[];
  if($q!==''){$w2.=' AND (l.referencia=? OR l.codigo LIKE ? OR l.ruta LIKE ? OR l.mensaje LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR t.name LIKE ? OR l.ip LIKE ?)';array_push($a2,$q,$like,$like,$like,$like,$like,$like,$like);}
  $solo=(string)($_GET['tipo']??'');if($solo==='servidor')$w2.=' AND l.status>=500';
  $errs=$this->db->all("SELECT l.*,u.name nombre,u.email correo,t.name subcuenta FROM agora_error_log l LEFT JOIN agora_users u ON u.id=l.user_id LEFT JOIN agora_tenants t ON t.id=l.tenant_id WHERE $w2 ORDER BY l.id DESC LIMIT 201",$a2);
  $masE=count($errs)>200;if($masE)array_pop($errs);
  $fecha=static fn(?string $d):?string=>$d?gmdate('c',strtotime($d.' UTC')):null;
  $exp=static fn(string $c):array=>Errores::explicar($c)??['codigo'=>$c,'titulo'=>'Código desconocido','razon'=>'Este código no está en el diccionario.','hacer'=>'','area'=>''];
  $err=fn(array $l):array=>['referencia'=>$l['referencia'],'fecha'=>$fecha($l['created_at']),'codigo'=>$l['codigo'],'explicacion'=>$exp($l['codigo']),'status'=>(int)$l['status'],'metodo'=>$l['metodo'],'ruta'=>$l['ruta'],'mensaje'=>$l['mensaje'],'tecnico'=>$l['tecnico'],'ip'=>$l['ip'],'nombre'=>$l['nombre']??null,'correo'=>$l['correo']??null,'subcuenta'=>$l['subcuenta']??null];
  $res=$this->db->one("SELECT COUNT(*) n,SUM(status>=500) graves FROM agora_error_log WHERE created_at>=UTC_TIMESTAMP()-INTERVAL $dias DAY")?:[];
  $nuevos=(int)($this->db->one("SELECT COUNT(*) n FROM agora_error_reports WHERE estado='nuevo'")['n']??0);
  $top=$this->db->all("SELECT codigo,COUNT(*) n FROM agora_error_log WHERE created_at>=UTC_TIMESTAMP()-INTERVAL $dias DAY GROUP BY codigo ORDER BY n DESC LIMIT 5");
  return ['dias'=>$dias,'resumen'=>['reportesNuevos'=>$nuevos,'errores'=>(int)($res['n']??0),'graves'=>(int)($res['graves']??0),'frecuentes'=>array_map(fn($t)=>['codigo'=>$t['codigo'],'n'=>(int)$t['n'],'titulo'=>$exp($t['codigo'])['titulo']],$top)],
   'reportes'=>array_map(fn($r)=>['id'=>(int)$r['id'],'referencia'=>'R-'.$r['id'],'fecha'=>$fecha($r['created_at']),'estado'=>$r['estado'],'descripcion'=>$r['descripcion'],'pantalla'=>$r['pantalla'],'version'=>$r['version_app'],'dispositivo'=>$r['dispositivo'],'ip'=>$r['ip'],
     'nombre'=>$r['nombre'],'correo'=>$r['correo'],'rol'=>$r['rol'],'subcuenta'=>$r['subcuenta']??(in_array($r['rol'],['MASTER','SUPERADMIN'],true)?'Agencia':null),'lentas'=>$r['lentas'],'revisado'=>$fecha($r['revisado_at']),
     'errores'=>array_map(fn($e)=>$e+['explicacion'=>$exp($e['codigo']),'servidor'=>isset($hall[$e['referencia']])?$err($hall[$e['referencia']]+['nombre'=>null,'correo'=>null,'subcuenta'=>null]):null],$r['errores'])],$reps),
   'masReportes'=>$masR,'errores'=>array_map($err,$errs),'masErrores'=>$masE];
 }
 public function estado(array $b):array{
  $this->auth->agency();$id=Http::integer($b['id']??0,1,PHP_INT_MAX);$est=(string)($b['estado']??'');
  if(!in_array($est,['nuevo','revisado','resuelto'],true))Http::fail('Estado no válido.',422);
  if(!$this->db->one('SELECT id FROM agora_error_reports WHERE id=?',[$id]))Http::fail('Ese reporte no existe.',404);
  $this->db->run('UPDATE agora_error_reports SET estado=?,revisado_por=?,revisado_at='.($est==='nuevo'?'NULL':'UTC_TIMESTAMP()').' WHERE id=?',[$est,$est==='nuevo'?null:$this->auth->user['id'],$id]);
  return ['ok'=>true,'estado'=>$est];
 }
 public function diccionario():array{$this->auth->agency();return Errores::diccionario();}
}
