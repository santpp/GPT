<?php
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http,MotorMobile};
use Agora\Security\Auth;
/**
 * Agora New App 2.8.0 · Campañas de Meta de la cuenta conectada (Ad Manager), solo lectura.
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
  if($g&&time()-(int)$g['at']<1200&&is_array($d=json_decode((string)$g['payload'],true)))return $d;
  $campanas=[];$estado='sin_conexion';$http=null;
  foreach(['INTEGRATION','AD_MANAGER'] as $tipo){
   foreach([null,''] as $campania){
    try{$r=(new \GhlGateway())->execute($c,fn(\GhlClient $cl):array=>$cl->adReportingList((string)$c['location_id'],$desde,$hasta,$tipo,'campaigns',$campania),false,true);}
    catch(\GhlCooldown $e){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. Inténtalo otra vez en unos segundos.',429);}
    catch(\GhlApiException $e){$http=$e->status;if(in_array($e->status,[401,403],true)){$estado='permiso';break 2;}continue;}
    $lista=self::lista($r);
    if($lista!==null){$estado='ok';foreach($lista as $x){$x['origen']=$tipo==='INTEGRATION'?'Meta':'Ad Manager';$campanas[$x['id']!==''?$x['id']:$tipo.count($campanas)]=$x;}break;}
   }
  }
  $campanas=array_values($campanas);
  usort($campanas,fn($a,$b)=>($b['inversion']??0)<=>($a['inversion']??0));
  $sum=function(string $k)use($campanas):?float{$t=array_reduce($campanas,fn($t,$x)=>$x[$k]===null?$t:($t??0)+$x[$k],null);return $t===null?null:round($t,2);};
  $tot=['inversion'=>$sum('inversion'),'impresiones'=>$sum('impresiones'),'alcance'=>$sum('alcance'),'clics'=>$sum('clics'),'resultados'=>$sum('resultados')];
  $tot['costoPorResultado']=$tot['inversion']!==null&&$tot['resultados']?round($tot['inversion']/$tot['resultados'],2):null;
  $tot['ctr']=$tot['impresiones']&&$tot['clics']!==null?round(100*$tot['clics']/$tot['impresiones'],2):null;
  $out=['mes'=>$mes,'desde'=>$desde,'hasta'=>$hasta,'estado'=>$estado,'http'=>$estado==='ok'?null:$http,'campanas'=>$campanas,'totales'=>$tot,'leidoEn'=>gmdate('c')];
  \query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$c['id'],$clave,json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
  return $out;
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
