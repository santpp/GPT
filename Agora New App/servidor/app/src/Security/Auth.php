<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Security;
use Agora\Core\{DB,Http,HttpError};
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
  'widgets.editar'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'], // 2.14.0 · crear fórmulas, variables y tableros (decisión del usuario: solo administración)
  'resumen.ver'=>['MASTER','SUPERADMIN'], 'espacios.administrar'=>['MASTER','SUPERADMIN'],
  'usuarios.administrar'=>['MASTER','SUPERADMIN','ADMIN'], 'ajustes.editar'=>['MASTER','SUPERADMIN','ADMIN'],
  'auditoria.ver'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'], 'equipo.metricas'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],
  'conversaciones.reasignar'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],
  'conversaciones.atender'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE'],
  'convenios.enviar'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE'],
  'datos.consultar'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','CONSULTA','USUARIO','VISOR'],
  'subcuentas.cambiar'=>['MASTER','SUPERADMIN'],
  // 2.11.0 · Facturación. Facturan también vendedores y usuarios (decisión del usuario); anular y notas, administración y supervisión.
  'facturas.ver'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','CONSULTA','USUARIO'],
  'facturas.emitir'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR','EJECUTIVO','REMITENTE','USUARIO'],
  'facturas.anular'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],
  'fiscal.configurar'=>['MASTER','SUPERADMIN','ADMIN'],
  'libro.ventas'=>['MASTER','SUPERADMIN','ADMIN','SUPERVISOR']
 ];
 public function can(string $key):bool{return $this->user!==null&&in_array($this->user['role'],self::POLICY[$key]??[],true);}
 public function permit(string $key):void{
  $role=$this->requireUser()['role'];if(!in_array($role,self::POLICY[$key]??[],true))Http::fail('Tu rol no permite esta función.',403);
  if(in_array($role,['MASTER','SUPERADMIN'],true))return;
  $roles=$this->db->setting($this->tenant,'permissions.roles',[]);$user=$this->db->setting($this->tenant,'permissions.user.'.$this->user['id'],[]);
  if(($user[$key]??$roles[$role][$key]??true)===false)Http::fail('Esta función está restringida para tu acceso.',403);
 }
 /** Agora New App · lo que esta persona puede hacer en la subcuenta activa (rol + ajustes de permisos). La pantalla lo usa para mostrar solo sus módulos; el servidor sigue comprobando cada petición. */
 public function effective():array{
  $out=[];foreach(array_keys(self::POLICY) as $k){try{$this->permit($k);$out[$k]=true;}catch(HttpError){$out[$k]=false;}}
  $out['finanzas']=in_array($this->requireUser()['role'],['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],true);
  $out['administra']=in_array($this->requireUser()['role'],['MASTER','SUPERADMIN','ADMIN'],true);
  $out['soloAsignado']=$this->assignedOnly();
  return $out;
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
  $remember=($b['recordar']??false)===true;
  // 2.13.0 · Código por correo al entrar (si el dueño eligió el correo de Ágora). Un equipo de confianza no lo vuelve a pedir.
  if($this->otpActivo()&&!$this->equipoConfiable((int)$u['id'],(int)$u['auth_version'])){
   [$desafio]=$this->codigoNuevo($u,'login',600);
   $this->audit('codigo.enviado','sesion',(string)$u['id']);
   return ['codigo'=>true,'desafio'=>$desafio,'destino'=>self::ocultarCorreo($u['email']),'recordar'=>$remember];
  }
  return $this->abrirSesion($u,$remember,false);
 }
 /** Crea la sesión (14 días si «Recuérdame»; si no, la duración normal) y, si se pide, marca el equipo como de confianza. */
 private function recordarSegundos():int{$v=(int)($this->config['remember_seconds']??0);return $v>0&&$v!==604800?$v:1209600;} // 2.13.0: 14 días (el antiguo valor por defecto de 7 días se toma como 14)
 private function abrirSesion(array $u,bool $remember,bool $confiar):array{
  $token=bin2hex(random_bytes(32));$expiry=time()+($remember?$this->recordarSegundos():(int)$this->config['session_seconds']);
  $this->db->transaction(function()use($u,$token,$expiry){if($this->session)$this->db->run('DELETE FROM agora_sessions WHERE token_hash=?',[$this->session['token_hash']]);$this->db->run('INSERT INTO agora_sessions(token_hash,user_id,auth_version,active_tenant_id,expires_at) VALUES(?,?,?,?,?)',[hash('sha256',$token),$u['id'],$u['auth_version'],$u['local_tenant_id'],gmdate('Y-m-d H:i:s',$expiry)]);$this->db->run('UPDATE agora_users SET last_login_at=UTC_TIMESTAMP() WHERE id=?',[$u['id']]);});
  $this->cookie('agora_session',$token,$remember?$expiry:0);
  if($confiar&&$remember)$this->confiarEquipo($u);
  $this->user=$u;$this->audit('entro','sesion',(string)$u['id'],$remember?['recordar'=>true]:[]);return ['usuario'=>$this->publicUser($u),'csrf'=>$this->csrf(),'activeTenantId'=>$u['local_tenant_id']?(int)$u['local_tenant_id']:null];
 }
 /* ---------- 2.13.0 · Códigos de acceso por correo, equipos de confianza y «¿Olvidaste tu clave?» ----------
    El código nunca se guarda ni se muestra: solo su huella (HMAC con app_key). Caduca, tiene 5 intentos y se usa una vez.
    El equipo de confianza es una cookie aleatoria propia del navegador (no la IP, que cambia con CGNAT y redes móviles). */
 public function otpActivo():bool{if(defined('AGORA_MODO_PRUEBAS'))return false;$c=$this->db->setting(0,'correo.canal',null);if(!is_array($c)||(int)($c['connectionId']??0)<=0||($c['codigoAlEntrar']??true)===false)return false;
  // Si la subcuenta que envía se desconectó, se entra solo con la clave (nadie queda fuera por un correo que no puede salir).
  return (bool)$this->db->one("SELECT 1 ok FROM agora_ghl_connections WHERE tenant_id=? AND status='conectada'",[(int)$c['connectionId']]);}
 /* 2.18.15 · Registro de accesos con código: qué se pidió o usó, de quién, de qué subcuenta, desde qué IP y navegador. Nunca el código.
    Si el registro falla, el acceso sigue (no se deja a nadie fuera por esto). Se conservan 6 meses. */
 public static function ipCliente():string{$ip=(string)($_SERVER['REMOTE_ADDR']??'');return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'';}
 private function registroAcceso(string $evento,string $uso,?array $u,string $correo=''):void{
  try{
   $ua=mb_substr(preg_replace('/[^\p{L}\p{N} .,;:()\/_+-]/u','',(string)($_SERVER['HTTP_USER_AGENT']??'')),0,160);
   $this->db->run('INSERT INTO agora_access_log(evento,uso,user_id,correo,tenant_id,ip,navegador) VALUES(?,?,?,?,?,?,?)',
    [$evento,$uso,$u?(int)$u['id']:null,mb_substr($u['email']??$correo,0,190),$u&&$u['local_tenant_id']?(int)$u['local_tenant_id']:null,self::ipCliente(),$ua]);
   if(random_int(1,50)===1)$this->db->run('DELETE FROM agora_access_log WHERE created_at<UTC_TIMESTAMP()-INTERVAL 183 DAY');
  }catch(\Throwable){}
 }
 private function huella(string $desafio,string $codigo):string{return hash_hmac('sha256',$desafio.'|'.$codigo,(string)$this->config['app_key']);}
 private static function ocultarCorreo(string $e):string{[$a,$d]=array_pad(explode('@',$e,2),2,'');return mb_substr($a,0,1).str_repeat('•',max(2,min(6,mb_strlen($a)-1))).'@'.$d;}
 private function codigoNuevo(array $u,string $uso,int $segundos,?string $desafio=null,bool $reenvio=false):array{
  $desafio??=bin2hex(random_bytes(24));$codigo=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
  $this->db->run('UPDATE agora_access_codes SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose=? AND used_at IS NULL AND challenge_hash<>?',[$u['id'],$uso,hash('sha256',$desafio)]);
  $this->db->run('INSERT INTO agora_access_codes(challenge_hash,user_id,purpose,code_hash,expires_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE code_hash=VALUES(code_hash),attempts=0,expires_at=VALUES(expires_at),sent_at=UTC_TIMESTAMP(),used_at=NULL',
   [hash('sha256',$desafio),$u['id'],$uso,$this->huella($desafio,$codigo),gmdate('Y-m-d H:i:s',time()+$segundos)]);
  $min=intdiv($segundos,60);
  // 2.18.14: el código va también en el asunto y al principio del texto, como lo reconocen Gmail y los avisos del teléfono para ofrecer «Copiar código».
  [$asunto,$titulo,$intro]=$uso==='reset'?["$codigo es tu código para cambiar tu clave de Ágora",'Cambia tu clave','Escribe este código en Ágora para elegir una clave nueva.']:["$codigo es tu código de acceso a Ágora",'Termina de entrar a Ágora','Escribe este código en Ágora para terminar de entrar.'];
  try{\Agora\Core\MotorMobile::correo($this->db,$this->config,$u['email'],$asunto,$titulo,$intro,$codigo,"Caduca en $min minutos y se usa una sola vez. Si no fuiste tú, ignora este correo y avisa a quien administra tu empresa.","Tu código de acceso es $codigo\n\n$intro Caduca en $min minutos y se usa una sola vez.\n");}
  catch(\Throwable $e){$this->registroAcceso('no_enviado',$uso,$u);Http::fail('No pudimos enviar el código por correo. Inténtalo de nuevo en unos minutos o avisa a quien administra Ágora.',503);}
  $this->registroAcceso($reenvio?'reenviado':'enviado',$uso,$u);
  return [$desafio];
 }
 /** Comprueba un código: 5 intentos, sin caducar y una sola vez. Devuelve la fila o falla con un mensaje común. */
 private function codigoValido(string $desafio,string $codigo,string $uso,?int $userId=null):array{
  $r=$this->db->one('SELECT * FROM agora_access_codes WHERE challenge_hash=? AND purpose=?'.($userId?' AND user_id='.(int)$userId:''),[hash('sha256',$desafio),$uso]);
  $malo='El código no es válido o ya venció. Pide uno nuevo.';
  $quien=fn()=>$r?$this->db->one('SELECT * FROM agora_users WHERE id=?',[$r['user_id']]):null;
  if(!$r||$r['used_at']!==null||strtotime($r['expires_at'].' UTC')<time()||(int)$r['attempts']>=5){$this->registroAcceso($r?'vencido':'invalido',$uso,$quien());Http::fail($malo,401);}
  $this->db->run('UPDATE agora_access_codes SET attempts=attempts+1 WHERE id=?',[$r['id']]);
  if(!preg_match('/^\d{6}$/D',$codigo)||!hash_equals($r['code_hash'],$this->huella($desafio,$codigo))){$this->registroAcceso('incorrecto',$uso,$quien());Http::fail((int)$r['attempts']>=4?$malo:'El código no coincide. Revisa el correo e inténtalo de nuevo.',401);}
  $this->db->run('UPDATE agora_access_codes SET used_at=UTC_TIMESTAMP() WHERE id=?',[$r['id']]);
  $this->registroAcceso('correcto',$uso,$quien());
  return $r;
 }
 public function loginCodigo(array $b):array{
  $this->rate('login-codigo:'.($_SERVER['REMOTE_ADDR']??''),30,900);
  $desafio=(string)($b['desafio']??'');if(!preg_match('/^[a-f0-9]{48}$/D',$desafio))Http::fail('El código no es válido o ya venció. Pide uno nuevo.',401);
  $r=$this->codigoValido($desafio,trim((string)($b['codigo']??'')),'login');
  $u=$this->db->one('SELECT * FROM agora_users WHERE id=? AND active=1',[$r['user_id']]);if(!$u)Http::fail('Correo o contraseña incorrectos.',401);
  return $this->abrirSesion($u,($b['recordar']??false)===true,true);
 }
 public function loginReenviar(array $b):array{
  $desafio=(string)($b['desafio']??'');if(!preg_match('/^[a-f0-9]{48}$/D',$desafio))Http::fail('Vuelve a escribir tu clave para pedir otro código.',401);
  $r=$this->db->one('SELECT * FROM agora_access_codes WHERE challenge_hash=? AND purpose=\'login\' AND used_at IS NULL',[hash('sha256',$desafio)]);
  if(!$r||strtotime($r['created_at'].' UTC')<time()-1800)Http::fail('Vuelve a escribir tu clave para pedir otro código.',401);
  if(strtotime($r['sent_at'].' UTC')>time()-60)Http::fail('Espera un minuto antes de pedir otro código.',429);
  $this->rate('login-reenviar:'.$r['user_id'],5,900);
  $u=$this->db->one('SELECT * FROM agora_users WHERE id=? AND active=1',[$r['user_id']]);if(!$u)Http::fail('Vuelve a escribir tu clave para pedir otro código.',401);
  $this->codigoNuevo($u,'login',600,$desafio,true);return ['ok'=>true];
 }
 private function equipoConfiable(int $userId,int $version):bool{
  $t=$_COOKIE['agora_device']??'';if(!is_string($t)||!preg_match('/^[a-f0-9]{64}$/D',$t))return false;
  $r=$this->db->one('SELECT token_hash FROM agora_trusted_devices WHERE token_hash=? AND user_id=? AND auth_version=? AND expires_at>UTC_TIMESTAMP()',[hash('sha256',$t),$userId,$version]);
  if($r)$this->db->run('UPDATE agora_trusted_devices SET last_used_at=UTC_TIMESTAMP() WHERE token_hash=?',[$r['token_hash']]);
  return (bool)$r;
 }
 private function confiarEquipo(array $u):void{
  $t=bin2hex(random_bytes(32));$exp=time()+$this->recordarSegundos();
  $ua=mb_substr(preg_replace('/[^\p{L}\p{N} .,;:()\/_-]/u','',(string)($_SERVER['HTTP_USER_AGENT']??'')),0,120);
  $this->db->run('INSERT INTO agora_trusted_devices(token_hash,user_id,auth_version,label,expires_at) VALUES(?,?,?,?,?)',[hash('sha256',$t),$u['id'],$u['auth_version'],$ua,gmdate('Y-m-d H:i:s',$exp)]);
  $this->db->run('DELETE FROM agora_trusted_devices WHERE expires_at<UTC_TIMESTAMP()');
  $this->cookie('agora_device',$t,$exp);
 }
 /** «¿Olvidaste tu clave?» · Siempre responde lo mismo, exista o no el correo (no revela quién tiene cuenta). */
 public function olvido(array $b):array{
  $email=Http::email($b);$this->rate('olvido-ip:'.($_SERVER['REMOTE_ADDR']??''),10,900);$this->rate('olvido-email:'.$email,3,900);
  $c=$this->db->setting(0,'correo.canal',null);if(defined('AGORA_MODO_PRUEBAS')||!is_array($c)||!(int)($c['connectionId']??0))return ['ok'=>true,'disponible'=>false];
  $u=$this->db->one('SELECT * FROM agora_users WHERE email=? AND active=1',[$email]);
  if(!$u)$this->registroAcceso('sin_cuenta','reset',null,$email); // 2.18.15: alguien pidió cambiar la clave de un correo que no tiene cuenta
  elseif(in_array($u['role'],['MASTER','SUPERADMIN'],true))$this->registroAcceso('dueno_bloqueado','reset',$u); // y si era la de un dueño (no se manda código)
  if($u&&in_array($u['role'],['MASTER','SUPERADMIN'],true))$u=null; // 2.13.1 · la clave de un dueño solo se cambia por terminal (misma respuesta)
  if($u){try{[$d]=$this->codigoNuevo($u,'reset',900,$this->desafioReset($u,$email));$this->audit('clave.codigo','sesion',(string)$u['id']);}catch(\Throwable){/* misma respuesta */}}
  return ['ok'=>true,'disponible'=>true];
 }
 private function desafioReset(array $u,string $email):string{return substr(hash_hmac('sha256','reset|'.$u['id'].'|'.$email,(string)$this->config['app_key']),0,48);}
 public function restablecer(array $b):array{
  $email=Http::email($b);$this->rate('restablecer-email:'.$email,10,900);$this->rate('restablecer-ip:'.($_SERVER['REMOTE_ADDR']??''),30,900);
  $nueva=Http::password($b['clave']??'');
  $u=$this->db->one('SELECT * FROM agora_users WHERE email=? AND active=1',[$email]);
  if(!$u||in_array($u['role'],['MASTER','SUPERADMIN'],true))Http::fail('El código no es válido o ya venció. Pide uno nuevo.',401);
  $desafio=$this->desafioReset($u,$email);
  $this->codigoValido($desafio,trim((string)($b['codigo']??'')),'reset',(int)$u['id']);
  $this->db->transaction(function()use($u,$nueva){
   $this->db->run('UPDATE agora_users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($nueva,PASSWORD_DEFAULT),$u['id']]);
   $this->db->run('DELETE FROM agora_sessions WHERE user_id=?',[$u['id']]);$this->db->run('DELETE FROM agora_trusted_devices WHERE user_id=?',[$u['id']]);});
  $this->user=$u;$this->audit('clave.restablecida','sesion',(string)$u['id']);$this->user=null;
  return ['ok'=>true];
 }
 public function logout():array{if($this->session)$this->db->run('DELETE FROM agora_sessions WHERE token_hash=?',[$this->session['token_hash']]);$this->cookie('agora_session','',time()-3600);return ['ok'=>true];}
 public function audit(string $action,string $module,string $target='',array $detail=[]):void{$this->db->run('INSERT INTO agora_audit(tenant_id,actor_id,actor_name,action,module,target,details,ip) VALUES(?,?,?,?,?,?,?,?)',[$this->tenant,$this->user['id']??null,$this->user['name']??'Sistema',$action,$module,$target,DB::json($detail),substr($_SERVER['REMOTE_ADDR']??'CLI',0,45)]);}
}
