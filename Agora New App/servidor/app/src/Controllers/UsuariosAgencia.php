<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
/*
 * 2.1.17 · Gestión unificada de usuarios para MASTER y SUPERADMIN (agency/usuarios).
 *  GET  agency/usuarios            Todos los usuarios a su alcance: agencia + subcuentas (MASTER: todas; SUPERADMIN: las asignadas).
 *  PUT  agency/usuarios            Edita nombre, correo, rol (campo rolNuevo), estado y subcuentas de cualquier usuario a su alcance.
 *  POST agency/usuarios/clave      Restablece la contraseña (una nueva escrita o una temporal generada que se muestra una sola vez).
 * Reglas: nadie edita al MASTER salvo el MASTER; nadie asigna el rol MASTER; solo el MASTER cruza la frontera
 * agencia ↔ subcuenta (crear o quitar un Superadmin); nadie se desactiva ni se cambia el rol a sí mismo.
 */
final class UsuariosAgencia {
 public const LOCALES=['ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','USUARIO','CONSULTA','VISOR'];
 public function __construct(public DB $db,public Auth $auth){}
 public function route(string $p,string $m,array $b):array{
  $this->auth->agency();
  if($p==='agency/usuarios'&&$m==='GET')return $this->listar();
  if($p==='agency/usuarios'&&$m==='PUT')return $this->guardar($b);
  if($p==='agency/usuarios/clave'&&$m==='POST')return $this->clave($b);
  Http::fail('Ruta no disponible.',404);
 }
 private function esMaster():bool{return $this->auth->user['role']==='MASTER';}
 /** Subcuentas que el editor puede tocar (MASTER: todas). */
 private function alcance():array{return array_map('intval',array_column($this->auth->tenants(),'id'));}
 private function asignadas(int $id):array{return array_map('intval',array_column($this->db->all('SELECT tenant_id FROM agora_assignments WHERE user_id=?',[$id]),'tenant_id'));}
 private function visible(array $u):bool{
  if($this->esMaster())return true;
  if(in_array($u['role'],['MASTER','SUPERADMIN'],true))return true; // el equipo de agencia se ve; las reglas de edición están en editable()
  return in_array((int)$u['local_tenant_id'],$this->alcance(),true);
 }
 private function editable(array $u):?string{
  if($u['role']==='MASTER'&&!$this->esMaster())return 'Solo el Master edita su propio acceso.';
  if(!$this->visible($u))return 'Usuario fuera de tus subcuentas.';
  return null;
 }
 private function publico(array $u,array $nombres):array{
  $ids=$u['role']==='SUPERADMIN'?$this->asignadas((int)$u['id']):($u['local_tenant_id']?[(int)$u['local_tenant_id']]:[]);
  $agencia=in_array($u['role'],['MASTER','SUPERADMIN'],true);
  return ['id'=>(int)$u['id'],'nombre'=>$u['name'],'correo'=>$u['email'],'rol'=>$u['role'],'activo'=>(bool)$u['active'],'ambito'=>$agencia?'agencia':'subcuenta',
   'subcuentaId'=>$agencia?null:(int)$u['local_tenant_id'],'subcuenta'=>$agencia?null:($nombres[(int)$u['local_tenant_id']]??''),'tenants'=>$ids,'ultimoAcceso'=>$u['last_login_at']??null,
   'yo'=>(int)$u['id']===(int)$this->auth->user['id'],'editable'=>$this->editable($u)===null,'protegido'=>$this->editable($u)];
 }
 public function listar():array{
  $where='1=1';$args=[];
  if(!$this->esMaster()){$al=$this->alcance();$slots=$al?implode(',',array_fill(0,count($al),'?')):'NULL';$where="(role IN ('MASTER','SUPERADMIN') OR local_tenant_id IN ($slots))";$args=$al;}
  $q=Http::text($_GET,'q',120,false);if($q!==''){$where.=' AND (name LIKE ? OR email LIKE ?)';$args[]='%'.$q.'%';$args[]='%'.$q.'%';}
  $total=(int)$this->db->one("SELECT COUNT(*) n FROM agora_users WHERE $where",$args)['n'];
  $rows=$this->db->all("SELECT id,name,email,role,active,local_tenant_id,last_login_at FROM agora_users WHERE $where ORDER BY CASE role WHEN 'MASTER' THEN 0 WHEN 'SUPERADMIN' THEN 1 ELSE 2 END,name LIMIT 1000",$args);
  $cuentas=[];foreach($this->auth->tenants() as $t)$cuentas[(int)$t['id']]=$t['subaccount'];
  if(!$this->esMaster())foreach($this->db->all('SELECT id,subaccount FROM agora_tenants LIMIT 1000') as $t)$cuentas[(int)$t['id']]??=$t['subaccount'];
  $mias=$this->alcance();
  return ['items'=>array_map(fn($u)=>$this->publico($u,$cuentas),$rows),'total'=>$total,'parcial'=>$total>count($rows),
   'subcuentas'=>array_values(array_map(fn($id)=>['id'=>$id,'nombre'=>$cuentas[$id]??(string)$id],$mias)),
   'roles'=>['agencia'=>[], // Agora New App: dueños y superadmin solo se crean por la terminal (bin/crear-dueno.php)
   'subcuenta'=>self::LOCALES],'esMaster'=>$this->esMaster()];
 }
 public function guardar(array $b):array{
  $id=Http::integer($b['id']??0,1,PHP_INT_MAX);
  return $this->db->transaction(function()use($id,$b){
   $u=$this->db->one('SELECT * FROM agora_users WHERE id=? FOR UPDATE',[$id]);if(!$u)Http::fail('Usuario no encontrado.',404);
   if($e=$this->editable($u))Http::fail($e,403);
   $yo=(int)$u['id']===(int)$this->auth->user['id'];
   $nombre=array_key_exists('nombre',$b)?Http::text($b,'nombre',120):$u['name'];
   $correo=array_key_exists('correo',$b)?Http::email($b):$u['email'];
   if($correo!==$u['email']&&$this->db->one('SELECT id FROM agora_users WHERE email=? AND id<>?',[$correo,$id]))Http::fail('Ese correo ya lo usa otra persona.',409);
   $rol=(string)($b['rolNuevo']??$u['role']);$activo=array_key_exists('activo',$b)?($b['activo']===true):(bool)$u['active'];
   if($rol==='MASTER'&&$u['role']!=='MASTER')Http::fail('El rol Master no se puede asignar.',403);
   if($rol==='SUPERADMIN'&&$u['role']!=='SUPERADMIN')Http::fail('Las cuentas de dueño (Superadmin) se crean solo desde la terminal del servidor: servidor/bin/crear-dueno.php.',403);
   if($yo&&$rol!==$u['role'])Http::fail('No puedes cambiar tu propio rol.',403);
   if($yo&&!$activo)Http::fail('No puedes desactivarte a ti mismo.',403);
   if($u['role']==='MASTER'&&($rol!=='MASTER'||!$activo))Http::fail('El Master no se puede desactivar ni cambiar de rol.',403);
   $eraAgencia=in_array($u['role'],['MASTER','SUPERADMIN'],true);$seraAgencia=in_array($rol,['MASTER','SUPERADMIN'],true);
   if(!$seraAgencia&&!in_array($rol,self::LOCALES,true))Http::fail('Rol no asignable.',422);
   if($eraAgencia!==$seraAgencia&&!$this->esMaster())Http::fail('Solo el Master convierte un usuario de subcuenta en Superadmin o al revés.',403);
   if($u['role']==='SUPERADMIN'&&!$this->esMaster()&&$rol!=='SUPERADMIN')Http::fail('Rol no asignable.',403);
   $al=$this->alcance();$local=null;$asignar=null;
   if($seraAgencia&&$rol==='SUPERADMIN'){
    $pedidas=$b['tenants']??($eraAgencia?$this->asignadas($id):[]);if(!is_array($pedidas)||count($pedidas)>1000)Http::fail('Subcuentas inválidas.');
    $pedidas=array_values(array_unique(array_map(fn($t)=>Http::integer($t,1,PHP_INT_MAX),$pedidas)));
    foreach($pedidas as $t)if(!in_array($t,$al,true))Http::fail('Solo asignas subcuentas que tú administras.',403);
    // Lo que el editor no ve se conserva: un Superadmin no le quita a otro subcuentas ajenas.
    $fuera=array_values(array_diff($eraAgencia?$this->asignadas($id):[],$al));$asignar=array_values(array_unique(array_merge($pedidas,$fuera)));
    if(!$asignar)Http::fail('Asigna al menos una subcuenta.');
   }elseif(!$seraAgencia){
    $local=Http::integer($b['subcuentaId']??$u['local_tenant_id']??0,1,PHP_INT_MAX);
    if(!in_array($local,$al,true))Http::fail('Solo mueves usuarios a subcuentas que tú administras.',403);
   }
   $cambios=[];foreach(['nombre'=>[$u['name'],$nombre],'correo'=>[$u['email'],$correo],'rol'=>[$u['role'],$rol],'activo'=>[(bool)$u['active'],$activo],'subcuenta'=>[$u['local_tenant_id']===null?null:(int)$u['local_tenant_id'],$seraAgencia?null:$local]] as $k=>[$a,$n])if($a!==$n)$cambios[$k]=['antes'=>$a,'despues'=>$n];
   $this->db->run('UPDATE agora_users SET name=?,email=?,role=?,active=?,local_tenant_id=?,auth_version=auth_version+1 WHERE id=?',[$nombre,$correo,$rol,(int)$activo,$seraAgencia?null:$local,$id]);
   if($asignar!==null){$this->db->run('DELETE FROM agora_assignments WHERE user_id=?',[$id]);foreach($asignar as $t)$this->db->run('INSERT INTO agora_assignments(user_id,tenant_id) VALUES(?,?)',[$id,$t]);$cambios['subcuentas']=$asignar;}
   elseif(!$seraAgencia&&$eraAgencia)$this->db->run('DELETE FROM agora_assignments WHERE user_id=?',[$id]);
   if(!$activo||isset($cambios['rol'])||isset($cambios['subcuenta'])||isset($cambios['subcuentas']))if(!$yo)$this->db->run('DELETE FROM agora_sessions WHERE user_id=?',[$id]);
   $this->auditar('usuario.editado',$u,$seraAgencia?null:$local,$cambios+['por'=>'agencia']);
   $nombres=[];foreach($this->db->all('SELECT id,subaccount FROM agora_tenants LIMIT 1000') as $t)$nombres[(int)$t['id']]=$t['subaccount'];
   return ['ok'=>true,'usuario'=>$this->publico($this->db->one('SELECT * FROM agora_users WHERE id=?',[$id]),$nombres)];
  });
 }
 public function clave(array $b):array{
  $id=Http::integer($b['id']??0,1,PHP_INT_MAX);
  $u=$this->db->one('SELECT * FROM agora_users WHERE id=?',[$id]);if(!$u)Http::fail('Usuario no encontrado.',404);
  if($e=$this->editable($u))Http::fail($e,403);
  if((int)$u['id']===(int)$this->auth->user['id'])Http::fail('Para tu propia contraseña usa Mi perfil.',403);
  $temporal=null;
  if(($b['generar']??false)===true){$abc='abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';$temporal='';for($i=0;$i<14;$i++)$temporal.=$abc[random_int(0,strlen($abc)-1)];$clave=$temporal;}
  else $clave=Http::password($b['clave']??'');
  $this->db->run('UPDATE agora_users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($clave,PASSWORD_DEFAULT),$id]);
  $this->db->run('DELETE FROM agora_sessions WHERE user_id=?',[$id]);
  $this->auditar('usuario.clave_restablecida',$u,$u['local_tenant_id']?(int)$u['local_tenant_id']:null,['modo'=>$temporal?'temporal':'manual','correo'=>$u['email']]);
  return ['ok'=>true,'temporal'=>$temporal];
 }
 /** Se audita en la agencia y, si el usuario es de una subcuenta, también en la auditoría de esa subcuenta. */
 private function auditar(string $accion,array $u,?int $tenant,array $detalle):void{
  $prev=$this->auth->tenant;
  $this->auth->tenant=null;$this->auth->audit($accion,'usuario',(string)$u['id'],$detalle);
  $origen=$u['local_tenant_id']?(int)$u['local_tenant_id']:null;
  foreach(array_unique(array_filter([$origen,$tenant])) as $t){$this->auth->tenant=$t;$this->auth->audit($accion,'usuario',(string)$u['id'],$detalle);}
  $this->auth->tenant=$prev;
 }
}
