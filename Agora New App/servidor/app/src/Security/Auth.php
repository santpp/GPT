<?php
declare(strict_types=1);
namespace Agora\Security;
use Agora\Core\{DB,Http};
final class Auth {
 public ?array $user=null; public ?array $session=null; public ?int $tenant=null;
 public function __construct(public DB $db,public array $config){
  $token=$_COOKIE['agora_session']??'';
  if(is_string($token)&&preg_match('/^[a-f0-9]{64}$/D',$token)){
   $s=$db->one('SELECT s.*,u.auth_version AS current_version,u.active FROM agora_sessions s JOIN agora_users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP()',[hash('sha256',$token)]);
   if($s&&$s['active']&&(int)$s['auth_version']===(int)$s['current_version']){$this->session=$s;$this->user=$db->one('SELECT * FROM agora_users WHERE id=?',[$s['user_id']]);}
  }
 }
 public function cookie(string $name,string $value,int $expires=0):void{setcookie($name,$value,['expires'=>$expires,'path'=>'/','secure'=>(bool)$this->config['cookie_secure'],'httponly'=>true,'samesite'=>'Lax']);}
 public function csrf():string{$v=$_COOKIE['agora_csrf']??'';if(!is_string($v)||!$this->validCsrf($v)){$nonce=bin2hex(random_bytes(24));$v=$nonce.'.'.hash_hmac('sha256',$nonce,$this->config['app_key']);$this->cookie('agora_csrf',$v);$_COOKIE['agora_csrf']=$v;}return $v;}
 private function validCsrf(string $s):bool{$p=explode('.',$s);return count($p)===2&&strlen($p[0])===48&&hash_equals(hash_hmac('sha256',$p[0],$this->config['app_key']),$p[1]);}
 public function assertCsrf():void{$cookie=$_COOKIE['agora_csrf']??'';$header=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($cookie)||!$this->validCsrf($cookie)||!hash_equals($cookie,$header))Http::fail('Actualiza la página y vuelve a intentarlo (CSRF).',419);$origin=$_SERVER['HTTP_ORIGIN']??'';if($origin!==''&&rtrim($origin,'/')!==rtrim($this->config['app_url'],'/'))Http::fail('Origen no autorizado: la dirección de esta página no coincide con app_url en config.php del servidor.',403);}
 public function rate(string $key,int $limit,int $seconds):void{
  $bucket=hash_hmac('sha256',$key,$this->config['app_key']);
  $this->db->run('INSERT INTO agora_rate_limits(bucket,hits,expires_at) VALUES(?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE hits=IF(expires_at<UTC_TIMESTAMP(),1,hits+1),expires_at=IF(expires_at<UTC_TIMESTAMP(),VALUES(expires_at),expires_at)',[$bucket,$seconds]);
  if((int)$this->db->one('SELECT hits FROM agora_rate_limits WHERE bucket=?',[$bucket])['hits']>$limit){header('Retry-After: '.$seconds);Http::fail('Demasiados intentos. Intenta más tarde.',429);}
 }
 public function requireUser():array{if(!$this->user)Http::fail('Debes iniciar sesión.',401);return $this->user;}
 public function agency():void{$u=$this->requireUser();if(!in_array($u['role'],['MASTER','SUPERADMIN'],true))Http::fail('Acceso exclusivo de agencia.',403);}
 public function master():void{if($this->requireUser()['role']!=='MASTER')Http::fail('Solo el dueño Master.',403);}
 public function manage():void{if(!in_array($this->requireUser()['role'],['MASTER','SUPERADMIN','ADMIN'],true))Http::fail('Requiere administración.',403);}
 public function write():void{if(in_array($this->requireUser()['role'],['USUARIO','CONSULTA','VISOR'],true))Http::fail('Tu rol es de consulta.',403);}
 public function financial():void{if(!in_array($this->requireUser()['role'],['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],true))Http::fail('No tienes acceso a información financiera global.',403);}
 public function assignedOnly():bool{return in_array($this->requireUser()['role'],['USUARIO','CONSULTA','VISOR','REMITENTE','EJECUTIVO'],true);}
 public const POLICY=[
  'resumen.ver'=>['MASTER','SUPERADMIN'], 'espacios.administrar'=>['MASTER','SUPERADMIN'],
  'usuarios.administrar'=>['MASTER','SUPERADMIN','ADMIN'], 'ajustes.editar'=>['MASTER','SUPERADMIN','ADMIN'],
  'auditoria.ver'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'], 'equipo.metricas'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],
  'conversaciones.reasignar'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],
  'conversaciones.atender'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE'],
  'convenios.enviar'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE'],
  'datos.consultar'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','CONSULTA','USUARIO','VISOR'],
  'subcuentas.cambiar'=>['MASTER','SUPERADMIN']
 ];
 public function permit(string $key):void{
  $role=$this->requireUser()['role'];if(!in_array($role,self::POLICY[$key]??[],true))Http::fail('Tu rol no permite esta función.',403);
  if(in_array($role,['MASTER','SUPERADMIN'],true))return;
  $roles=$this->db->setting($this->tenant,'permissions.roles',[]);$user=$this->db->setting($this->tenant,'permissions.user.'.$this->user['id'],[]);
  if(($user[$key]??$roles[$role][$key]??true)===false)Http::fail('Esta función está restringida para tu acceso.',403);
 }
 public function authorizeTenant(int $id,bool $allowPaused=false):array{
  $u=$this->requireUser();$t=$this->db->one('SELECT * FROM agora_tenants WHERE id=?',[$id]);if(!$t)Http::fail('Subcuenta no disponible.',404);
  $ok=$u['role']==='MASTER'||($u['role']==='SUPERADMIN'&&$this->db->one('SELECT user_id FROM agora_assignments WHERE user_id=? AND tenant_id=?',[$u['id'],$id]))||((int)$u['local_tenant_id']===$id);
  if(!$ok)Http::fail('Subcuenta no autorizada.',403);
  if(!$allowPaused&&$t['status']!=='activa')Http::fail('Esta subcuenta está suspendida o pendiente.',403);
  return $t;
 }
 public function workspace(bool $allowPaused=false):int{
  $id=Http::integer($_SERVER['HTTP_X_WORKSPACE_ID']??0,1,PHP_INT_MAX);$this->authorizeTenant($id,$allowPaused);
  if(isset($_GET['connectionId'])&&(string)$_GET['connectionId']!==(string)$id)Http::fail('La subcuenta cambió. Recarga la pantalla.',409);
  return $this->tenant=$id;
 }
 public function tenants():array{$u=$this->requireUser();if($u['role']==='MASTER')return $this->db->all('SELECT * FROM agora_tenants ORDER BY id LIMIT 1000');if($u['role']==='SUPERADMIN')return $this->db->all('SELECT t.* FROM agora_tenants t JOIN agora_assignments a ON a.tenant_id=t.id WHERE a.user_id=? ORDER BY t.id LIMIT 1000',[$u['id']]);return $this->db->all('SELECT * FROM agora_tenants WHERE id=?',[$u['local_tenant_id']]);}
 public function publicUser(array $u,?array $permissions=null):array{
  $ids=$u['role']==='SUPERADMIN'?array_column($this->db->all('SELECT tenant_id FROM agora_assignments WHERE user_id=?',[$u['id']]),'tenant_id'):($u['local_tenant_id']?[$u['local_tenant_id']]:[]);
  return ['id'=>(int)$u['id'],'nombre'=>$u['name'],'correo'=>$u['email'],'telefono'=>$u['phone'],'foto'=>$u['avatar'],'rol'=>$u['role'],'activo'=>(bool)$u['active'],'estado'=>$u['active']?'activo':'inactivo','tenants'=>array_map('intval',$ids),'permisos'=>$permissions??($u['local_tenant_id']?$this->db->setting((int)$u['local_tenant_id'],'permissions.user.'.$u['id'],[]):[]),'origen'=>'sistema','yo'=>(int)$u['id']===(int)($this->user['id']??0)];
 }
 public function me():array{$u=$this->requireUser();$p=$this->publicUser($u);return $p+['csrf'=>$this->csrf(),'activeTenantId'=>$u['local_tenant_id']?(int)$u['local_tenant_id']:null,'user'=>['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>in_array($u['role'],['MASTER','SUPERADMIN','ADMIN'])?'administrator':'member','capabilities'=>['viewConversations'=>true,'sendMessages'=>!in_array($u['role'],['USUARIO','CONSULTA','VISOR']),'assignedDataOnly'=>$this->assignedOnly()]]];}
 public function login(array $b):array{
  if(defined('AGORA_MODO_PRUEBAS')&&is_string($b['correo']??null)&&!str_contains($b['correo'],'@'))$b['correo']=trim($b['correo']).'@agora.test'; // modo pruebas: «admin» = admin@agora.test
  $email=Http::email($b);$pw=(string)($b['clave']??'');if(strlen($pw)>72)Http::fail('Credenciales inválidas.',401);
  $this->rate('login-ip:'.($_SERVER['REMOTE_ADDR']??''),40,900);$this->rate('login-email:'.$email,15,900);
  $u=$this->db->one('SELECT * FROM agora_users WHERE email=?',[$email]);$dummy='$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
  if(!password_verify($pw,$u['password_hash']??$dummy)||!($u['active']??false))Http::fail('Correo o contraseña incorrectos.',401);
  $remember=($b['recordar']??false)===true;$token=bin2hex(random_bytes(32));$expiry=time()+($remember?(int)($this->config['remember_seconds']??604800):(int)$this->config['session_seconds']);
  $this->db->transaction(function()use($u,$token,$expiry){if($this->session)$this->db->run('DELETE FROM agora_sessions WHERE token_hash=?',[$this->session['token_hash']]);$this->db->run('INSERT INTO agora_sessions(token_hash,user_id,auth_version,active_tenant_id,expires_at) VALUES(?,?,?,?,?)',[hash('sha256',$token),$u['id'],$u['auth_version'],$u['local_tenant_id'],gmdate('Y-m-d H:i:s',$expiry)]);$this->db->run('UPDATE agora_users SET last_login_at=UTC_TIMESTAMP() WHERE id=?',[$u['id']]);});
  $this->cookie('agora_session',$token,$remember?$expiry:0);$this->user=$u;$this->audit('entro','sesion',(string)$u['id'],$remember?['recordar'=>true]:[]);return ['usuario'=>$this->publicUser($u),'csrf'=>$this->csrf(),'activeTenantId'=>$u['local_tenant_id']?(int)$u['local_tenant_id']:null];
 }
 public function logout():array{if($this->session)$this->db->run('DELETE FROM agora_sessions WHERE token_hash=?',[$this->session['token_hash']]);$this->cookie('agora_session','',time()-3600);return ['ok'=>true];}
 public function audit(string $action,string $module,string $target='',array $detail=[]):void{$this->db->run('INSERT INTO agora_audit(tenant_id,actor_id,actor_name,action,module,target,details,ip) VALUES(?,?,?,?,?,?,?,?)',[$this->tenant,$this->user['id']??null,$this->user['name']??'Sistema',$action,$module,$target,DB::json($detail),substr($_SERVER['REMOTE_ADDR']??'CLI',0,45)]);}
}
