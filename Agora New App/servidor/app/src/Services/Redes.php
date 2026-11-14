<?php
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http,MotorMobile};
use Agora\Security\Auth;
/**
 * Agora New App 2.7.0 · Redes sociales (Social Planner de la cuenta conectada).
 *
 * - Cuentas conectadas en la cuenta (Facebook, Instagram, LinkedIn, Google, TikTok…), con aviso si alguna venció.
 * - Publicaciones: programadas, publicadas, con error y borradores, en un rango de fechas.
 * - Nueva publicación (texto, imagen por enlace opcional, programada o como borrador) y borrar una programada o borrador.
 * - Cifras de los últimos días (publicaciones, alcance, me gusta, comentarios, seguidores) si la cuenta las da.
 * Todo pasa por la pasarela del motor (cuota de la subcuenta). Un permiso de redes que falta nunca apaga la conexión.
 */
final class Redes {
 private const PLATAFORMAS=['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','google'=>'Google Business','tiktok'=>'TikTok','twitter'=>'X','youtube'=>'YouTube','pinterest'=>'Pinterest','threads'=>'Threads','bluesky'=>'Bluesky'];
 private const TIPOS=['scheduled'=>'Programadas','published'=>'Publicadas','failed'=>'Con error','draft'=>'Borradores','all'=>'Todas'];
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
 /** Llama a la cuenta con la cuota de la subcuenta y traduce los fallos a palabras. */
 private function llamar(array $c,\Closure $op):array{
  try{return (new \GhlGateway())->execute($c,$op,false);}
  catch(\GhlCooldown $e){header('Retry-After: '.$e->retryAfter);Http::fail('La cuenta pidió esperar un momento. Inténtalo otra vez en unos segundos.',429);}
  catch(\GhlApiException $e){
   if(in_array($e->status,[401,403],true))Http::fail('La conexión de esta subcuenta no tiene permiso para las redes sociales. Pídele a la agencia que agregue los permisos de Social Planner a la integración.',424);
   if($e->status===404)Http::fail('La cuenta no encontró esa publicación. Puede que ya se haya borrado.',404);
   if(in_array($e->status,[400,422],true))Http::fail('La cuenta no aceptó la publicación'.($e->detail!==''?': '.mb_substr($e->detail,0,160):'.').' Revisa el texto, la imagen y las redes elegidas.',422);
   Http::fail('La cuenta no respondió bien (código '.$e->status.'). Inténtalo otra vez en un momento.',424);
  }
 }
 private static function nombrePlataforma(string $p):string{return self::PLATAFORMAS[strtolower($p)]??ucfirst($p);}

