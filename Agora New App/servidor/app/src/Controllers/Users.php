<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
final class Users {
 public function __construct(public DB $db,public Auth $auth){}
 public static function image(mixed $value):?string{
  if($value===null||$value==='')return null;
  if(!is_string($value)||strlen($value)>1500000||!preg_match('~^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$~D',$value,$m))Http::fail('Imagen inválida. Usa PNG, JPEG o WebP.');
  $raw=base64_decode($m[2],true);$info=$raw?@getimagesizefromstring($raw):false;if(!$info||$info[0]>4096||$info[1]>4096||$info[0]*$info[1]>8000000)Http::fail('Dimensiones de imagen no admitidas.');
  if(!function_exists('imagecreatefromstring'))Http::fail('Activa la extensión GD en hosting.',503);
  $im=@imagecreatefromstring($raw);if(!$im)Http::fail('No se pudo procesar la imagen.');$scale=min(1,512/max($info[0],$info[1]));$out=imagecreatetruecolor(max(1,(int)($info[0]*$scale)),max(1,(int)($info[1]*$scale)));imagefill($out,0,0,imagecolorallocate($out,255,255,255));imagecopyresampled($out,$im,0,0,0,0,imagesx($out),imagesy($out),$info[0],$info[1]);ob_start();imagejpeg($out,null,85);$clean=ob_get_clean();imagedestroy($im);imagedestroy($out);return 'data:image/jpeg;base64,'.base64_encode($clean);
 }
 /** 2.1.15 · Logo de empresa: solo PNG (firma y tipo reales), proporción entre 1:3 y 6:1, mínimo 256 px en el lado largo.
  Se conserva la transparencia; si mide más de 1024 px se reduce con remuestreo de calidad. Nunca se agranda (se pixelaría). */
 public static function logo(mixed $value):?string{
  if($value===null||$value==='')return null;
  if(!is_string($value)||strlen($value)>2900000||!preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~D',$value,$m))Http::fail('El logo debe ser un archivo PNG de hasta 2 MB.');
  $raw=base64_decode($m[1],true);if(!$raw||substr($raw,0,8)!=="\x89PNG\r\n\x1a\n")Http::fail('El archivo no es un PNG válido.');
  $info=@getimagesizefromstring($raw);if(!$info||($info[2]??0)!==IMAGETYPE_PNG)Http::fail('El archivo no es un PNG válido.');
  [$w,$h]=$info;if($w>4096||$h>4096)Http::fail('El logo no puede pasar de 4096 px por lado.');
  if(max($w,$h)<256||min($w,$h)<64)Http::fail('El logo es muy pequeño y se vería pixelado. Usa al menos 256 px en el lado más largo.');
  $p=$w/$h;if($p>6||$p<1/3)Http::fail('La proporción del logo debe estar entre 1:3 y 6:1.');
  if(max($w,$h)<=1024||!function_exists('imagecreatefromstring'))return 'data:image/png;base64,'.base64_encode($raw);
  $im=@imagecreatefromstring($raw);if(!$im)Http::fail('No se pudo procesar el logo.');
  $k=1024/max($w,$h);$nw=max(1,(int)round($w*$k));$nh=max(1,(int)round($h*$k));
  $out=imagecreatetruecolor($nw,$nh);imagealphablending($out,false);imagesavealpha($out,true);imagefill($out,0,0,imagecolorallocatealpha($out,0,0,0,127));
  imagecopyresampled($out,$im,0,0,0,0,$nw,$nh,$w,$h);ob_start();imagepng($out,null,9);$png=(string)ob_get_clean();imagedestroy($im);imagedestroy($out);
  return 'data:image/png;base64,'.base64_encode($png);
 }
 /** 2.1.15 · Paleta de colores de la cuenta: {principal, acento} en #RRGGBB, o null para volver a la de Ágora. */
 public static function palette(mixed $v):?array{
  if($v===null||$v==='')return null;
  if(!is_array($v))Http::fail('Paleta inválida.');
  $out=[];foreach(['principal','acento'] as $k){$c=$v[$k]??'';if(!is_string($c)||!preg_match('/^#[0-9A-Fa-f]{6}$/D',$c))Http::fail('Color inválido en la paleta. Usa el formato #RRGGBB.');$out[$k]=strtoupper($c);}
  return $out;
 }
 public function profile(string $method,array $b):array{
  $u=$this->auth->requireUser();if($method==='GET')return ['usuario'=>$this->auth->publicUser($u)];
  if(array_diff(array_keys($b),['nombre','correo','telefono','foto','clave_actual']))Http::fail('Campo de perfil no permitido.');
  $email=array_key_exists('correo',$b)?Http::email($b):$u['email'];if($email!==$u['email']&&!password_verify((string)($b['clave_actual']??''),$u['password_hash']))Http::fail('Para cambiar el correo confirma tu contraseña actual.',403);
  $name=array_key_exists('nombre',$b)?Http::text($b,'nombre'):$u['name'];$phone=array_key_exists('telefono',$b)?Http::text($b,'telefono',40,false):$u['phone'];$photo=array_key_exists('foto',$b)?self::image($b['foto']):$u['avatar'];
  $this->db->run('UPDATE agora_users SET name=?,email=?,phone=?,avatar=? WHERE id=?',[$name,$email,$phone,$photo,$u['id']]);$this->auth->audit('perfil.actualizado','usuario',(string)$u['id']);return ['usuario'=>$this->auth->publicUser($this->db->one('SELECT * FROM agora_users WHERE id=?',[$u['id']]))];
 }
 public function listing(bool $agency):array{
  $agency?$this->auth->agency():$this->auth->manage();$args=[];$where=$agency?"role IN ('MASTER','SUPERADMIN')":'local_tenant_id=?';if(!$agency)$args[]=$this->auth->tenant;
  if($agency&&$this->auth->user['role']!=='MASTER'){$where.=' AND id=?';$args[]=$this->auth->user['id'];}
  $q=Http::text($_GET,'q',120,false);if($q!==''){$where.=' AND (name LIKE ? OR email LIKE ?)';$args[]='%'.$q.'%';$args[]='%'.$q.'%';}
  $total=(int)$this->db->one("SELECT COUNT(*) n FROM agora_users WHERE $where",$args)['n'];$args[]=max(0,(int)($_GET['cursor']??0));
  $rows=$this->db->all("SELECT * FROM agora_users WHERE $where AND id>? ORDER BY id LIMIT 101",$args);$more=count($rows)>100;if($more)array_pop($rows);
  $permissions=[];if(!$agency&&$rows){$keys=array_map(fn($u)=>'permissions.user.'.$u['id'],$rows);$slots=implode(',',array_fill(0,count($keys),'?'));foreach($this->db->all("SELECT setting_key,data FROM agora_settings WHERE tenant_id=? AND setting_key IN ($slots)",array_merge([$this->auth->tenant],$keys)) as $r)$permissions[$r['setting_key']]=json_decode($r['data'],true);}
  $items=array_map(function($u)use($permissions){$u['avatar']=null;return $this->auth->publicUser($u,$permissions['permissions.user.'.$u['id']]??[]);},$rows);
  return ['items'=>$items,'usuarios'=>$items,'total'=>$total,'hasMore'=>$more,'nextCursor'=>$more?(string)end($rows)['id']:null];
 }
 public function save(array $b,bool $agency,bool $update=false):array{
  if($agency)$this->auth->master();else $this->auth->manage();
  $u=null;if($update){$id=Http::integer($b['id']??0,1,PHP_INT_MAX);$u=$this->db->one('SELECT * FROM agora_users WHERE id=? FOR UPDATE',[$id]);if(!$u)Http::fail('Usuario no encontrado.',404);if($u['role']==='MASTER'||(int)$u['id']===(int)$this->auth->user['id'])Http::fail('Este acceso está protegido. Usa tu perfil para datos personales.',403);if($agency&&$u['role']!=='SUPERADMIN'||!$agency&&(int)$u['local_tenant_id']!==$this->auth->tenant)Http::fail('Usuario fuera de alcance.',403);}
  $role=$b['rol']??$u['role']??'USUARIO';$allowed=$agency?['SUPERADMIN']:['ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','USUARIO','CONSULTA','VISOR'];if(!in_array($role,$allowed,true))Http::fail('Rol no asignable.',403);
  $name=isset($b['nombre'])?Http::text($b,'nombre'):($u['name']??Http::text($b,'nombre'));$email=isset($b['correo'])?Http::email($b):($u['email']??Http::email($b));
  if(isset($b['estado'])&&!in_array($b['estado'],['activo','inactivo'],true))Http::fail('Estado inválido.');$active=isset($b['estado'])?($b['estado']==='activo'):($b['activo']??($u['active']??true));$hash=$u['password_hash']??null;if(isset($b['clave']))$hash=password_hash(Http::password($b['clave']),PASSWORD_DEFAULT);if(!$hash)Http::fail('La contraseña inicial es obligatoria.');
  if($u){$this->db->run('UPDATE agora_users SET name=?,email=?,role=?,active=?,password_hash=?,auth_version=auth_version+1 WHERE id=?',[$name,$email,$role,(int)(bool)$active,$hash,$u['id']]);$id=(int)$u['id'];if(!$active)$this->db->run('DELETE FROM agora_sessions WHERE user_id=?',[$id]);}
  else{$this->db->run('INSERT INTO agora_users(name,email,role,active,password_hash,local_tenant_id) VALUES(?,?,?,?,?,?)',[$name,$email,$role,(int)(bool)$active,$hash,$agency?null:$this->auth->tenant]);$id=(int)$this->db->pdo->lastInsertId();}
  if($agency){$ids=$b['tenants']??[];if(!is_array($ids)||!count($ids)||count($ids)>1000)Http::fail('Selecciona subcuentas válidas.');$this->db->run('DELETE FROM agora_assignments WHERE user_id=?',[$id]);foreach(array_unique($ids) as $t){$t=Http::integer($t,1,PHP_INT_MAX);$this->auth->authorizeTenant($t,true);$this->db->run('INSERT INTO agora_assignments(user_id,tenant_id) VALUES(?,?)',[$id,$t]);}}
  $this->auth->audit($u?'usuario.actualizado':'usuario.creado','usuario',(string)$id,['rol'=>$role,'activo'=>(bool)$active]);return ['ok'=>true,'id'=>$id];
 }
 /** Revoca el acceso de un usuario local de la subcuenta activa: lo desactiva e invalida sus sesiones. */
 public function revoke(int $id):array{
  $this->auth->manage();$u=$this->db->one('SELECT * FROM agora_users WHERE id=? AND local_tenant_id=? FOR UPDATE',[$id,$this->auth->tenant]);
  if(!$u)Http::fail('Usuario no encontrado en esta subcuenta.',404);if($u['role']==='MASTER'||(int)$u['id']===(int)$this->auth->user['id'])Http::fail('Este acceso está protegido.',403);
  $this->db->run('UPDATE agora_users SET active=0,auth_version=auth_version+1 WHERE id=?',[$id]);$this->db->run('DELETE FROM agora_sessions WHERE user_id=?',[$id]);
  $this->auth->audit('usuario.revocado','usuario',(string)$id,['antes'=>$u['active']?'activo':'inactivo','despues'=>'revocado','correo'=>$u['email']]);return ['ok'=>true,'id'=>$id];
 }
 public function permissions(string $path,string $method,array $b):array{
  $this->auth->manage();$t=$this->auth->tenant;
  if($path==='workspace/permisos'){
   if($method==='GET'){$saved=$this->db->setting($t,'permissions.roles',[]);$roles=[];foreach(['SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','CONSULTA','REMITENTE','USUARIO','VISOR'] as $role)foreach(Auth::POLICY as $k=>$allowed)$roles[$role][$k]=in_array($role,$allowed,true)&&($saved[$role][$k]??true);return ['roles'=>$roles,'restrictionOnly'=>true];}
   if($method!=='PUT')Http::fail('Método no permitido.',405);$this->auth->agency();$roles=$b['roles']??null;if(!is_array($roles))Http::fail('Roles inválidos.');
   foreach($roles as $role=>$rules){if(!in_array($role,['ADMIN','SUPERVISOR','EJECUTIVO','CONSULTA','REMITENTE','USUARIO','VISOR'])||!is_array($rules))Http::fail('Rol inválido.');$this->validateRules($role,$rules);}
   $this->db->saveSetting($t,'permissions.roles',$roles);
  }else{
   if($method!=='POST')Http::fail('Método no permitido.',405);$id=Http::integer($b['id']??0,1,PHP_INT_MAX);$u=$this->db->one('SELECT * FROM agora_users WHERE id=? AND local_tenant_id=? FOR UPDATE',[$id,$t]);if(!$u||(int)$id===(int)$this->auth->user['id'])Http::fail('Usuario protegido o fuera de alcance.',403);$rules=$b['permisos']??null;if(!is_array($rules))Http::fail('Permisos inválidos.');$this->validateRules($u['role'],$rules);$this->db->saveSetting($t,'permissions.user.'.$id,$rules);
  }$this->auth->audit('permisos.actualizados','usuario',(string)($b['id']??'roles'));return ['ok'=>true];
 }
 private function validateRules(string $role,array $rules):void{foreach($rules as $key=>$value)if(!isset(Auth::POLICY[$key])||$key==='subcuentas.cambiar'||!is_bool($value)||($value&&!in_array($role,Auth::POLICY[$key],true)))Http::fail('Los permisos solo pueden restringir el alcance del rol.');}
 public function password(array $b):array{$u=$this->auth->requireUser();if(!password_verify((string)($b['actual']??$b['clave_actual']??''),$u['password_hash']))Http::fail('Contraseña actual incorrecta.',403);$new=Http::password($b['clave_nueva']??$b['nueva']??$b['clave']??'');$this->db->run('UPDATE agora_users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);$this->db->run('DELETE FROM agora_sessions WHERE user_id=?',[$u['id']]);$this->auth->audit('clave.cambiada','usuario',(string)$u['id']);$this->auth->cookie('agora_session','',time()-3600);return ['ok'=>true,'reauthenticate'=>true];}
}
