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
 * - Publicaciones (solo lectura): también las nativas, hechas en Meta Business Suite, que la cuenta sincroniza.
 * No se publica ni se borra desde Ágora.
 * Todas las lecturas son «suaves»: si la cuenta rechaza una, la subcuenta no se pausa (la bandeja sigue).
 * Las redes se guardan 5 minutos y las cifras 15, para no gastar cuota.
 */
final class Redes {
 private const PLATAFORMAS=['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','google'=>'Google Business','tiktok'=>'TikTok','twitter'=>'X','youtube'=>'YouTube','pinterest'=>'Pinterest','threads'=>'Threads','bluesky'=>'Bluesky'];
 /** Las que la cuenta puede medir (LinkedIn solo páginas y TikTok solo cuentas de empresa: la cuenta descarta las demás). */
 private const TIPOS=['scheduled'=>'Programadas','published'=>'Publicadas','failed'=>'Con error','draft'=>'Borradores','all'=>'Todas'];
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
 /** Publicaciones de un tipo y, si se pide, de una sola red, en un rango (por defecto, 90 días atrás y 60 adelante). */
 public function publicaciones(array $q):array{
  $c=$this->conexion();
  $tipo=is_string($q['tipo']??null)&&isset(self::TIPOS[$q['tipo']])?$q['tipo']:'all';
  $desde=self::fecha($q['desde']??null)??gmdate('Y-m-d',strtotime('-90 days'));$hasta=self::fecha($q['hasta']??null)??gmdate('Y-m-d',strtotime('+60 days'));
  if($desde>$hasta)[$desde,$hasta]=[$hasta,$desde];
  $pagina=max(0,min(50,(int)($q['pagina']??0)));
  $todas=$this->cuentas($c);$red=is_string($q['red']??null)&&preg_match('/^[a-z_]{2,30}$/D',$q['red'])?$q['red']:'';
  $filtro=['type'=>$tipo,'skip'=>(string)($pagina*20),'limit'=>'20','fromDate'=>$desde.'T00:00:00.000Z','toDate'=>$hasta.'T23:59:59.999Z','includeUsers'=>'false'];
  if($red!==''){$ids=array_column(array_filter($todas,fn($x)=>$x['plataforma']===$red),'id');if(!$ids)return ['tipo'=>$tipo,'red'=>$red,'desde'=>$desde,'hasta'=>$hasta,'pagina'=>0,'items'=>[],'total'=>0,'hayMas'=>false];$filtro['accounts']=implode(',',$ids);}
  $clave='social:posts:'.substr(hash('sha256',json_encode($filtro)),0,20);
  $leida=self::guardado($c,$clave,300);
  if($leida===null){$leida=$this->leerPublicaciones($c,$filtro,$tipo);if($leida['lista'])self::guardar($c,$clave,$leida);}
  $lista=$leida['lista'];$total=$leida['total'];$vacia=!empty($leida['vacia']);
  $cuentas=[];foreach($todas as $x)$cuentas[$x['id']]=$x;
  $out=[];foreach(is_array($lista)?$lista:[] as $p){
   if(!is_array($p))continue;
   $ids=is_array($p['accountIds']??null)?$p['accountIds']:(is_string($p['accountId']??null)?[$p['accountId']]:[]);
   $media=[];foreach(is_array($p['media']??null)?$p['media']:[] as $m)if(is_array($m)&&is_string($m['url']??null)&&str_starts_with($m['url'],'https://'))$media[]=['url'=>$m['url'],'tipo'=>str_starts_with((string)($m['type']??''),'video')?'video':'imagen','miniatura'=>is_string($m['thumbnail']??null)&&str_starts_with($m['thumbnail'],'https://')?$m['thumbnail']:null];
   $plat=strtolower((string)($p['platform']??''));if($plat===''&&$ids&&isset($cuentas[$ids[0]]))$plat=$cuentas[$ids[0]]['plataforma'];
   if($red!==''&&$plat!==''&&$plat!==$red)continue;
   $out[]=['id'=>(string)($p['_id']??$p['id']??$p['postId']??''),'texto'=>mb_substr((string)($p['summary']??$p['caption']??$p['message']??$p['text']??''),0,3000),'estado'=>is_string($p['status']??null)?$p['status']:'','fecha'=>(string)($p['displayDate']??$p['publishedAt']??$p['scheduleDate']??$p['createdAt']??''),
    'error'=>is_string($p['error']??null)&&$p['error']!==''?mb_substr($p['error'],0,240):null,'plataforma'=>$plat,'red'=>$plat!==''?self::nombrePlataforma($plat):'',
    'cuentas'=>array_values(array_map(fn($i)=>['id'=>$i,'nombre'=>$cuentas[$i]['nombre']??'Cuenta','red'=>$cuentas[$i]['red']??''],$ids)),'media'=>$media,'nativa'=>!in_array(strtolower((string)($p['source']??'composer')),['composer','recurring','csv',''],true)];
  }
  $res=['tipo'=>$tipo,'red'=>$red,'desde'=>$desde,'hasta'=>$hasta,'pagina'=>$pagina,'items'=>$out,'total'=>is_numeric($total)?(int)$total:null,'hayMas'=>count($lista)>=20,'cuentaVacia'=>$vacia];
  // Solo los dueños de Ágora ven cómo respondió la cuenta (sin contenido), para diagnosticar una lista vacía.
  if(in_array($this->a->requireUser()['role'],['MASTER','SUPERADMIN'],true))$res['diagnostico']=$leida['diagnostico'];
  return $res;
 }
 /**
  * Pide la lista a la cuenta: primero con la versión «v3», luego con la 2021-07-28.
  * Si con el tipo pedido no viene nada, prueba con «todas» y filtra por estado aquí (algunas cuentas solo responden así para las nativas).
  * @return array{lista:list<array>,total:?int,diagnostico:list<string>}
  */
 private function leerPublicaciones(array $c,array $filtro,string $tipo):array{
  $diag=[];$intentos=[];
  foreach(['v3','2021-07-28'] as $v){$intentos[]=[$v,$filtro];}
  if($tipo!=='all')foreach(['v3','2021-07-28'] as $v){$intentos[]=[$v,['type'=>'all']+$filtro];}
  $hubo=false;$permiso=false;$ultimo=null;
  foreach($intentos as [$v,$f]){
   $f['type']=$f['type']??$tipo;$etq='versión '.$v.', tipo '.$f['type'];
   try{$r=(new \GhlGateway())->execute($c,fn(\GhlClient $cl):array=>$cl->socialPosts((string)$c['location_id'],$f,$v),false,true);}
   catch(\GhlCooldown $e){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. Inténtalo otra vez en unos segundos.',429);}
   catch(\GhlApiException $e){$diag[]=$etq.': código '.$e->status.($e->detail!==''?' ('.$e->detail.')':'');$ultimo=$e->status;if(in_array($e->status,[401,403],true))$permiso=true;continue;}
   $hubo=true;[$lista,$donde,$total]=self::listaPosts($r);
   if($f['type']!==$tipo&&$tipo!=='all')$lista=array_values(array_filter($lista,fn($p)=>strtolower((string)($p['status']??''))===$tipo));
   $diag[]=$etq.': respondió; '.($donde!==''?'lista en «'.$donde.'»':'sin lista reconocible (claves: '.implode(', ',array_slice(array_map('strval',array_keys($r)),0,8)).')').'; '.count($lista).' publicaciones'.($total!==null?', total '.$total:'');
   if($lista)return ['lista'=>$lista,'total'=>$f['type']===$tipo?$total:null,'diagnostico'=>$diag];
  }
  if(!$hubo){
   if($permiso)Http::fail('La conexión de esta subcuenta no tiene permiso para leer las publicaciones. Pídele a la agencia que agregue los permisos de Social Planner a la integración.',424);
   Http::fail('La cuenta no dio las publicaciones (código '.$ultimo.'). Inténtalo otra vez en un momento.',424);
  }
  // Último intento: nombrando todas las redes conectadas y con un año hacia atrás (algunas cuentas solo responden así).
  $ids=array_column(array_filter($this->cuentas($c),fn($x)=>!$x['vencida']),'id');
  if($ids&&empty($filtro['accounts'])){
   $f=['type'=>'all','accounts'=>implode(',',$ids),'fromDate'=>gmdate('Y-m-d',strtotime('-365 days')).'T00:00:00.000Z']+$filtro;$f['type']='all';
   try{$r=(new \GhlGateway())->execute($c,fn(\GhlClient $cl):array=>$cl->socialPosts((string)$c['location_id'],$f,'v3'),false,true);
    [$lista,$donde]=self::listaPosts($r);if($tipo!=='all')$lista=array_values(array_filter($lista,fn($p)=>strtolower((string)($p['status']??''))===$tipo));
    $diag[]='versión v3, todas las redes, 365 días: respondió; '.count($lista).' publicaciones';
    if($lista)return ['lista'=>$lista,'total'=>null,'diagnostico'=>$diag];}
   catch(\GhlCooldown $e){}catch(\GhlApiException $e){$diag[]='versión v3, todas las redes, 365 días: código '.$e->status;}
  }
  return ['lista'=>[],'total'=>0,'diagnostico'=>$diag,'vacia'=>true];
 }
 /** Encuentra la lista de publicaciones sin suponer el anidado: results.posts, posts, data… o la primera lista de objetos con id. */
 private static function listaPosts(array $r):array{
  foreach([['results','posts'],['posts'],['data','posts'],['results','data'],['data'],['results']] as $ruta){
   $v=$r;foreach($ruta as $k)$v=is_array($v)&&array_key_exists($k,$v)?$v[$k]:null;
   if(is_array($v)&&array_is_list($v)){
    $tot=$r['results']['count']??$r['results']['total']??$r['count']??$r['total']??null;
    return [array_values(array_filter($v,'is_array')),implode('.',$ruta),is_numeric($tot)?(int)$tot:null];
   }
  }
  $hallada=null;$donde='';
  $buscar=function(mixed $v,string $ruta,int $prof)use(&$buscar,&$hallada,&$donde):void{
   if($hallada!==null||$prof>4||!is_array($v))return;
   if(array_is_list($v)&&$v&&is_array($v[0])&&(isset($v[0]['_id'])||isset($v[0]['summary'])||isset($v[0]['postId']))){$hallada=$v;$donde=$ruta;return;}
   foreach($v as $k=>$h)$buscar($h,ltrim($ruta.'.'.$k,'.'),$prof+1);
  };
  $buscar($r,'',0);
  return [$hallada?array_values(array_filter($hallada,'is_array')):[],$donde,null];
 }
 private static function fecha(mixed $v):?string{return is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)&&strtotime($v)!==false?$v:null;}
}
