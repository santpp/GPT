<?php
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http,MotorMobile};
use Agora\Security\Auth;
/**
 * Agora New App · Redes sociales (Social Planner de la cuenta conectada). Solo lectura.
 *
 * - Redes conectadas en la cuenta, con aviso si alguna venció.
 * - Cifras de los últimos 7 días frente a los 7 anteriores (lo que la cuenta informa).
 * No se publica ni se listan publicaciones: solo las cifras de las redes.
 * Todas las lecturas son «suaves»: si la cuenta rechaza una, la subcuenta no se pausa (la bandeja sigue).
 * Las redes se guardan 5 minutos y las cifras 15, para no gastar cuota.
 */
final class Redes {
 private const PLATAFORMAS=['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','google'=>'Google Business','tiktok'=>'TikTok','twitter'=>'X','youtube'=>'YouTube','pinterest'=>'Pinterest','threads'=>'Threads','bluesky'=>'Bluesky'];
 /** Las que la cuenta puede medir (LinkedIn solo páginas y TikTok solo cuentas de empresa: la cuenta descarta las demás). */
 private const CON_CIFRAS=['facebook','instagram','linkedin','google','pinterest','youtube','tiktok'];
 public function __construct(private DB $db,private array $config,private Auth $a){}

 private function conexion():array{
  $t=(int)$this->a->tenant;
  if(!MotorMobile::conectada($this->db,$t))Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  MotorMobile::instalar($this->db,$this->config);MotorMobile::arrancar($this->config);
  $c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$t,MotorMobile::AGENCIA])->fetch();
  if(!$c){MotorMobile::sincronizar($this->db,$this->config,$t);$c=\query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$t,MotorMobile::AGENCIA])->fetch();}
  if(!$c)Http::fail('Esta subcuenta todavía no está conectada con su cuenta.',409);
  return $c;
 }
 private static function guardado(array $c,string $clave,int $segundos):?array{
  $r=\query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS at FROM directory_cache WHERE connection_id=? AND cache_key=?',[$c['id'],$clave])->fetch();
  if(!$r||time()-(int)$r['at']>$segundos)return null;$d=json_decode((string)$r['payload'],true);return is_array($d)?$d:null;
 }
 private static function guardar(array $c,string $clave,array $d):void{
  \query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$c['id'],$clave,json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
 }
 /** Lectura suave: un rechazo de la cuenta no pausa la subcuenta. */
 private function leer(array $c,\Closure $op):array{
  try{return (new \GhlGateway())->execute($c,$op,false,true);}
  catch(\GhlCooldown $e){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. Inténtalo otra vez en unos segundos.',429);}
  catch(\GhlApiException $e){
   if(in_array($e->status,[401,403],true))Http::fail('La conexión de esta subcuenta no tiene permiso para leer las redes sociales. Pídele a la agencia que agregue los permisos de Social Planner a la integración.',424);
   Http::fail('La cuenta no respondió bien (código '.$e->status.'). Inténtalo otra vez en un momento.',424);
  }
 }
 private static function nombrePlataforma(string $p):string{return self::PLATAFORMAS[strtolower($p)]??ucfirst($p);}

 /** Redes conectadas en la cuenta (guardadas 5 minutos). */
 private function cuentas(array $c):array{
  if(($g=self::guardado($c,'social:cuentas',300))!==null)return $g;
  $r=$this->leer($c,fn(\GhlClient $cl):array=>$cl->socialAccounts((string)$c['location_id']));
  $lista=$r['results']['accounts']??$r['accounts']??[];$out=[];
  foreach(is_array($lista)?$lista:[] as $x){
   if(!is_array($x)||!is_string($x['id']??null))continue;$plat=strtolower((string)($x['platform']??''));
   $out[]=['id'=>$x['id'],'perfil'=>is_string($x['profileId']??null)?$x['profileId']:'','nombre'=>(string)($x['name']??'Cuenta'),'plataforma'=>$plat,'red'=>self::nombrePlataforma($plat),'tipo'=>(string)($x['type']??''),'vencida'=>(bool)($x['isExpired']??false)];
  }
  self::guardar($c,'social:cuentas',$out);return $out;
 }
 /** Cifras de los últimos 7 días (guardadas 15 minutos). Opcionales: si la cuenta no las da, se dice por qué. */
 private function cifras(array $c,array $cuentas):array{
  $medibles=array_values(array_filter($cuentas,fn($x)=>!$x['vencida']&&$x['perfil']!==''&&in_array($x['plataforma'],self::CON_CIFRAS,true)));
  if(!$medibles)return ['cifras'=>null,'aviso'=>$cuentas?'Ninguna de las redes conectadas tiene cifras disponibles.':null];
  $perfiles=array_values(array_unique(array_column($medibles,'perfil')));$plats=array_values(array_unique(array_column($medibles,'plataforma')));
  $clave='social:cifras:'.substr(hash('sha256',implode(',',$perfiles)),0,16);
  if(($g=self::guardado($c,$clave,900))!==null)return $g;
  try{$s=(new \GhlGateway())->execute($c,fn(\GhlClient $cl):array=>$cl->socialStatistics((string)$c['location_id'],$perfiles,$plats),false,true);}
  catch(\GhlCooldown $e){return ['cifras'=>null,'aviso'=>'Las cifras están en espera para respetar la cuota. Vuelve a abrir en un momento.'];}
  catch(\GhlApiException $e){$out=['cifras'=>null,'aviso'=>in_array($e->status,[401,403],true)?'La conexión no tiene permiso para leer las cifras de las redes. Pídele a la agencia que agregue los permisos de Social Planner a la integración.':'La cuenta no dio las cifras de las redes (código '.$e->status.').'];self::guardar($c,$clave,$out);return $out;}
  catch(\Throwable $e){return ['cifras'=>null,'aviso'=>'La cuenta no dio las cifras de las redes.'];}
  $res=$s['results']??[];$tot=$res['totals']??[];$br=$res['breakdowns']??[];$num=fn($v)=>is_numeric($v)?(float)$v:null;
  $porRed=[];
  foreach(['impressions'=>'impresiones','followers'=>'seguidores','likes'=>'meGusta'] as $k=>$nombre){
   foreach((array)($res['platformTotals'][$k]??[]) as $plat=>$v){$v=is_array($v)?($v['total']??$v['value']??null):$v;if(is_numeric($v)){$porRed[$plat]['red']=self::nombrePlataforma((string)$plat);$porRed[$plat][$nombre]=(float)$v;}}
  }
  $out=['cifras'=>['dias'=>is_array($res['dayRange']??null)&&$res['dayRange']?count($res['dayRange']):7,'publicaciones'=>$num($tot['posts']??null),'impresiones'=>$num($tot['impressions']??null),'alcance'=>$num($br['reach']['total']??null),'meGusta'=>$num($tot['likes']??null),'comentarios'=>$num($tot['comments']??null),'seguidores'=>$num($tot['followers']??null),
   'cambio'=>['publicaciones'=>$num($br['posts']['totalChange']??null),'impresiones'=>$num($br['impressions']['totalChange']??null),'alcance'=>$num($br['reach']['totalChange']??null)],'porRed'=>array_values($porRed)],'aviso'=>null];
  self::guardar($c,$clave,$out);return $out;
 }
 public function resumen():array{
  $c=$this->conexion();$cuentas=$this->cuentas($c);$x=$this->cifras($c,$cuentas);
  return ['cuentas'=>$cuentas,'cifras'=>$x['cifras'],'avisoCifras'=>$x['aviso']];
 }
 private static function fecha(mixed $v):?string{return is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)&&strtotime($v)!==false?$v:null;}
}
