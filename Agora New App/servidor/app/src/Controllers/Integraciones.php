<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
/*
 * 2.1.19 · Ajustes → «Cuentas conectadas». Antes estas rutas no existían en el servidor PHP y los botones
 * de la pantalla daban «Ruta o método no implementado». Ahora:
 *  - lo que es una preferencia se guarda (moneda, recordatorio de no leídos, alerta de ventas sin monto,
 *    comisión, carpetas de campos, número de WhatsApp, actualización automática, canal de acceso);
 *  - lo que necesita la conexión externa (probar, sincronizar usuarios, revisar WhatsApp) responde con un
 *    mensaje claro de qué falta configurar.
 * Todo en agora_settings de la subcuenta, clave «integracion».
 */
final class Integraciones {
 private const NO_CONECTADA='Esta subcuenta todavía no tiene una cuenta externa conectada en el servidor. Mientras tanto, los datos se cargan desde Ajustes (importar reporte) o con Excel en Migración de datos.';
 public function __construct(public DB $db,public Auth $auth,public array $config){}

 public function route(string $p,string $m,array $b):array{
  $this->auth->manage();
  if($p==='admin/access-channel'&&$m==='PUT'){$id=($b['connectionId']??null)===null?null:Http::integer($b['connectionId'],1,PHP_INT_MAX);if($id)$this->auth->authorizeTenant($id);$this->db->saveSetting(0,'acceso.canal',['connectionId'=>$id]);$this->auth->audit('acceso.canal','ajuste',(string)$id);return ['ok'=>true,'connectionId'=>$id];}
  if($p==='admin/access-channel/test'&&$m==='POST')Http::fail('El envío de correos no está configurado en este servidor. Configura el correo de salida (SMTP) para poder probar el canal de acceso.',503);
  if($p==='admin/cron-url')Http::fail('En CyberPanel la revisión periódica se programa con una tarea cron del servidor (ver INSTALAR-CYBERPANEL.md, sección «Tareas programadas»). No hace falta una dirección pública.',503);
  if($p==='admin/installation/encryption'&&$m==='POST')Http::fail('El cifrado de la instalación se configura en servidor/config/config.php. Revisa INSTALAR-CYBERPANEL.md.',503);
  if($p==='admin/integrations'&&$m==='POST')Http::fail('Las subcuentas nuevas se crean desde Agencia → «Nueva subcuenta». La conexión con una cuenta externa requiere su clave de acceso en el servidor.',503);
  if(!preg_match('~^admin/integrations/(\d+)(?:/(.+))?$~D',$p,$x))Http::fail('Ruta no disponible.',404);
  $id=(int)$x[1];$this->auth->authorizeTenant($id);$que=$x[2]??'';
  $cfg=$this->db->setting($id,'integracion',[]);if(!is_array($cfg))$cfg=[];
  $guardar=function(array $nuevo,string $accion)use($id,&$cfg){$cfg=array_replace($cfg,$nuevo);$this->db->saveSetting($id,'integracion',$cfg);$this->auth->audit($accion,'ajuste',(string)$id,$nuevo);};
  $conectada=isset($this->config['ghl']['tenants'][$id]);
  switch($que.' '.$m){
   case ' PUT':{$nombre=Http::text($b,'name',120,false);if($nombre!==''){$this->db->run('UPDATE agora_tenants SET subaccount=?,revision=revision+1 WHERE id=?',[$nombre,$id]);$this->auth->audit('subcuenta.nombre','ajuste',(string)$id);}return ['ok'=>true];}
   case ' DELETE':Http::fail('Las subcuentas no se borran desde aquí: se pausan o archivan desde Agencia → Centro de mando.',409);
   case 'test POST':if(!$conectada)Http::fail(self::NO_CONECTADA,503);return ['ok'=>true,'message'=>'La conexión responde.'];
   case 'schedule POST':{$on=($b['enabled']??false)===true;$guardar(['sync_enabled'=>$on],'integracion.horario');return ['ok'=>true,'enabled'=>$on];}
   case 'commission GET':return $this->comision($cfg);
   case 'commission PUT':{
    $kind=in_array($b['kind']??'',['percent','per_closing'],true)?$b['kind']:Http::fail('Elige cómo se paga la comisión.');
    $amount=filter_var($b['amount']??null,FILTER_VALIDATE_FLOAT);if($amount===false||$amount<0||($kind==='percent'&&$amount>100)||$amount>1e9)Http::fail($kind==='percent'?'El porcentaje va de 0 a 100.':'Revisa el monto por cierre.');
    $hist=$cfg['comisiones']??[];$mes=gmdate('Y-m');$hist=array_values(array_filter($hist,fn($h)=>($h['since']??'')!==$mes));$hist[]=['kind'=>$kind,'amount'=>round((float)$amount,2),'since'=>$mes];
    $guardar(['comisiones'=>array_slice($hist,-24)],'integracion.comision');return $this->comision($cfg);
   }
   case 'won-alert PUT':{$d=Http::integer($b['days']??0,0,90);$guardar(['won_alert_days'=>$d],'integracion.alerta');return ['ok'=>true,'days'=>$d];}
   case 'reminder PUT':{$n=Http::integer($b['minutes']??0,0,1440);$guardar(['unread_reminder_minutes'=>$n],'integracion.recordatorio');return ['ok'=>true,'minutes'=>$n];}
   case 'currency PUT':{$c=in_array($b['currency']??'',['USD','EUR','VES'],true)?$b['currency']:Http::fail('Moneda inválida.');$this->db->run('UPDATE agora_tenants SET currency=?,revision=revision+1 WHERE id=?',[$c,$id]);$this->auth->audit('integracion.moneda','ajuste',(string)$id,['moneda'=>$c]);return ['ok'=>true,'currency'=>$c];}
   case 'fields/folders GET':return ['items'=>array_map(fn($n)=>['name'=>$n],$cfg['carpetas']??[]),'names'=>$cfg['carpetas']??[]];
   case 'fields/folders PUT':{$l=$b['names']??[];if(!is_array($l)||count($l)>50)Http::fail('Hasta 50 carpetas.');$l=array_values(array_unique(array_filter(array_map(fn($v)=>mb_substr(trim((string)$v),0,80),$l),'strlen')));$guardar(['carpetas'=>$l],'integracion.carpetas');return ['ok'=>true,'names'=>$l];}
   case 'whatsapp PUT':{$n=$b['numberId']??null;$a=$b['accountId']??null;foreach([$n,$a] as $v)if($v!==null&&!preg_match('/^[0-9A-Za-z_-]{1,80}$/D',(string)$v))Http::fail('Revisa el número o la cuenta de WhatsApp.');$guardar(['whatsapp'=>['numberId'=>$n,'accountId'=>$a]],'integracion.whatsapp');return ['ok'=>true];}
   case 'whatsapp/check POST':return ['diagnosis'=>$conectada?'La revisión de WhatsApp necesita el servicio de mensajería activo en el servidor.':self::NO_CONECTADA];
   case 'users GET':return ['items'=>array_map(fn($u)=>['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role'],'active'=>(bool)$u['active']],$this->db->all('SELECT id,name,email,role,active FROM agora_users WHERE local_tenant_id=? ORDER BY name LIMIT 500',[$id])),'syncedAt'=>null,'source'=>'agora'];
   case 'users/sync POST':Http::fail(self::NO_CONECTADA.' Los usuarios de Ágora se administran en «Usuarios y roles».',503);
   case 'won-alert GET':return ['days'=>(int)($cfg['won_alert_days']??0)];
  }
  Http::fail('Ruta no disponible.',404);
 }
 private function comision(array $cfg):array{
  $h=$cfg['comisiones']??[];$cur=$h?end($h):['kind'=>'percent','amount'=>10,'since'=>null];
  return ['current'=>$cur,'history'=>array_reverse(array_slice($h,0,-1))];
 }
 /** Lo guardado se suma a cada fila de admin/integrations. */
 public static function extra(DB $db,int $id):array{$c=$db->setting($id,'integracion',[]);if(!is_array($c))$c=[];return ['won_alert_days'=>(int)($c['won_alert_days']??0),'unread_reminder_minutes'=>(int)($c['unread_reminder_minutes']??0),'sync_enabled'=>(bool)($c['sync_enabled']??false)];}
}
