<?php
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http};
/**
 * Agora New App · la conexión de cada subcuenta con su cuenta de GoHighLevel.
 *
 * - El token se escribe en la ficha de la subcuenta (Agencia) y se guarda cifrado; nunca vuelve
 *   a la pantalla: solo sus 4 últimos caracteres para reconocerlo.
 * - Location ID (obligatorio) y Company ID (opcional, para consumos de la agencia).
 * - «Probar conexión» lee la ubicación con ese token y comprueba que el Location ID coincide.
 * - Si una subcuenta no tiene conexión guardada se usa, como antes, la de config.php (`ghl.tenants`).
 */
final class GhlConnection {
 public const API='https://services.leadconnectorhq.com';
 public const VERSION='2021-07-28';
 private TokenVault $vault;
 public function __construct(private DB $db,private array $config){$this->vault=new TokenVault($config);}

 /** Lo que puede verse: sin token. */
 public function status(int $tenant):array{
  $r=$this->db->one('SELECT location_id,company_id,token_hint,status,location_name,checked_at,last_error FROM agora_ghl_connections WHERE tenant_id=?',[$tenant]);
  if(!$r){
   $legacy=isset($this->config['ghl']['tenants'][$tenant]);
   return ['configurada'=>$legacy,'origen'=>$legacy?'config':null,'locationId'=>$legacy?(string)($this->config['ghl']['tenants'][$tenant]['location_id']??''):null,'companyId'=>null,'token'=>$legacy?'guardado en config.php':null,'estado'=>$legacy?'sin_probar':'sin_conexion','ubicacion'=>null,'probada'=>null,'error'=>null];
  }
  return ['configurada'=>true,'origen'=>'subcuenta','locationId'=>$r['location_id'],'companyId'=>$r['company_id'],'token'=>'•••• '.$r['token_hint'],'estado'=>$r['status'],'ubicacion'=>$r['location_name'],'probada'=>$r['checked_at'],'error'=>$r['last_error']];
 }

 /** Credenciales para hablar con la cuenta (solo servidor). */
 public function credentials(int $tenant):?array{
  $r=$this->db->one('SELECT location_id,token_ciphertext FROM agora_ghl_connections WHERE tenant_id=?',[$tenant]);
  if($r)return ['token'=>$this->vault->open($r['token_ciphertext'],$tenant,$r['location_id']),'location_id'=>$r['location_id']];
  $c=$this->config['ghl']['tenants'][$tenant]??null;
  return is_array($c)&&!empty($c['token'])&&!empty($c['location_id'])?['token'=>(string)$c['token'],'location_id'=>(string)$c['location_id']]:null;
 }

 /**
  * Guarda lo que llega de la ficha. Token vacío = se conserva el guardado (si el Location ID no cambia).
  * `quitar` = true borra la conexión. Devuelve qué cambió, para la auditoría (nunca el token).
  */
 public function save(int $tenant,array $in):array{
  if(($in['quitar']??false)===true){$this->db->run('DELETE FROM agora_ghl_connections WHERE tenant_id=?',[$tenant]);return ['quitada'=>true];}
  $location=trim((string)($in['locationId']??''));$company=trim((string)($in['companyId']??''));$token=trim((string)($in['token']??''));
  $old=$this->db->one('SELECT location_id,token_ciphertext FROM agora_ghl_connections WHERE tenant_id=?',[$tenant]);
  if($location===''&&$token===''&&$company==='')return [];
  if(!preg_match('/^[A-Za-z0-9]{8,64}$/D',$location))Http::fail('El Location ID no es válido: son letras y números, sin espacios (lo ves en la cuenta, Ajustes → Perfil del negocio).');
  if($company!==''&&!preg_match('/^[A-Za-z0-9]{8,64}$/D',$company))Http::fail('El Company ID no es válido: son letras y números, sin espacios.');
  if($token!==''&&!preg_match('/^[A-Za-z0-9._-]{20,4000}$/D',$token))Http::fail('El token no tiene el formato esperado. Copia el token de la integración privada completo (empieza por «pit-»).');
  if($token===''){
   if(!$old)Http::fail('Escribe el token de la integración privada de esta subcuenta.');
   // Sin token nuevo: se vuelve a cifrar el guardado con el Location ID nuevo (el cifrado depende de él).
   $token=$this->vault->open($old['token_ciphertext'],$tenant,$old['location_id']);$changedToken=false;
  }else $changedToken=true;
  $cipher=$this->vault->seal($token,$tenant,$location);$hint=substr($token,-4);
  $this->db->run('DELETE FROM agora_ghl_connections WHERE tenant_id=?',[$tenant]);
  $this->db->run("INSERT INTO agora_ghl_connections(tenant_id,location_id,company_id,token_ciphertext,token_hint,status) VALUES(?,?,?,?,?,'sin_probar')",[$tenant,$location,$company!==''?$company:null,$cipher,$hint]);
  return ['locationId'=>$location,'companyId'=>$company!==''?$company:null,'tokenCambiado'=>$changedToken];
 }

