<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Widgets;
use Agora\Core\{Http,HttpError};
use Agora\Controllers\Api;

/**
 * Agora New App 2.28.0 · Widgets, etapa 2 (fases 7, 9, 10 y 11): consultas sobre las filas de cada cosa.
 * Un widget «de datos» dice QUÉ contar (ventas, oportunidades, contactos, tareas, conversaciones, plantillas de WhatsApp),
 * CÓMO (contar, sumar, promedio, mínimo, máximo), POR QUÉ agruparlo y CON QUÉ filtros. Se calcula en el servidor sobre lo
 * que Ágora guarda (sus ventas y tareas, y lo que ya leyó de la cuenta), con los mismos límites de cada rol: quien solo ve
 * lo suyo, solo cuenta lo suyo. Nunca se suman monedas distintas.
 *   GET  workspace/consultas/catalogo                 objetos, campos para agrupar y filtrar, medidas y operaciones
 *   GET  workspace/consultas/valores?objeto=&campo=   los valores de un campo (para elegir filtros)
 *   POST workspace/consultas {pedidos:[{clave,consulta,periodo}]}   resultados (hasta 30 a la vez)
 *   POST workspace/consultas/filas {consulta,periodo,limite}         las filas (tarjeta con lista y descarga)
 */
final class Consultas {
 public const OPS=['contar'=>'Contar','sumar'=>'Sumar','promedio'=>'Promedio','minimo'=>'Mínimo','maximo'=>'Máximo'];
 public const RANGOS=['periodo'=>'El mes del widget','12m'=>'Los últimos 12 meses','todo'=>'Todo'];
 public const TIEMPO=['dia'=>'Día','semana'=>'Semana','mes'=>'Mes','dia_semana'=>'Día de la semana','hora'=>'Hora del día'];
 /** objeto => [nombre, fechas, campos para agrupar y filtrar, medidas [unidad, nombre], ¿qué dice una fila?] */
 public const OBJETOS=[
  'ventas'=>['Ventas',['fecha'=>'Fecha de la venta'],['vendedor'=>'Vendedor','producto'=>'Producto','cobro'=>'Forma de cobro','moneda'=>'Moneda','estado'=>'Estado','origen'=>'Origen'],
   ['monto'=>['dinero','Monto']]],
  'cobros'=>['Cuotas de cobro',['vence'=>'Fecha de la cuota','pagada'=>'Fecha en que se pagó'],['estado'=>'Estado','vendedor'=>'Vendedor','producto'=>'Producto','cobro'=>'Forma de cobro','moneda'=>'Moneda'],
   ['monto'=>['dinero','Monto']]],
  'oportunidades'=>['Oportunidades',['creada'=>'Fecha de creación','ganada'=>'Fecha en que se ganó'],['embudo'=>'Embudo','etapa'=>'Etapa','estado'=>'Estado','responsable'=>'Responsable'],
   ['valor'=>['dinero','Valor']]],
  'contactos'=>['Contactos',['creado'=>'Fecha de alta'],['responsable'=>'Responsable','etiqueta'=>'Etiqueta','origen'=>'Origen'],[]],
  'tareas'=>['Tareas',['vence'=>'Vencimiento'],['responsable'=>'Responsable','estado'=>'Estado','origen'=>'Origen'],[]],
  'conversaciones'=>['Conversaciones',['ultimo'=>'Último mensaje'],['canal'=>'Canal','responsable'=>'Responsable','situacion'=>'Situación'],['sin_leer'=>['numero','Mensajes sin leer']]],
  'plantillas'=>['Plantillas de WhatsApp pedidas',['pedida'=>'Fecha del pedido'],['plantilla'=>'Plantilla','quien'=>'Quién la pidió','situacion'=>'Situación'],[]],
 ];
 private const CANALES=['TYPE_WHATSAPP'=>'WhatsApp','TYPE_SMS'=>'SMS','TYPE_PHONE'=>'SMS','TYPE_EMAIL'=>'Correo','TYPE_INSTAGRAM'=>'Instagram','TYPE_FACEBOOK'=>'Facebook','TYPE_FB'=>'Facebook','TYPE_IG'=>'Instagram','TYPE_WEBCHAT'=>'Chat de la web','TYPE_GMB'=>'Google','TYPE_LIVE_CHAT'=>'Chat de la web'];
 private const ESTADOS_OP=['open'=>'Abierta','won'=>'Ganada','lost'=>'Perdida','abandoned'=>'Abandonada'];
 private const DIAS=['1'=>'Lunes','2'=>'Martes','3'=>'Miércoles','4'=>'Jueves','5'=>'Viernes','6'=>'Sábado','7'=>'Domingo'];
 private const COBROS=['unico'=>'Pago único','recurrente'=>'Recurrente','cuotas'=>'En cuotas'];
 private array $filas=[];private ?array $etapas=null;private ?array $personas=null;private ?bool $conectada=null;private ?array $camposCuenta=null;

