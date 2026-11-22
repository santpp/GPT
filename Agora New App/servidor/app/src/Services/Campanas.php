<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http,MotorMobile};
use Agora\Security\Auth;
/**
 * Agora New App 2.8.2 · Campañas de Meta de la cuenta conectada (Ad Manager), solo lectura.
 *
 * Lee la lista de campañas con sus cifras en un mes, también las creadas en el Administrador de anuncios de Meta
 * (fuente «INTEGRATION»), y las del Ad Manager de la cuenta (fuente «AD_MANAGER»).
 * La documentación no describe la forma de la respuesta: se lee buscando cada cifra por su nombre, sin suponer el anidado.
 * Lecturas «suaves» (un rechazo no pausa la subcuenta) y guardadas 20 minutos.
 */
final class Campanas {
 public function __construct(private DB $db,private array $config,private Auth $a){}

 public function mes(?string $mes):array{
  $t=(int)$this->a->tenant;
  if(!MotorMobile::conectada($this->db,$t))Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  $mes=is_string($mes)&&preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$mes)?$mes:gmdate('Y-m');
  $desde=$mes.'-01';$hasta=min(gmdate('Y-m-t',strtotime($desde)),gmdate('Y-m-d'));
  if($desde>gmdate('Y-m-d'))Http::fail('Ese mes todavía no empieza.');
  MotorMobile::instalar($this->db,$this->config);MotorMobile::arrancar($this->config);
  $c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$t,MotorMobile::AGENCIA])->fetch();
  if(!$c){MotorMobile::sincronizar($this->db,$this->config,$t);$c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$t,MotorMobile::AGENCIA])->fetch();}
  if(!$c)Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  $clave='meta:campanas:'.$desde.':'.$hasta;
  $g=\query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS at FROM directory_cache WHERE connection_id=? AND cache_key=?',[$c['id'],$clave])->fetch();
  if($g&&is_array($d=json_decode((string)$g['payload'],true))&&time()-(int)$g['at']<(($d['estado']??'')==='ok'?1200:120))return $this->paraQuien($d);
  $campanas=[];$estado='sin_conexion';$http=null;$diag=[];
  foreach(['INTEGRATION','AD_MANAGER'] as $tipo){
   foreach([null,''] as $campania){
    $etq='lista '.$tipo.($campania===null?'':' (campaignId vacío)');
    try{$r=$this->leer($c,fn(\GhlClient $cl):array=>$cl->adReportingList((string)$c['location_id'],$desde,$hasta,$tipo,'campaigns',$campania));}
    catch(\GhlApiException $e){$http=$e->status;$diag[]=$etq.': código '.$e->status.($e->detail!==''?' ('.$e->detail.')':'');if(in_array($e->status,[401,403],true)){$estado='permiso';break 2;}continue;}
    $lista=self::lista($r);
    $diag[]=$etq.': respondió ('.($lista===null?'sin lista reconocible; claves: '.implode(', ',array_slice(array_map('strval',array_keys($r)),0,8)):count($lista).' campañas').')';
    if($lista!==null){$estado='ok';foreach($lista as $x){$x['origen']=$tipo==='INTEGRATION'?'Meta':'Ad Manager';$campanas[$x['id']!==''?$x['id']:$tipo.count($campanas)]=$x;}break;}
   }
  }
  $totalesCuenta=null;$integracion=null;$cuentasPub=null;
  if($estado!=='ok'&&$estado!=='permiso'){
   // Sin lista: ¿hay totales del mes? ¿Está conectada la cuenta publicitaria? Así el aviso dice lo que pasa de verdad.
   foreach(['INTEGRATION','AD_MANAGER'] as $tipo){
    try{$r=$this->leer($c,fn(\GhlClient $cl):array=>$cl->adReporting((string)$c['location_id'],$desde,$hasta,$tipo));}
    catch(\GhlApiException $e){$diag[]='totales '.$tipo.': código '.$e->status.($e->detail!==''?' ('.$e->detail.')':'');continue;}
    $f=self::totalesDe($r);$diag[]='totales '.$tipo.': respondió ('.($f?'con cifras':'sin cifras; claves: '.implode(', ',array_slice(array_map('strval',array_keys($r)),0,8))).')';
    if($f){$totalesCuenta=$f;$estado='totales';break;}
   }
   try{$r=$this->leer($c,fn(\GhlClient $cl):array=>$cl->adIntegration((string)$c['location_id']));$integracion=self::hayAlgo($r);$diag[]='integración de anuncios: respondió ('.($integracion?'conectada':'vacía').')';}
   catch(\GhlApiException $e){$diag[]='integración de anuncios: código '.$e->status.($e->detail!==''?' ('.$e->detail.')':'');if($e->status===404)$integracion=false;if(in_array($e->status,[401,403],true)&&$estado!=='totales')$estado='permiso';}
   try{$r=$this->leer($c,fn(\GhlClient $cl):array=>$cl->adAccounts((string)$c['location_id']));$l=self::primeraLista($r);$cuentasPub=$l===null?null:count($l);$diag[]='cuentas publicitarias: respondió ('.($cuentasPub===null?'sin lista':$cuentasPub).')';}
   catch(\GhlApiException $e){$diag[]='cuentas publicitarias: código '.$e->status.($e->detail!==''?' ('.$e->detail.')':'');}
   // «Access Token not found»: la cuenta publicitaria está registrada pero su autorización de Meta venció o se perdió.
   $sinToken=(bool)array_filter($diag,fn($l)=>str_contains($l,'código 422')&&stripos($l,'access token')!==false);
   if($estado==='sin_conexion'||$estado==='permiso'&&$sinToken)$estado=$sinToken?'reconectar':($integracion===false||$cuentasPub===0?'sin_integracion':($integracion===true||$cuentasPub?'sin_lista':'sin_conexion'));
  }
  $campanas=array_values($campanas);
  usort($campanas,fn($a,$b)=>($b['inversion']??0)<=>($a['inversion']??0));
  $sum=function(string $k)use($campanas):?float{$t=array_reduce($campanas,fn($t,$x)=>$x[$k]===null?$t:($t??0)+$x[$k],null);return $t===null?null:round($t,2);};
  $tot=['inversion'=>$sum('inversion'),'impresiones'=>$sum('impresiones'),'alcance'=>$sum('alcance'),'clics'=>$sum('clics'),'resultados'=>$sum('resultados')];
  $tot['costoPorResultado']=$tot['inversion']!==null&&$tot['resultados']?round($tot['inversion']/$tot['resultados'],2):null;
  $tot['ctr']=$tot['impresiones']&&$tot['clics']!==null?round(100*$tot['clics']/$tot['impresiones'],2):null;
  if($totalesCuenta)$tot=$totalesCuenta;
  $out=['mes'=>$mes,'desde'=>$desde,'hasta'=>$hasta,'estado'=>$estado,'http'=>$estado==='ok'?null:$http,'campanas'=>$campanas,'totales'=>$tot,'integracion'=>$integracion,'cuentasPublicitarias'=>$cuentasPub,'diagnostico'=>$diag,'leidoEn'=>gmdate('c')];
  \query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$c['id'],$clave,json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
  return $this->paraQuien($out);
 }
 /** El detalle técnico de cómo respondió la cuenta solo lo ven los dueños de Ágora. */
 private function paraQuien(array $d):array{if(!in_array($this->a->requireUser()['role'],['MASTER','SUPERADMIN'],true))unset($d['diagnostico']);return $d;}
 private function leer(array $c,\Closure $op):array{
  try{return (new \GhlGateway())->execute($c,$op,false,true);}
  catch(\GhlCooldown $e){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. Inténtalo otra vez en unos segundos.',429);}
 }
 private static function hayAlgo(array $r):bool{
  foreach(['integration','data','results','adAccount','adAccountId','id','_id','pageId'] as $k)if(!empty($r[$k]))return true;
  return false;
 }
 private static function primeraLista(array $r):?array{
  foreach($r as $v){if(is_array($v)&&array_is_list($v))return $v;if(is_array($v))foreach($v as $h)if(is_array($h)&&array_is_list($h))return $h;}
  return null;
 }
 /** Totales del mes desde el reporte agregado (sin lista de campañas). */
 private static function totalesDe(array $r):?array{
  $filas=[];$buscar=function(mixed $v,int $p)use(&$buscar,&$filas):void{if($p>5||!is_array($v))return;$x=['inversion'=>self::num($v,['spend','amountSpent']),'impresiones'=>self::num($v,['impressions']),'clics'=>self::num($v,['clicks']),'resultados'=>self::num($v,['conversions','results','leads'])];if(array_filter($x,fn($n)=>$n!==null)){$filas[]=$x;return;}foreach($v as $h)$buscar($h,$p+1);};
  $buscar($r,0);if(!$filas)return null;
  $s=fn($k)=>array_reduce($filas,fn($t,$f)=>$f[$k]===null?$t:($t??0)+$f[$k],null);
  $t=['inversion'=>$s('inversion')!==null?round($s('inversion'),2):null,'impresiones'=>$s('impresiones'),'alcance'=>null,'clics'=>$s('clics'),'resultados'=>$s('resultados')];
  $t['costoPorResultado']=$t['inversion']!==null&&$t['resultados']?round($t['inversion']/$t['resultados'],2):null;
  $t['ctr']=$t['impresiones']&&$t['clics']!==null?round(100*$t['clics']/$t['impresiones'],2):null;
  return $t;
 }

 /** Busca en la respuesta la lista de campañas (objetos con nombre o id y alguna cifra). null si no hay ninguna lista reconocible. */
 private static function lista(array $r):?array{
  $mejor=null;
  $buscar=function(mixed $v,int $prof)use(&$buscar,&$mejor):void{
   if($prof>5||!is_array($v))return;
   if(array_is_list($v)&&$v&&is_array($v[0])){
    $filas=array_values(array_filter(array_map([self::class,'fila'],$v)));
    if($filas&&($mejor===null||count($filas)>count($mejor)))$mejor=$filas;
   }
   foreach($v as $hijo)$buscar($hijo,$prof+1);
  };
  $buscar($r,0);
  if($mejor!==null)return $mejor;
  // Respuesta válida sin lista: la cuenta respondió, pero no hay campañas en el periodo.
  return self::pareceVacia($r)?[]:null;
 }
 private static function pareceVacia(array $r):bool{foreach($r as $v)if(is_array($v)&&array_is_list($v)&&!$v)return true;return isset($r['success'])||isset($r['data'])||isset($r['results']);}
 private static function num(array $x,array $claves):?float{
  foreach($claves as $k){$v=self::valor($x,$k);if(is_numeric($v))return (float)$v;}
  return null;
 }
 /** Un valor por nombre, también si viene dentro de «insights», «metrics» o «data». */
 private static function valor(array $x,string $k):mixed{
  if(array_key_exists($k,$x))return is_array($x[$k])?($x[$k]['value']??$x[$k]['total']??null):$x[$k];
  foreach(['insights','metrics','reporting','stats','data'] as $n)if(is_array($x[$n]??null)){$h=$x[$n];if(array_is_list($h)&&isset($h[0])&&is_array($h[0]))$h=$h[0];if(array_key_exists($k,$h))return is_array($h[$k])?($h[$k]['value']??null):$h[$k];}
  return null;
 }
 private static function fila(mixed $x):?array{
  if(!is_array($x))return null;
  $nombre=self::valor($x,'name')??self::valor($x,'campaignName')??self::valor($x,'campaign_name');
  $id=self::valor($x,'id')??self::valor($x,'campaignId')??self::valor($x,'campaign_id')??self::valor($x,'_id');
  $inv=self::num($x,['spend','amountSpent','amount_spent']);$imp=self::num($x,['impressions']);$cl=self::num($x,['clicks','inline_link_clicks']);
  if(!is_string($nombre)&&!is_scalar($id))return null;
  if($inv===null&&$imp===null&&$cl===null)return null;
  $res=self::num($x,['conversions','results','leads','actions']);
  $estado=self::valor($x,'effective_status')??self::valor($x,'status');
  return ['id'=>is_scalar($id)?(string)$id:'','nombre'=>is_string($nombre)&&$nombre!==''?mb_substr($nombre,0,190):'Campaña sin nombre','estado'=>is_string($estado)?strtolower($estado):null,
   'inversion'=>$inv,'impresiones'=>$imp,'alcance'=>self::num($x,['reach']),'clics'=>$cl,'resultados'=>$res,
   'costoPorResultado'=>self::num($x,['cost_per_conversion','costPerConversion','cost_per_result','costPerResult'])??($inv!==null&&$res?round($inv/$res,2):null),
   'ctr'=>self::num($x,['ctr'])??($imp&&$cl!==null?round(100*$cl/$imp,2):null),'cpc'=>self::num($x,['cpc'])];
 }
}