 /** Cuentas de redes conectadas en la cuenta. */
 public function cuentas(array $c):array{
  $r=$this->llamar($c,fn(\GhlClient $cl):array=>$cl->socialAccounts((string)$c['location_id']));
  $lista=$r['results']['accounts']??$r['accounts']??[];
  $out=[];foreach(is_array($lista)?$lista:[] as $x){
   if(!is_array($x)||!is_string($x['id']??null))continue;
   $out[]=['id'=>$x['id'],'perfil'=>(string)($x['profileId']??''),'nombre'=>(string)($x['name']??'Cuenta'),'plataforma'=>strtolower((string)($x['platform']??'')),'red'=>self::nombrePlataforma((string)($x['platform']??'')),'tipo'=>(string)($x['type']??''),'vencida'=>(bool)($x['isExpired']??false),'avatar'=>is_string($x['avatar']??null)&&str_starts_with($x['avatar'],'https://')?$x['avatar']:null];
  }
  return $out;
 }
 /** Resumen: cuentas y cifras de los últimos días (las cifras son opcionales: si la cuenta no las da, no se muestran). */
 public function resumen():array{
  $c=$this->conexion();$cuentas=$this->cuentas($c);$cifras=null;$aviso=null;
  $perfiles=array_values(array_unique(array_filter(array_map(fn($x)=>$x['id'],array_filter($cuentas,fn($x)=>!$x['vencida'])))));
  if($perfiles){
   try{$s=(new \GhlGateway())->execute($c,fn(\GhlClient $cl):array=>$cl->socialStatistics((string)$c['location_id'],$perfiles),false);
    $res=$s['results']??[];$tot=$res['totals']??[];$br=$res['breakdowns']??[];
    $num=fn($v)=>is_numeric($v)?(float)$v:null;
    $cifras=['dias'=>is_array($res['dayRange']??null)?count($res['dayRange']):null,'publicaciones'=>$num($tot['posts']??null),'impresiones'=>$num($tot['impressions']??null),'alcance'=>$num($br['reach']['total']??null),'meGusta'=>$num($tot['likes']??null),'comentarios'=>$num($tot['comments']??null),'seguidores'=>$num($tot['followers']??null),
     'cambio'=>['publicaciones'=>$num($br['posts']['totalChange']??null),'impresiones'=>$num($br['impressions']['totalChange']??null),'alcance'=>$num($br['reach']['totalChange']??null)]];
   }catch(\GhlCooldown $e){$aviso='Las cifras están en espera para respetar la cuota.';}
   catch(\Throwable $e){$aviso='La cuenta no dio las cifras de las redes.';}
  }
  return ['cuentas'=>$cuentas,'cifras'=>$cifras,'avisoCifras'=>$aviso,'puedePublicar'=>$this->puedePublicar(),'enlazado'=>$this->autor()!==null,'tipos'=>self::TIPOS];
 }
 private function puedePublicar():bool{try{$this->a->permit('conversaciones.atender');}catch(\Agora\Core\HttpError){return false;}return !$this->a->assignedOnly();}
 /** Quien publica tiene que ser una persona de la cuenta: su usuario enlazado. */
 private function autor():?string{return (new UserLinks($this->db))->forUser((int)$this->a->tenant,['id'=>$this->a->user['id'],'email'=>$this->a->user['email']])['id']??null;}