 public function __construct(private Api $api){}
 private function t():int{return (int)$this->api->a->tenant;}
 private function db(){return $this->api->db;}

 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/consultas/catalogo'&&$m==='GET')return $this->catalogo();
  if($p==='workspace/consultas/valores'&&$m==='GET')return $this->valoresCampo((string)($_GET['objeto']??''),(string)($_GET['campo']??''));
  if($p==='workspace/consultas'&&$m==='POST')return $this->lote($b);
  if($p==='workspace/consultas/filas'&&$m==='POST')return $this->listar($b);
  Http::fail('Ruta no disponible.',404);
 }

 /* ---------- catálogo ---------- */
 public function catalogo():array{
  $out=[];
  foreach(self::OBJETOS as $id=>[$n,$fechas,$campos,$medidas]){
   $cs=[];foreach($campos as $k=>$l)$cs[]=['id'=>$k,'nombre'=>$l];
   foreach($this->camposPersonalizados($id) as $k=>$l)$cs[]=['id'=>$k,'nombre'=>$l,'personalizado'=>true];
   foreach(self::TIEMPO as $k=>$l)$cs[]=['id'=>$k,'nombre'=>$l,'tiempo'=>true];
   $out[]=['id'=>$id,'nombre'=>$n,'fechas'=>array_map(fn($k,$l)=>['id'=>$k,'nombre'=>$l],array_keys($fechas),$fechas),'campos'=>$cs,
    'medidas'=>array_map(fn($k,$x)=>['id'=>$k,'nombre'=>$x[1],'unidad'=>$x[0]],array_keys($medidas),$medidas)];
  }
  return ['objetos'=>$out,'operaciones'=>array_map(fn($k,$l)=>['id'=>$k,'nombre'=>$l],array_keys(self::OPS),self::OPS),'rangos'=>array_map(fn($k,$l)=>['id'=>$k,'nombre'=>$l],array_keys(self::RANGOS),self::RANGOS)];
 }
 /** Campos personalizados de la cuenta que traen los contactos y oportunidades que Ágora ya leyó («cf:<id>»). */
 private function camposPersonalizados(string $objeto):array{
  if(!in_array($objeto,['contactos','oportunidades'],true))return [];
  $ids=[];foreach($this->filas($objeto) as $f)foreach($f['_cf']??[] as $k=>$v)$ids[$k]=1;
  if(!$ids)return [];
  $nombres=$this->nombresCampos();$out=[];
  foreach(array_slice(array_keys($ids),0,30) as $k)$out['cf:'.$k]=$nombres[$k]??('Campo personalizado '.mb_substr($k,0,8));
  return $out;
 }
 private function nombresCampos():array{
  if($this->camposCuenta!==null)return $this->camposCuenta;$this->camposCuenta=[];
  try{foreach($this->db()->all('SELECT payload FROM contact_field_cache WHERE connection_id=?',[$this->t()]) as $r){$d=json_decode((string)$r['payload'],true);
   foreach((is_array($d['customFields']??null)?$d['customFields']:(is_array($d)&&array_is_list($d)?$d:[])) as $c)if(is_array($c)&&isset($c['id'],$c['name']))$this->camposCuenta[(string)$c['id']]=mb_substr((string)$c['name'],0,60);}}catch(\Throwable){}
  return $this->camposCuenta;
 }
 private function valoresCampo(string $objeto,string $campo):array{
  if(!isset(self::OBJETOS[$objeto]))Http::fail('No se puede contar «'.$objeto.'».');
  if(!$this->campoValido($objeto,$campo))Http::fail('Ese campo no existe.');
  $n=[];foreach($this->filas($objeto) as $f)foreach($this->claves($f,$campo,$objeto) as $k)$n[$k]=($n[$k]??0)+1;
  $out=[];foreach($n as $k=>$c)$out[]=['clave'=>(string)$k,'etiqueta'=>$this->etiqueta($objeto,$campo,(string)$k),'n'=>$c];
  usort($out,fn($a,$b)=>$b['n']<=>$a['n']?:strcmp($a['etiqueta'],$b['etiqueta']));
  return ['objeto'=>$objeto,'campo'=>$campo,'valores'=>array_slice($out,0,200)];
 }
 private function campoValido(string $objeto,string $campo):bool{
  return isset(self::OBJETOS[$objeto][2][$campo])||isset(self::TIEMPO[$campo])||(str_starts_with($campo,'cf:')&&preg_match('/^cf:[A-Za-z0-9_-]{1,80}$/D',$campo)&&in_array($objeto,['contactos','oportunidades'],true));
 }

 /* ---------- la consulta guardada en el widget ---------- */
 /** Deja solo lo conocido; falla con un mensaje claro si algo no existe. */
 public function validar(mixed $c,string $quien='El widget'):array{
  if(!is_array($c))Http::fail($quien.' no dice qué contar.');
  $o=(string)($c['objeto']??'');if(!isset(self::OBJETOS[$o]))Http::fail($quien.': no se puede contar «'.mb_substr($o,0,30).'».');
  $op=(string)($c['op']??'contar');if(!isset(self::OPS[$op]))$op='contar';
  $medidas=self::OBJETOS[$o][3];$medida=(string)($c['medida']??'');
  if($op!=='contar'){if(!$medidas)Http::fail($quien.': en «'.self::OBJETOS[$o][0].'» solo se puede contar.');if(!isset($medidas[$medida]))$medida=array_key_first($medidas);}else $medida='';
  $fecha=(string)($c['fecha']??'');if(!isset(self::OBJETOS[$o][1][$fecha]))$fecha=array_key_first(self::OBJETOS[$o][1]);
  $rango=(string)($c['rango']??'periodo');if(!isset(self::RANGOS[$rango]))$rango='periodo';
  $ag=(string)($c['agrupar']??'');if($ag!==''&&!$this->campoValido($o,$ag))Http::fail($quien.': no se puede agrupar por ese campo.');
  $ag2=(string)($c['agrupar2']??'');if($ag2!==''&&(!$this->campoValido($o,$ag2)||$ag===''||$ag2===$ag))$ag2='';
  $fs=[];foreach(array_slice(is_array($c['filtros']??null)?$c['filtros']:[],0,6) as $f){
   if(!is_array($f))continue;$campo=(string)($f['campo']??'');if(!$this->campoValido($o,$campo))Http::fail($quien.': un filtro usa un campo que no existe.');
   $vals=array_values(array_unique(array_map(fn($v)=>mb_substr(trim((string)$v),0,120),array_filter(is_array($f['valores']??null)?$f['valores']:[],'is_scalar'))));
   $vals=array_slice(array_values(array_filter($vals,fn($v)=>$v!=='')),0,50);if(!$vals)continue;
   $fs[]=['campo'=>$campo,'valores'=>$vals,'excluir'=>!empty($f['excluir'])];
  }
  return ['objeto'=>$o,'op'=>$op,'medida'=>$medida,'fecha'=>$fecha,'rango'=>$rango,'agrupar'=>$ag,'agrupar2'=>$ag2,'filtros'=>$fs];
 }
 /** Nombre corto para mostrar («Ventas · Sumar monto»). */
 public static function nombre(array $c):string{
  $o=self::OBJETOS[$c['objeto']]??null;if(!$o)return 'Consulta';
  $n=$c['op']==='contar'?$o[0]:self::OPS[$c['op']].' '.mb_strtolower($o[3][$c['medida']][1]??'').' de '.mb_strtolower($o[0]);
  return $n.($c['agrupar']!==''?' por '.mb_strtolower(self::OBJETOS[$c['objeto']][2][$c['agrupar']]??self::TIEMPO[$c['agrupar']]??'campo'):'');
 }

 /* ---------- cálculo ---------- */
 private function lote(array $b):array{
  $ps=$b['pedidos']??null;if(!is_array($ps)||!array_is_list($ps))Http::fail('Indica qué consultar.');if(count($ps)>30)Http::fail('Se consultan hasta 30 widgets a la vez.');
  $out=[];foreach($ps as $i=>$p){
   if(!is_array($p))continue;$k=mb_substr((string)($p['clave']??$i),0,40);
   try{$c=$this->validar($p['consulta']??null,'El widget '.($i+1));$out[$k]=$this->ejecutar($c,self::per($p['periodo']??null),(int)($p['top']??0));}
   catch(HttpError $e){$out[$k]=['valor'=>null,'unidad'=>'numero','motivo'=>$e->getMessage()];}
  }
  return ['resultados'=>$out];
 }
 private static function per(mixed $p):string{$p=(string)$p;return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$p)?$p:gmdate('Y-m');}
 /** Las filas que entran (rango y filtros). */
 private function seleccion(array $c,string $periodo):array{
  $o=$c['objeto'];$fk='_f_'.$c['fecha'];
  [$desde,$hasta]=match($c['rango']){'periodo'=>[$periodo.'-01',gmdate('Y-m-t',strtotime($periodo.'-01'))],'12m'=>[gmdate('Y-m-01',strtotime($periodo.'-01 -11 months')),gmdate('Y-m-t',strtotime($periodo.'-01'))],default=>[null,null]};
  $sel=[];
  foreach($this->filas($o) as $f){
   if($desde!==null){$d=$f[$fk]??null;if(!$d||substr($d,0,10)<$desde||substr($d,0,10)>$hasta)continue;}
   foreach($c['filtros'] as $flt){$ks=$this->claves($f,$flt['campo'],$o,$c['fecha']);$hay=(bool)array_intersect($ks,$flt['valores']);if($hay===$flt['excluir'])continue 2;}
   $sel[]=$f;
  }
  return $sel;
 }
 public function ejecutar(array $c,string $periodo,int $top=0):array{
  $o=$c['objeto'];$sel=$this->seleccion($c,$periodo);
  $u=$c['op']==='contar'?'numero':(self::OBJETOS[$o][3][$c['medida']][0]??'numero');
  $moneda=null;
  if($u==='dinero'){ // nunca se suman monedas distintas
   $mons=array_values(array_unique(array_map(fn($f)=>$f['_moneda']??$this->monedaSubcuenta(),$sel)));
   if(count($mons)>1&&$c['agrupar']!=='moneda'&&$c['agrupar2']!=='moneda')return ['valor'=>null,'unidad'=>$u,'n'=>count($sel),'motivo'=>'Hay datos en varias monedas ('.implode(', ',$mons).'). Filtra por una moneda o agrupa por moneda.'];
   $moneda=$mons[0]??$this->monedaSubcuenta();
  }
  $base=['unidad'=>$u,'moneda'=>$moneda,'n'=>count($sel),'nombre'=>self::nombre($c),'motivo'=>''];
  if($c['agrupar']===''){$v=$this->agregar($c,$sel);return $base+['valor'=>$v,'motivo'=>$v===null&&$c['op']!=='contar'?'No hay datos en este período.':''];}
  // agrupado (y, para el mapa de calor, en dos dimensiones)
  $g=[];foreach($sel as $f)foreach($this->claves($f,$c['agrupar'],$o,$c['fecha']) as $k){
   if($c['agrupar2']==='')$g[$k][]=$f;else foreach($this->claves($f,$c['agrupar2'],$o,$c['fecha']) as $k2)$g[$k][$k2][]=$f;}
  if($c['agrupar2']!==''){
   $cols=[];foreach($g as $fila)foreach($fila as $k2=>$_)$cols[$k2]=1;$cols=self::ordenar($c['agrupar2'],array_keys($cols),$this->ordenEtapas($c['agrupar2']));
   $filas=self::ordenar($c['agrupar'],array_keys($g),$this->ordenEtapas($c['agrupar']));
   return $base+['valor'=>$this->agregar($c,$sel),'filas'=>array_map(fn($k)=>['clave'=>(string)$k,'etiqueta'=>$this->etiqueta($o,$c['agrupar'],(string)$k)],$filas),
    'columnas'=>array_map(fn($k)=>['clave'=>(string)$k,'etiqueta'=>$this->etiqueta($o,$c['agrupar2'],(string)$k)],$cols),
    'celdas'=>array_map(fn($k)=>array_map(fn($k2)=>isset($g[$k][$k2])?$this->agregar($c,$g[$k][$k2]):null,$cols),$filas)];
  }
  $grupos=[];foreach($g as $k=>$fs)$grupos[]=['clave'=>(string)$k,'etiqueta'=>$this->etiqueta($o,$c['agrupar'],(string)$k),'valor'=>$this->agregar($c,$fs),'n'=>count($fs)];
  $natural=self::ordenar($c['agrupar'],array_column($grupos,'clave'),$this->ordenEtapas($c['agrupar']));
  if($natural!==null&&(isset(self::TIEMPO[$c['agrupar']])||$c['agrupar']==='etapa')){$pos=array_flip($natural);usort($grupos,fn($a,$b)=>($pos[$a['clave']]??999)<=>($pos[$b['clave']]??999));}
  else usort($grupos,fn($a,$b)=>($b['valor']??-INF)<=>($a['valor']??-INF)?:strcmp($a['etiqueta'],$b['etiqueta']));
  if($c['agrupar']==='etapa'&&$o==='oportunidades')$grupos=$this->conEtapasVacias($grupos,$sel);
  return $base+['valor'=>$this->agregar($c,$sel),'grupos'=>$top>0?array_slice($grupos,0,max(1,min(50,$top))):array_slice($grupos,0,100)];
 }
 private function agregar(array $c,array $fs):?float{
  if($c['op']==='contar')return (float)count($fs);
  $vs=[];foreach($fs as $f){$v=$f['_m_'.$c['medida']]??null;if(is_numeric($v))$vs[]=(float)$v;}
  if(!$vs)return $c['op']==='sumar'?0.0:null;
  return round(match($c['op']){'sumar'=>array_sum($vs),'promedio'=>array_sum($vs)/count($vs),'minimo'=>min($vs),'maximo'=>max($vs)},2);
 }
 /** Orden natural para tiempo y etapas; null = ordenar por valor. */
 private static function ordenar(string $campo,array $claves,?array $etapas):?array{
  $ks=array_map('strval',$claves);
  if(in_array($campo,['dia','semana','mes'],true)){sort($ks);return $ks;}
  if($campo==='dia_semana'||$campo==='hora'){usort($ks,fn($a,$b)=>(int)$a<=>(int)$b);return $ks;}
  if($campo==='etapa'&&$etapas){$pos=array_flip($etapas);usort($ks,fn($a,$b)=>($pos[$a]??999)<=>($pos[$b]??999));return $ks;}
  return $ks;
 }
 private function ordenEtapas(string $campo):?array{if($campo!=='etapa')return null;$o=[];foreach($this->embudos() as $p)foreach($p['stages'] as $s)$o[]=(string)$s['id'];return $o;}
 /** En el embudo de conversión también se ven las etapas sin oportunidades (del embudo filtrado o del único que hay). */
 private function conEtapasVacias(array $grupos,array $sel):array{
  $emb=array_values(array_unique(array_map(fn($f)=>$f['embudo'],$sel)));if(count($emb)!==1)return $grupos;
  $hay=array_column($grupos,null,'clave');$out=[];
  foreach($this->embudos() as $p)if((string)$p['id']===$emb[0])foreach($p['stages'] as $s){$k=(string)$s['id'];$out[]=$hay[$k]??['clave'=>$k,'etiqueta'=>(string)$s['name'],'valor'=>0.0,'n'=>0];}
  return $out?:$grupos;
 }

 /* ---------- filas de cada objeto (con los límites del rol) ---------- */
 private function conectada():bool{return $this->conectada??=\Agora\Core\MotorMobile::conectada($this->db(),$this->t());}
 private function soloLoSuyo():bool{return $this->api->a->assignedOnly();}
 private function miIdCuenta():string{return (string)((new \Agora\Services\UserLinks($this->db()))->forUser($this->t(),['id'=>$this->api->a->user['id'],'email'=>$this->api->a->user['email']])['id']??'-');}
 private function monedaSubcuenta():string{$r=$this->db()->one('SELECT currency FROM agora_tenants WHERE id=?',[$this->t()]);$c=strtoupper((string)($r['currency']??'USD'));return preg_match('/^[A-Z]{3}$/',$c)?$c:'USD';}
 public function filas(string $o):array{
  if(isset($this->filas[$o]))return $this->filas[$o];
  $t=$this->t();$uid=(int)($this->api->a->user['id']??0);$propio=$this->soloLoSuyo();
  $rec=function(string $kind)use($t,$uid,$propio):array{$sql="SELECT owner_id,status,data FROM agora_records WHERE tenant_id=? AND kind=?";$a=[$t,$kind];if($propio){$sql.=' AND owner_id=?';$a[]=$uid;}
   return array_values(array_filter(array_map(fn($r)=>is_array($d=json_decode((string)$r['data'],true))?$d+['_owner'=>$r['owner_id'],'_status'=>$r['status']]:null,$this->db()->all($sql.' ORDER BY seq DESC LIMIT 20000',$a))));};
  $out=[];
  switch($o){
   case 'ventas':
    foreach($rec('venta') as $d){
     $out[]=['_f_fecha'=>self::fecha($d['fecha']??null),'vendedor'=>trim((string)($d['vendedor']??'')),'producto'=>trim((string)($d['producto']??'')),'cobro'=>(string)($d['cobro']??''),
      'moneda'=>strtoupper((string)($d['moneda']??'')),'_moneda'=>strtoupper((string)($d['moneda']??''))?:null,'estado'=>(string)($d['estado']??$d['_status']??''),'origen'=>(string)($d['origen']??''),
      '_m_monto'=>(float)($d['monto']??0),'_t'=>trim((string)($d['cliente']['nombre']??$d['cliente']??'')).((string)($d['producto']??'')!==''?' · '.$d['producto']:'')];}
    break;
   case 'cobros': // cada cuota del plan de cobro de cada venta (así cuenta el módulo «Cobrado» y «Por cobrar» del mes)
    $hoy=gmdate('Y-m-d');
    foreach($rec('venta') as $d){$mon=strtoupper((string)($d['moneda']??''))?:null;$cli=trim((string)($d['cliente']['nombre']??''));
     foreach((is_array($d['plan']??null)?$d['plan']:[]) as $q){if(!is_array($q))continue;$pag=($q['estado']??'')==='pagado';$f=(string)($q['fecha']??'');
      $out[]=['_f_vence'=>self::fecha($f),'_f_pagada'=>$pag?self::fecha($q['pagadoEn']??null):null,'estado'=>$pag?'pagado':($f!==''&&$f<$hoy?'vencido':'pendiente'),'vendedor'=>trim((string)($d['vendedor']??'')),
       'producto'=>trim((string)($d['producto']??'')),'cobro'=>(string)($d['cobro']??''),'moneda'=>(string)$mon,'_moneda'=>$mon,'_m_monto'=>(float)($q['monto']??0),'_t'=>$cli.' · cuota '.(int)($q['n']??0)];}}
    break;
   case 'tareas':
    foreach($rec('task') as $d)$out[]=['_f_vence'=>self::fecha(($d['vence']??'').(isset($d['hora'])&&$d['hora']?' '.$d['hora']:'')),'responsable'=>trim((string)($d['responsable']??'')),'estado'=>(string)($d['estado']??''),'origen'=>(string)($d['origen']??''),'_t'=>(string)($d['titulo']??'')];
    break;
   case 'oportunidades':
    if($this->conectada()){$sql='SELECT * FROM agora_cuenta_oportunidades WHERE tenant_id=?';$a=[$t];if($propio){$sql.=' AND asignado_a=?';$a[]=$this->miIdCuenta();}
     foreach($this->db()->all($sql.' LIMIT 20000',$a) as $r){$d=json_decode((string)$r['datos'],true)?:[];
      $out[]=['_f_creada'=>self::fecha($r['creada_en']??($d['createdAt']??$d['dateAdded']??null)),'_f_ganada'=>self::fecha($r['ganada_en']),'embudo'=>(string)$r['embudo_id'],'etapa'=>(string)$r['etapa_id'],'estado'=>(string)$r['estado'],
       'responsable'=>(string)($r['asignado_a']??''),'_m_valor'=>$r['valor']!==null?(float)$r['valor']:null,'_cf'=>self::cf($d),'_t'=>trim((string)($r['nombre']?:($r['contacto_nombre']??'')))?:'Oportunidad'];}}
    else foreach($rec('opportunity') as $d)$out[]=['_f_creada'=>self::fecha($d['dateAdded']??$d['createdAt']??null),'_f_ganada'=>self::fecha($d['wonAt']??null),'embudo'=>(string)($d['pipelineId']??''),'etapa'=>(string)($d['pipelineStageId']??''),
     'estado'=>(string)($d['status']??''),'responsable'=>(string)($d['assignedTo']??''),'_m_valor'=>isset($d['monetaryValue'])&&is_numeric($d['monetaryValue'])?(float)$d['monetaryValue']:null,'_cf'=>self::cf($d),'_t'=>(string)($d['name']??'Oportunidad')];
    break;
   case 'contactos':
    if($this->conectada()){$sql='SELECT * FROM agora_cuenta_contactos WHERE tenant_id=?';$a=[$t];if($propio){$sql.=' AND asignado_a=?';$a[]=$this->miIdCuenta();}
     foreach($this->db()->all($sql.' LIMIT 20000',$a) as $r){$d=json_decode((string)$r['datos'],true)?:[];$tags=json_decode((string)$r['etiquetas'],true);
      $out[]=['_f_creado'=>self::fecha($r['creado_en']??($d['dateAdded']??null)),'responsable'=>(string)($r['asignado_a']??''),'etiqueta'=>is_array($tags)?array_values(array_map('strval',$tags)):[],'origen'=>(string)($d['source']??''),'_cf'=>self::cf($d),'_t'=>(string)($r['nombre']??'')];}}
    else foreach($rec('contact') as $d)$out[]=['_f_creado'=>self::fecha($d['dateAdded']??null),'responsable'=>(string)($d['assignedTo']??''),'etiqueta'=>array_values(array_map('strval',is_array($d['tags']??null)?$d['tags']:[])),'origen'=>(string)($d['source']??''),'_cf'=>self::cf($d),'_t'=>(string)($d['name']??'')];
    break;
   case 'conversaciones':
    if(!$this->conectada())break;
    $sql='SELECT payload,unread_count,assigned_to FROM conversation_index WHERE connection_id=?';$a=[$t];if($propio){$sql.=' AND assigned_to=?';$a[]=$this->miIdCuenta();}
    foreach($this->db()->all($sql.' LIMIT 20000',$a) as $r){$d=json_decode((string)$r['payload'],true)?:[];$ms=$d['lastMessageDate']??null;$f=is_numeric($ms)?gmdate('Y-m-d H:i:s',(int)($ms/1000)):self::fecha($ms);
     $sit=(int)$r['unread_count']>0?'sin_leer':((($d['lastMessageDirection']??'')==='inbound')?'esperando':'al_dia');
     $out[]=['_f_ultimo'=>$f,'canal'=>self::CANALES[(string)($d['lastMessageType']??'')]??'Otro','responsable'=>(string)($r['assigned_to']??''),'situacion'=>$sit,'_m_sin_leer'=>(float)$r['unread_count'],'_t'=>(string)($d['contactName']??$d['fullName']??'Conversación')];}
    break;
   case 'plantillas':
    try{$sql="SELECT label,author_name,external_user_id,requested_at,seen_at FROM whatsapp_flow_sends WHERE connection_id=? AND state='requested'";$a=[$t];if($propio){$sql.=' AND external_user_id=?';$a[]=$this->miIdCuenta();}
     foreach($this->db()->all($sql.' ORDER BY requested_at DESC LIMIT 20000',$a) as $r)$out[]=['_f_pedida'=>gmdate('Y-m-d H:i:s',(int)$r['requested_at']),'plantilla'=>(string)$r['label'],'quien'=>(string)$r['author_name'],'situacion'=>$r['seen_at']!==null?'vista':'pedida','_t'=>(string)$r['label']];}
    catch(\Throwable){}
    break;
  }
  return $this->filas[$o]=$out;
 }
 private static function fecha(mixed $v):?string{if(!is_string($v)||trim($v)==='')return null;$t=strtotime($v);return $t?gmdate('Y-m-d H:i:s',$t):null;}
 /** Campos personalizados de un contacto u oportunidad leído de la cuenta: [id => valor de texto]. */
 private static function cf(array $d):array{
  $out=[];foreach((is_array($d['customFields']??null)?$d['customFields']:[]) as $c){if(!is_array($c))continue;$id=(string)($c['id']??'');$v=$c['value']??$c['fieldValue']??$c['field_value']??null;
   if($id===''||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id)||$v===null||$v==='')continue;$out[$id]=is_array($v)?array_values(array_map(fn($x)=>mb_substr((string)$x,0,120),array_filter($v,'is_scalar'))):mb_substr((string)$v,0,120);}
  return $out;
 }
 /** Las claves de una fila para un campo (una etiqueta puede dar varias). */
 private function claves(array $f,string $campo,string $o,string $fecha=''):array{
  if(isset(self::TIEMPO[$campo])){$fk=$fecha!==''?'_f_'.$fecha:'_f_'.array_key_first(self::OBJETOS[$o][1]);$d=$f[$fk]??null;if(!$d)return ['—'];$ts=strtotime($d.' UTC');
   return [match($campo){'dia'=>substr($d,0,10),'semana'=>gmdate('o-\SW',$ts),'mes'=>substr($d,0,7),'dia_semana'=>gmdate('N',$ts),'hora'=>gmdate('G',$ts)}];}
  if(str_starts_with($campo,'cf:')){$v=$f['_cf'][substr($campo,3)]??null;return $v===null?['—']:(is_array($v)?($v?:['—']):[$v]);}
  $v=$f[$campo]??'';if(is_array($v))return $v?:['—'];return [(string)$v===''?'—':(string)$v];
 }
 private function etiqueta(string $o,string $campo,string $k):string{
  if($k==='—')return 'Sin dato';
  if($campo==='dia_semana')return self::DIAS[$k]??$k;
  if($campo==='hora')return sprintf('%02d:00',(int)$k);
  if($campo==='mes'){$m=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];return isset($m[(int)substr($k,5,2)-1])?$m[(int)substr($k,5,2)-1].' '.substr($k,0,4):$k;}
  if($campo==='semana')return 'Semana '.(int)substr($k,6).' de '.substr($k,0,4);
  if($campo==='dia'){$t=strtotime($k);return $t?gmdate('d/m/Y',$t):$k;}
  if($campo==='responsable'){$p=$this->personas();return $p[$k]??($o==='tareas'?$k:'Sin asignar');}
  if($campo==='embudo'){foreach($this->embudos() as $p)if((string)$p['id']===$k)return (string)$p['name'];return 'Embudo '.mb_substr($k,0,8);}
  if($campo==='etapa'){foreach($this->embudos() as $p)foreach($p['stages'] as $s)if((string)$s['id']===$k)return (string)$s['name'];return 'Etapa '.mb_substr($k,0,8);}
  if($campo==='estado'&&$o==='oportunidades')return self::ESTADOS_OP[$k]??$k;
  if($campo==='estado'&&$o==='cobros')return ['pagado'=>'Pagada','pendiente'=>'Pendiente','vencido'=>'Vencida'][$k]??$k;
  if($campo==='estado'&&$o==='tareas')return ['pendiente'=>'Pendiente','hecha'=>'Hecha','vencida'=>'Vencida'][$k]??$k;
  if($campo==='cobro')return self::COBROS[$k]??$k;
  if($campo==='situacion')return ['sin_leer'=>'Sin leer','esperando'=>'Esperando respuesta','al_dia'=>'Al día','vista'=>'Ya en la conversación','pedida'=>'Pedida, aún no aparece'][$k]??$k;
  if($campo==='origen')return ['manual'=>'A mano','migracion'=>'Importada','excel'=>'Excel','cuenta'=>'La cuenta','oportunidad'=>'Desde una oportunidad'][$k]??$k;
  return $k;
 }
 /** Nombres de las personas: usuarios de Ágora (por id) y del equipo de la cuenta (por su id en la cuenta). */
 private function personas():array{
  if($this->personas!==null)return $this->personas;$p=[];
  foreach($this->db()->all('SELECT id,name FROM agora_users WHERE local_tenant_id=? OR id IN (SELECT user_id FROM agora_assignments WHERE tenant_id=?)',[$this->t(),$this->t()]) as $u)$p[(string)$u['id']]=(string)$u['name'];
  try{foreach($this->db()->all('SELECT external_user_id,display_name FROM external_users WHERE connection_id=?',[$this->t()]) as $u)$p[(string)$u['external_user_id']]=(string)$u['display_name'];}catch(\Throwable){}
  return $this->personas=$p;
 }
 private function embudos():array{
  if($this->etapas!==null)return $this->etapas;$this->etapas=[];
  try{
   $vivo=$this->conectada()?\Agora\Core\MotorMobile::capturar($this->db(),$this->api->config,$this->api->a,'workspace/pipelines',['connectionId'=>(string)$this->t()]):null;
   $d=$vivo!==null?($vivo['status']<400?$vivo['body']:[]):$this->api->dispatch('workspace/pipelines','GET',[]);
   foreach((is_array($d['pipelines']??null)?$d['pipelines']:[]) as $p)if(is_array($p)&&isset($p['id']))$this->etapas[]=['id'=>(string)$p['id'],'name'=>(string)($p['name']??''),'stages'=>array_values(array_filter(is_array($p['stages']??null)?$p['stages']:[],fn($s)=>is_array($s)&&isset($s['id'])))];
  }catch(\Throwable){}
  return $this->etapas;
 }

 /* ---------- filas para la tarjeta con lista y la descarga ---------- */
 private function listar(array $b):array{
  $c=$this->validar($b['consulta']??null);$periodo=self::per($b['periodo']??null);$lim=max(1,min(500,(int)($b['limite']??10)));
  $sel=$this->seleccion($c,$periodo);$fk='_f_'.$c['fecha'];
  usort($sel,fn($a,$b)=>strcmp((string)($b[$fk]??''),(string)($a[$fk]??'')));
  $u=$c['medida']!==''?self::OBJETOS[$c['objeto']][3][$c['medida']][0]:null;
  return ['total'=>count($sel),'unidad'=>$u,'filas'=>array_map(fn($f)=>['texto'=>$f['_t']!==''?$f['_t']:'—','fecha'=>$f[$fk]??null,'valor'=>$c['medida']!==''?($f['_m_'.$c['medida']]??null):null,'moneda'=>$f['_moneda']??null],array_slice($sel,0,$lim))];
 }
}