 /** Comprueba el token leyendo la ubicación. Guarda el resultado; nunca registra el token ni la respuesta completa. */
 public function test(int $tenant):array{
  $c=$this->credentials($tenant);if(!$c)Http::fail('Esta subcuenta todavía no tiene conexión guardada.',409);
  [$status,$body]=self::request('GET','/locations/'.rawurlencode($c['location_id']),$c['token']);
  $loc=is_array($body['location']??null)?$body['location']:null;
  if($status>=200&&$status<300&&$loc&&($loc['id']??null)===$c['location_id']){$estado='conectada';$error=null;$name=is_string($loc['name']??null)?mb_substr($loc['name'],0,190):null;}
  else{$estado='error';$name=null;$error=match(true){$status===401=>'El token no es válido o fue revocado.',$status===403=>'El token no tiene permiso para leer esta ubicación (revisa los permisos de la integración privada).',$status===404||$status===400=>'No existe una ubicación con ese Location ID para este token.',$status===0=>'No se pudo conectar con la cuenta. Revisa la red del servidor.',default=>'La cuenta respondió con un error ('.$status.').'};}
  $this->db->run('UPDATE agora_ghl_connections SET status=?,location_name=?,checked_at=UTC_TIMESTAMP(),last_error=? WHERE tenant_id=?',[$estado,$name,$error,$tenant]);
  return $this->status($tenant)+($estado==='conectada'?['datos'=>$this->probe($c)]:[]);
 }

 /**
  * Qué puede leer este token: una lectura mínima (1 registro) por área. No guarda nada.
  * Anuncios y Social Planner dependen además de que la subcuenta tenga la cuenta publicitaria
  * o las redes conectadas dentro de la cuenta.
  */
 public const AREAS=[
  'contactos'=>['Contactos','/contacts/?locationId={L}&limit=1','2021-07-28'],
  'conversaciones'=>['Conversaciones','/conversations/search?locationId={L}&limit=1','2021-04-15'],
  'oportunidades'=>['Oportunidades y embudos','/opportunities/search?location_id={L}&limit=1','2021-07-28'],
  'usuarios'=>['Equipo (usuarios)','/users/?locationId={L}','2021-07-28'],
  'calendarios'=>['Calendarios y citas','/calendars/?locationId={L}','2021-04-15'],
  'facturas'=>['Facturas','/invoices/?altId={L}&altType=location&limit=1&offset=0','2021-07-28'],
  'productos'=>['Productos y precios','/products/?locationId={L}&limit=1','2021-07-28'],
  'redes'=>['Social Planner (redes conectadas)','/social-media-posting/{L}/accounts','2021-07-28'],
  'anuncios_facebook'=>['Anuncios de Facebook (Ad Manager)','/ad-publishing/facebook/reporting?locationId={L}','2021-07-28'],
 ];
 private function probe(array $c):array{
  $out=[];
  foreach(self::AREAS as $key=>[$label,$path,$version]){
   [$st]=self::request('GET',str_replace('{L}',rawurlencode($c['location_id']),$path),$c['token'],null,$version);
   $out[]=['area'=>$key,'nombre'=>$label,'http'=>$st,'estado'=>match(true){$st>=200&&$st<300=>'disponible',$st===401||$st===403=>'sin_permiso',$st===0=>'sin_red',default=>'no_disponible'}];
  }
  return $out;
 }

 /** Petición a la cuenta. En pruebas se sustituye con $GLOBALS['agoraGhlTransport']. @return array{0:int,1:mixed} */
 public static function request(string $method,string $path,string $token,?array $body=null,string $version=self::VERSION):array{
  if(isset($GLOBALS['agoraGhlTransport'])&&is_callable($GLOBALS['agoraGhlTransport']))return ($GLOBALS['agoraGhlTransport'])($method,$path,$token,$body);
  $base=getenv('APP_ENV')==='development'&&preg_match('~^http://127\.0\.0\.1:\d+$~',(string)getenv('AGORA_GHL_FAKE'))?getenv('AGORA_GHL_FAKE'):self::API; // solo desarrollo
  $h=curl_init($base.$path);
  curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>$base===self::API?CURLPROTO_HTTPS:CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_CUSTOMREQUEST=>$method,
   CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Version: '.$version,'Accept: application/json','Content-Type: application/json']]);
  if($body!==null)curl_setopt($h,CURLOPT_POSTFIELDS,DB::json($body));
  $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
  return [$raw===false?0:$status,is_string($raw)?json_decode($raw,true):null];
 }
}