 /** Publicaciones de un tipo en un rango de fechas (por defecto, 30 días atrás y 60 adelante). */
 public function publicaciones(array $q):array{
  $c=$this->conexion();
  $tipo=is_string($q['tipo']??null)&&isset(self::TIPOS[$q['tipo']])?$q['tipo']:'all';
  $desde=self::fecha($q['desde']??null)??gmdate('Y-m-d',strtotime('-30 days'));$hasta=self::fecha($q['hasta']??null)??gmdate('Y-m-d',strtotime('+60 days'));
  if($desde>$hasta)[$desde,$hasta]=[$hasta,$desde];
  $pagina=max(0,min(50,(int)($q['pagina']??0)));
  $filtro=['type'=>$tipo,'skip'=>(string)($pagina*20),'limit'=>'20','fromDate'=>$desde.'T00:00:00.000Z','toDate'=>$hasta.'T23:59:59.999Z','includeUsers'=>'true'];
  $r=$this->llamar($c,fn(\GhlClient $cl):array=>$cl->socialPosts((string)$c['location_id'],$filtro));
  $lista=$r['results']['posts']??$r['posts']??[];$total=$r['results']['count']??null;
  $cuentas=[];try{foreach($this->cuentas($c) as $x)$cuentas[$x['id']]=$x;}catch(\Throwable){}
  $out=[];foreach(is_array($lista)?$lista:[] as $p){
   if(!is_array($p))continue;
   $ids=is_array($p['accountIds']??null)?$p['accountIds']:(is_string($p['accountId']??null)?[$p['accountId']]:[]);
   $media=[];foreach(is_array($p['media']??null)?$p['media']:[] as $m)if(is_array($m)&&is_string($m['url']??null)&&str_starts_with($m['url'],'https://'))$media[]=['url'=>$m['url'],'tipo'=>str_starts_with((string)($m['type']??''),'video')?'video':'imagen','miniatura'=>is_string($m['thumbnail']??null)&&str_starts_with($m['thumbnail'],'https://')?$m['thumbnail']:null];
   $out[]=['id'=>(string)($p['_id']??$p['id']??''),'texto'=>mb_substr((string)($p['summary']??''),0,3000),'estado'=>is_string($p['status']??null)?$p['status']:'','fecha'=>(string)($p['displayDate']??$p['scheduleDate']??$p['publishedAt']??$p['createdAt']??''),'publicada'=>$p['publishedAt']??null,
    'error'=>is_string($p['error']??null)&&$p['error']!==''?mb_substr($p['error'],0,240):null,'plataforma'=>strtolower((string)($p['platform']??'')),'red'=>self::nombrePlataforma((string)($p['platform']??'')),
    'cuentas'=>array_values(array_map(fn($i)=>['id'=>$i,'nombre'=>$cuentas[$i]['nombre']??'Cuenta','red'=>$cuentas[$i]['red']??''],$ids)),'media'=>$media,'tipoPost'=>is_string($p['type']??null)?$p['type']:'post','origen'=>(string)($p['source']??'')];
  }
  return ['tipo'=>$tipo,'desde'=>$desde,'hasta'=>$hasta,'pagina'=>$pagina,'items'=>$out,'total'=>is_numeric($total)?(int)$total:null,'hayMas'=>count($out)===20];
 }
 /** Nueva publicación: programada (con fecha) o borrador. */
 public function crear(array $b):array{
  if(!$this->puedePublicar())Http::fail('Tu rol no permite publicar en las redes de la subcuenta.',403);
  $autor=$this->autor();if(!$autor)Http::fail('Para publicar, tu usuario tiene que estar enlazado con la cuenta. Pídeselo a quien administra la subcuenta (Usuarios y roles).',403);
  $c=$this->conexion();
  $texto=trim((string)($b['texto']??''));if($texto===''||mb_strlen($texto)>3000)Http::fail('Escribe el texto de la publicación (hasta 3.000 caracteres).');
  $ids=$b['cuentas']??[];if(!is_array($ids)||!$ids||count($ids)>20)Http::fail('Elige en qué redes se publica.');
  $validas=[];foreach($this->cuentas($c) as $x)$validas[$x['id']]=$x;
  foreach($ids as $i){if(!is_string($i)||!isset($validas[$i]))Http::fail('Una de las redes elegidas ya no está conectada. Recarga la pantalla.',409);if($validas[$i]['vencida'])Http::fail('La conexión de «'.$validas[$i]['nombre'].'» venció. Hay que volver a conectarla en la cuenta antes de publicar.',409);}
  $media=[];$img=trim((string)($b['imagen']??''));
  if($img!==''){if(!preg_match('~^https://[^\s<>"]{4,1000}$~',$img))Http::fail('El enlace de la imagen tiene que empezar por https://');$media[]=['url'=>$img,'type'=>preg_match('/\.mp4($|\?)/i',$img)?'video/mp4':'image/jpeg'];}
  $borrador=($b['borrador']??false)===true;$cuando=null;
  if(!$borrador){
   $f=(string)($b['cuando']??'');try{$d=new \DateTimeImmutable($f);}catch(\Exception){Http::fail('Elige la fecha y la hora de la publicación.');}
   if($d->getTimestamp()<time()+120)Http::fail('La fecha tiene que ser al menos dos minutos en el futuro.');
   if($d->getTimestamp()>time()+86400*365)Http::fail('La fecha no puede pasar de un año.');
   $cuando=$d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
  }
  $post=['accountIds'=>array_values($ids),'summary'=>$texto,'media'=>$media,'type'=>'post','userId'=>$autor,'createdBy'=>$autor,'status'=>$borrador?'draft':'scheduled']+($cuando?['scheduleDate'=>$cuando]:[]);
  $r=$this->llamar($c,fn(\GhlClient $cl):array=>$cl->socialCreatePost((string)$c['location_id'],$post));
  $id=(string)($r['results']['post']['_id']??$r['results']['_id']??$r['post']['_id']??$r['results']['id']??'');
  $this->a->audit('redes.publicacion','redes',$id,['redes'=>count($ids),'borrador'=>$borrador,'cuando'=>$cuando]);
  return ['ok'=>true,'id'=>$id,'estado'=>$borrador?'draft':'scheduled'];
 }
 public function borrar(string $id):array{
  if(!$this->puedePublicar())Http::fail('Tu rol no permite borrar publicaciones de la subcuenta.',403);
  if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))Http::fail('Publicación inválida.');
  $c=$this->conexion();$this->llamar($c,fn(\GhlClient $cl):array=>$cl->socialDeletePost((string)$c['location_id'],$id));
  $this->a->audit('redes.borrada','redes',$id);return ['ok'=>true];
 }
 private static function fecha(mixed $v):?string{return is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)&&strtotime($v)!==false?$v:null;}
}
