<?php
declare(strict_types=1);
namespace Agora\Widgets;
use Agora\Core\{Http,HttpError};
use Agora\Controllers\Api;

/**
 * Agora New App 2.14.0 · Rutas de los widgets (fase 1 y motor de fórmulas):
 *   GET    workspace/metricas/catalogo            métricas, fórmulas, variables y funciones disponibles
 *   GET    workspace/metricas?ids=a,b&periodo=…   valores (métricas «mes.vendido» y fórmulas «f:12»), con unidad y motivo
 *   POST   workspace/formulas/probar              vista previa de una fórmula (sin guardar) con el error y su posición
 *   POST/PUT/DELETE workspace/formulas           crear, cambiar y borrar fórmulas (solo administración)
 *   GET/PUT/DELETE  workspace/variables          variables propias (valor general o por mes; escribir: solo administración)
 * Quién crea: Administración, Supervisión y dueños (permiso widgets.editar). El resto ve los valores.
 */
final class Widgets {
 private array $cache=[];private int $vueltas=0;
 private const MODS=['MES ANTERIOR'=>-1,'ANO PASADO'=>-12,'MISMO MES DEL ANO PASADO'=>-12];
 public function __construct(private Api $api){}
 private function t():int{return (int)$this->api->a->tenant;}
 private function db(){return $this->api->db;}

 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/metricas/catalogo'&&$m==='GET')return $this->catalogo();
  if($p==='workspace/metricas'&&$m==='GET')return $this->valores();
  if($p==='workspace/formulas/probar'&&$m==='POST')return $this->probar($b);
  if($p==='workspace/formulas'){if($m==='GET')return ['formulas'=>$this->formulas()];if($m==='POST')return $this->guardarFormula($b,null);if($m==='PUT')return $this->guardarFormula($b,Http::integer($b['id']??0,1,PHP_INT_MAX));if($m==='DELETE')return $this->borrarFormula(Http::integer($b['id']??$_GET['id']??0,1,PHP_INT_MAX));}
  if($p==='workspace/tableros'){if($m==='GET')return $this->tablero((string)($_GET['modulo']??''));if($m==='PUT')return $this->guardarTablero($b);if($m==='DELETE')return $this->borrarTablero((string)($b['modulo']??$_GET['modulo']??''));}
  if($p==='workspace/variables'){if($m==='GET')return ['variables'=>$this->variables()];if($m==='PUT')return $this->guardarVariable($b);if($m==='DELETE')return $this->borrarVariable($b);}
  Http::fail('Ruta no encontrada.',404);
 }

 /* ---------- lectura ---------- */
 private function periodo(?string $p=null):string{$p=$p??(string)($_GET['periodo']??'');return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$p)?$p:gmdate('Y-m');}
 private static function mover(string $p,int $meses):string{[$y,$m]=array_map('intval',explode('-',$p));$t=$y*12+($m-1)+$meses;return sprintf('%04d-%02d',intdiv($t,12),$t%12+1);}
 private function ctx(string $periodo):array{$h=(string)($_GET['hoy']??'');return ['hoy'=>preg_match('/^\d{4}-\d{2}-\d{2}$/D',$h)?$h:gmdate('Y-m-d'),'desde'=>$periodo.'-01','hasta'=>gmdate('Y-m-t',strtotime($periodo.'-01 12:00 UTC'))];}
 private function moneda():string{$r=$this->db()->one('SELECT currency FROM agora_tenants WHERE id=?',[$this->t()]);$c=strtoupper((string)($r['currency']??'USD'));return preg_match('/^[A-Z]{3}$/',$c)?$c:'USD';}
 public function formulas():array{return array_map(fn($r)=>['id'=>(int)$r['id'],'nombre'=>$r['nombre'],'expresion'=>$r['expresion'],'formato'=>$r['formato'],'decimales'=>(int)$r['decimales'],'descripcion'=>$r['descripcion']],$this->db()->all('SELECT * FROM agora_widget_formulas WHERE tenant_id=? ORDER BY nombre LIMIT 500',[$this->t()]));}
 public function variables():array{return array_map(fn($r)=>['nombre'=>$r['nombre'],'periodo'=>$r['periodo'],'valor'=>(float)$r['valor'],'unidad'=>$r['unidad']],$this->db()->all('SELECT * FROM agora_widget_variables WHERE tenant_id=? ORDER BY nombre,periodo LIMIT 2000',[$this->t()]));}
 private function catalogo():array{
  $m=[];foreach(Metricas::LISTA as $id=>[$n,$u,$f,,$d])$m[]=['id'=>$id,'nombre'=>$n,'unidad'=>$u,'grupo'=>Metricas::FUENTES[$f][3],'porPeriodo'=>Metricas::FUENTES[$f][2],'descripcion'=>$d];
  $fn=[];foreach(Formula::AYUDA as $k=>$a)$fn[]=['nombre'=>$k==='ANO'?'AÑO':$k,'ayuda'=>$a];
  return ['metricas'=>$m,'formulas'=>$this->formulas(),'variables'=>$this->variables(),'funciones'=>$fn,'puedeEditar'=>$this->api->a->can('widgets.editar')];
 }

 /** Llama por dentro la ruta de la fuente (mismo usuario, mismos permisos) y guarda la respuesta para esta petición. */
 private function fuente(string $f,string $periodo):array{
  [$ruta,$params,$porPeriodo]=Metricas::FUENTES[$f];$k=$f.'|'.($porPeriodo?$periodo:'');if(isset($this->cache[$k]))return $this->cache[$k];
  $get=$_GET;$q=[];foreach($params as $a=>$v)$q[$a]=strtr($v,['{periodo}'=>$periodo,'{conexion}'=>(string)$this->t()]);
  $_GET=$q+(isset($get['tz'])?['tz'=>$get['tz']]:[]);
  try{
   $vivo=\Agora\Core\MotorMobile::capturar($this->db(),$this->api->config,$this->api->a,$ruta,$_GET);
   if($vivo!==null)$r=$vivo['status']<400?['ok'=>true,'d'=>$vivo['body']]:['ok'=>false,'m'=>(string)($vivo['body']['error']??'No se pudo leer «'.Metricas::FUENTES[$f][3].'».')];
   else $r=['ok'=>true,'d'=>$this->api->dispatch($ruta,'GET',[])];
  }
  catch(HttpError $e){$r=['ok'=>false,'m'=>$e->getMessage()];}
  catch(\Throwable $e){$r=['ok'=>false,'m'=>'No se pudo leer «'.Metricas::FUENTES[$f][3].'».'];}
  finally{$_GET=$get;}
  return $this->cache[$k]=$r;
 }
 /** Valor de una métrica del diccionario. */
 public function metrica(string $id,string $periodo):array{
  [$nombre,$u,$f,$campo]=Metricas::LISTA[$id];$s=$this->fuente($f,$periodo);
  if(!$s['ok'])return ['v'=>null,'u'=>$u,'m'=>$s['m']];
  $v=Metricas::campo($s['d'],strtr($campo,['{moneda}'=>$this->moneda()]));
  if($v===null||!is_numeric($v))return ['v'=>$u==='dinero'&&$f==='ventas'?0.0:null,'u'=>$u,'m'=>$u==='dinero'&&$f==='ventas'?'':'Sin dato de «'.$nombre.'» en este período.'];
  return ['v'=>(float)$v,'u'=>$u,'m'=>''];
 }
 private function variable(string $clave,string $periodo):?array{
  $r=$this->db()->one("SELECT * FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? AND periodo IN (?, '') ORDER BY periodo DESC LIMIT 1",[$this->t(),$clave,$periodo]);
  if($r)return ['v'=>(float)$r['valor'],'u'=>$r['unidad'],'m'=>''];
  $hay=$this->db()->one('SELECT nombre FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? LIMIT 1',[$this->t(),$clave]);
  return $hay?['v'=>null,'u'=>'numero','m'=>'«'.$hay['nombre'].'» no tiene valor para este mes.']:null;
 }
 private function formulaPorClave(string $clave):?array{return $this->db()->one('SELECT * FROM agora_widget_formulas WHERE tenant_id=? AND nombre_clave=?',[$this->t(),$clave]);}
 /** El que resuelve {Nombre · modificador} dentro de una fórmula. */
 private function resolver(string $periodo,array $pila=[]):callable{
  return function(string $nombre,string $mod,int $pos)use($periodo,$pila):array{
   $p=$periodo;if($mod!==''){$k=preg_replace('/\s+/',' ',Formula::norm(trim($mod)));if(!isset(self::MODS[$k]))throw new FormulaError('«'.$mod.'» no se reconoce. Usa «mes anterior» o «año pasado».',$pos);$p=self::mover($periodo,self::MODS[$k]);}
   $clave=Metricas::clave($nombre);
   if($id=Metricas::porNombre($nombre)){if($mod!==''&&!Metricas::FUENTES[Metricas::LISTA[$id][2]][2])throw new FormulaError('«'.$nombre.'» es de hoy: no tiene mes anterior.',$pos);return $this->metrica($id,$p);}
   if($f=$this->formulaPorClave($clave)){
    if(in_array($clave,$pila,true)||count($pila)>12)throw new FormulaError('«'.$nombre.'» se usa a sí misma (una fórmula no puede dar vueltas).',$pos);
    if(++$this->vueltas>300)throw new FormulaError('La fórmula es demasiado compleja.',$pos);
    $r=Formula::evaluar(Formula::parse($f['expresion']),$this->resolver($p,[...$pila,$clave]),$this->ctx($p));
    return self::conFormato($r,$f['formato']);
   }
   if($v=$this->variable($clave,$p))return $v;
   throw new FormulaError('No hay ninguna cifra, fórmula o variable llamada «'.$nombre.'».',$pos);
  };
 }
 private static function conFormato(array $r,string $formato):array{if(in_array($formato,['dinero','numero','porcentaje','fecha'],true)&&!($r['u']==='fecha'xor $formato==='fecha'))$r['u']=$formato;return $r;}
 private function calcularFormula(array $f,string $periodo):array{
  try{$r=Formula::evaluar(Formula::parse($f['expresion']),$this->resolver($periodo,[Metricas::clave($f['nombre'])]),$this->ctx($periodo));$r=self::conFormato($r,$f['formato']);
   if($r['v']!==null)$r['v']=round($r['v'],$r['u']==='dinero'?2:(int)$f['decimales']+2);return ['valor'=>$r['v'],'unidad'=>$r['u'],'motivo'=>$r['m']];}
  catch(FormulaError $e){return ['valor'=>null,'unidad'=>'numero','motivo'=>'La fórmula tiene un error: '.$e->getMessage()];}
 }
 private function valores():array{
  $periodo=$this->periodo();$ids=array_values(array_unique(array_filter(array_map('trim',explode(',',(string)($_GET['ids']??''))))));
  if(!$ids)Http::fail('Indica qué cifras quieres.');if(count($ids)>40)Http::fail('Se piden hasta 40 cifras a la vez.');
  $out=[];
  foreach($ids as $id){
   if(isset(Metricas::LISTA[$id])){$r=$this->metrica($id,$periodo);$out[$id]=['valor'=>$r['v'],'unidad'=>$r['u'],'motivo'=>$r['m']];continue;}
   if(preg_match('/^f:(\d{1,12})$/D',$id,$mm)&&($f=$this->db()->one('SELECT * FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[(int)$mm[1],$this->t()]))){$this->vueltas=0;$out[$id]=$this->calcularFormula($f,$periodo);continue;}
   $out[$id]=['valor'=>null,'unidad'=>'numero','motivo'=>'Esa cifra ya no existe.'];
  }
  return ['periodo'=>$periodo,'valores'=>$out];
 }

 /* ---------- fórmulas ---------- */
 private function probar(array $b):array{
  $expr=(string)($b['expresion']??'');$periodo=$this->periodo(isset($b['periodo'])?(string)$b['periodo']:null);
  try{$ast=Formula::parse($expr);$this->vueltas=0;$pila=isset($b['nombre'])&&is_string($b['nombre'])?[Metricas::clave($b['nombre'])]:[];
   $r=Formula::evaluar($ast,$this->resolver($periodo,$pila),$this->ctx($periodo));
   $refs=array_values(array_unique(array_map(fn($x)=>$x[0].($x[1]!==''?' · '.$x[1]:''),Formula::refs($ast))));
   return ['ok'=>true,'valor'=>$r['v'],'unidad'=>$r['u'],'motivo'=>$r['m'],'usa'=>$refs,'periodo'=>$periodo];}
  catch(FormulaError $e){return ['ok'=>false,'error'=>$e->getMessage(),'pos'=>$e->pos];}
 }
 private static function nombreValido(mixed $n):string{
  if(!is_string($n))Http::fail('Escribe un nombre.');$n=trim(preg_replace('/\s+/u',' ',$n));
  if(mb_strlen($n)<2||mb_strlen($n)>60)Http::fail('El nombre debe tener entre 2 y 60 caracteres.');
  if(!preg_match('/^[\p{L}\p{N} .,%()\/+\-–_#ºª°]+$/u',$n))Http::fail('El nombre solo puede tener letras, números, espacios y . , % ( ) / + - _');
  return $n;
 }
 /** Un nombre no puede repetir el de una métrica, otra fórmula o una variable. */
 private function nombreLibre(string $n,string $tipo,?int $id=null):void{
  $c=Metricas::clave($n);
  if(Metricas::porNombre($n))Http::fail('«'.$n.'» ya es el nombre de una cifra del sistema. Elige otro.',409);
  $f=$this->formulaPorClave($c);if($f&&($tipo!=='formula'||(int)$f['id']!==$id))Http::fail('Ya hay una fórmula llamada «'.$f['nombre'].'».',409);
  if($tipo==='formula'&&$this->db()->one('SELECT 1 FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? LIMIT 1',[$this->t(),$c]))Http::fail('Ya hay una variable llamada «'.$n.'».',409);
 }
 private function guardarFormula(array $b,?int $id):array{
  $n=self::nombreValido($b['nombre']??null);$this->nombreLibre($n,'formula',$id);
  $expr=trim((string)($b['expresion']??''));$fmt=(string)($b['formato']??'auto');if(!in_array($fmt,['auto','dinero','numero','porcentaje','fecha'],true))Http::fail('Formato no válido.');
  $dec=isset($b['decimales'])?Http::integer($b['decimales'],0,6):2;$desc=mb_substr(trim((string)($b['descripcion']??'')),0,190);
  if($id&&!$this->db()->one('SELECT id FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[$id,$this->t()]))Http::fail('Esa fórmula no existe.',404);
  if(!$id&&(int)$this->db()->one('SELECT COUNT(*) n FROM agora_widget_formulas WHERE tenant_id=?',[$this->t()])['n']>=200)Http::fail('Se llegó al tope de 200 fórmulas.');
  $prueba=$this->probar(['expresion'=>$expr,'nombre'=>$n]);
  if(!$prueba['ok'])throw new HttpError('La fórmula tiene un error: '.$prueba['error'],422);
  $c=Metricas::clave($n);$u=$this->api->a->user['id']??null;
  if($id)$this->db()->run('UPDATE agora_widget_formulas SET nombre=?,nombre_clave=?,expresion=?,formato=?,decimales=?,descripcion=? WHERE id=? AND tenant_id=?',[$n,$c,$expr,$fmt,$dec,$desc,$id,$this->t()]);
  else{$this->db()->run('INSERT INTO agora_widget_formulas(tenant_id,nombre,nombre_clave,expresion,formato,decimales,descripcion,creado_por) VALUES(?,?,?,?,?,?,?,?)',[$this->t(),$n,$c,$expr,$fmt,$dec,$desc,$u]);$id=(int)$this->db()->pdo->lastInsertId();}
  $this->api->a->audit(isset($b['id'])?'formula.cambiada':'formula.creada','widgets',(string)$id,['detalle'=>$n]);
  return ['formula'=>['id'=>$id,'nombre'=>$n,'expresion'=>$expr,'formato'=>$fmt,'decimales'=>$dec,'descripcion'=>$desc],'prueba'=>$prueba];
 }
 private function borrarFormula(int $id):array{
  $f=$this->db()->one('SELECT * FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[$id,$this->t()]);if(!$f)Http::fail('Esa fórmula no existe.',404);
  // ¿la usa otra fórmula?
  foreach($this->db()->all('SELECT nombre,expresion FROM agora_widget_formulas WHERE tenant_id=? AND id<>?',[$this->t(),$id]) as $o){
   try{foreach(Formula::refs(Formula::parse($o['expresion'])) as [$r])if(Metricas::clave($r)===$f['nombre_clave'])Http::fail('«'.$o['nombre'].'» usa esta fórmula. Cámbiala primero.',409);}catch(FormulaError){}
  }
  foreach($this->db()->all('SELECT modulo,config FROM agora_dashboards WHERE tenant_id=?',[$this->t()]) as $d){foreach((json_decode((string)$d['config'],true)['widgets']??[]) as $w)if(($w['fuente']??'')==='formula'&&(int)($w['ref']??0)===$id)Http::fail('El tablero de «'.$d['modulo'].'» usa esta fórmula. Quita ese widget primero.',409);}
  $this->db()->run('DELETE FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[$id,$this->t()]);$this->api->a->audit('formula.borrada','widgets',(string)$id,['detalle'=>$f['nombre']]);
  return ['ok'=>true];
 }

 /* ---------- tableros (2.15.0 · fase 2): un tablero de widgets por módulo, el del equipo ---------- */
 private const TAM=['chico','ancho'];private const PER=['modulo','actual','anterior'];private const COMP=['','mes_anterior','ano_pasado'];private const FMT=['auto','dinero','numero','porcentaje'];
 private static function modulo(string $m):string{if(!preg_match('/^[a-z][a-z_]{1,39}$/D',$m))Http::fail('Módulo no válido.');return $m;}
 private function tablero(string $modulo):array{
  $modulo=self::modulo($modulo);$r=$this->db()->one("SELECT config,version,updated_at FROM agora_dashboards WHERE tenant_id=? AND modulo=? AND alcance='equipo' AND user_id=0",[$this->t(),$modulo]);
  $cfg=$r?json_decode((string)$r['config'],true):null;
  return ['modulo'=>$modulo,'widgets'=>is_array($cfg['widgets']??null)?$cfg['widgets']:[],'version'=>$r?(int)$r['version']:0,'puedeEditar'=>$this->api->a->can('widgets.editar')];
 }
 /** Valida cada widget: solo campos conocidos, textos cortos, referencias que existen. */
 private function widget(mixed $w,int $i):array{
  if(!is_array($w))Http::fail('Widget '.($i+1).' no válido.');
  $id=(string)($w['id']??'');if(!preg_match('/^[a-z0-9]{6,16}$/D',$id))$id=bin2hex(random_bytes(5));
  $fuente=(string)($w['fuente']??'');$ref=$w['ref']??null;
  if($fuente==='metrica'){if(!is_string($ref)||!isset(Metricas::LISTA[$ref]))Http::fail('El widget '.($i+1).' usa una cifra que no existe.');}
  elseif($fuente==='formula'){$ref=is_numeric($ref)?(int)$ref:0;if(!$this->db()->one('SELECT id FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[$ref,$this->t()]))Http::fail('El widget '.($i+1).' usa una fórmula que ya no existe.');}
  else Http::fail('El widget '.($i+1).' no dice qué mide.');
  $txt=fn($k,$max)=>mb_substr(trim(strip_tags((string)($w[$k]??''))),0,$max);
  $per=(string)($w['periodo']??'modulo');if(!in_array($per,self::PER,true)&&!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$per))$per='modulo';
  $sem=is_array($w['semaforo']??null)?$w['semaforo']:[];$num=fn($v)=>is_numeric($v)&&abs((float)$v)<1e14?(float)$v:null;
  $semaforo=!empty($sem['activo'])&&$num($sem['verde']??null)!==null&&$num($sem['ambar']??null)!==null?['activo'=>true,'verde'=>$num($sem['verde']),'ambar'=>$num($sem['ambar']),'mejor'=>($sem['mejor']??'mas')==='menos'?'menos':'mas']:['activo'=>false];
  return ['id'=>$id,'fuente'=>$fuente,'ref'=>$ref,'titulo'=>$txt('titulo',60),'nota'=>$txt('nota',120),
   'tamano'=>in_array($w['tamano']??'',self::TAM,true)?$w['tamano']:'chico','periodo'=>$per,
   'comparar'=>in_array($w['comparar']??'',self::COMP,true)?$w['comparar']:'','formato'=>in_array($w['formato']??'',self::FMT,true)?$w['formato']:'auto',
   'decimales'=>isset($w['decimales'])&&is_numeric($w['decimales'])?max(0,min(4,(int)$w['decimales'])):2,'semaforo'=>$semaforo];
 }
 private function guardarTablero(array $b):array{
  $modulo=self::modulo((string)($b['modulo']??''));$ws=$b['widgets']??null;if(!is_array($ws)||!array_is_list($ws))Http::fail('Lista de widgets no válida.');
  if(count($ws)>24)Http::fail('Un tablero lleva hasta 24 widgets.');
  $lista=[];$ids=[];foreach($ws as $i=>$w){$x=$this->widget($w,$i);while(isset($ids[$x['id']]))$x['id']=bin2hex(random_bytes(5));$ids[$x['id']]=1;$lista[]=$x;}
  $ver=isset($b['version'])?(int)$b['version']:-1;
  return $this->db()->transaction(function()use($modulo,$lista,$ver){
   $r=$this->db()->one("SELECT id,version FROM agora_dashboards WHERE tenant_id=? AND modulo=? AND alcance='equipo' AND user_id=0 FOR UPDATE",[$this->t(),$modulo]);
   $actual=$r?(int)$r['version']:0;if($ver>=0&&$ver!==$actual)Http::fail('Otra persona cambió este tablero mientras lo editabas. Vuelve a abrirlo para ver lo último.',409);
   $cfg=\Agora\Core\DB::json(['widgets'=>$lista]);$u=$this->api->a->user['id']??null;
   if($r)$this->db()->run('UPDATE agora_dashboards SET config=?,version=version+1,updated_by=? WHERE id=?',[$cfg,$u,$r['id']]);
   else $this->db()->run("INSERT INTO agora_dashboards(tenant_id,modulo,alcance,user_id,config,version,updated_by) VALUES(?,?,'equipo',0,?,1,?)",[$this->t(),$modulo,$cfg,$u]);
   $this->api->a->audit('tablero.guardado','widgets',$modulo,['detalle'=>count($lista).' widgets']);
   return ['modulo'=>$modulo,'widgets'=>$lista,'version'=>$actual+1];
  });
 }
 private function borrarTablero(string $modulo):array{$modulo=self::modulo($modulo);$this->db()->run("DELETE FROM agora_dashboards WHERE tenant_id=? AND modulo=? AND alcance='equipo' AND user_id=0",[$this->t(),$modulo]);$this->api->a->audit('tablero.restablecido','widgets',$modulo);return ['ok'=>true];}

 /* ---------- variables ---------- */
 private function guardarVariable(array $b):array{
  $n=self::nombreValido($b['nombre']??null);$c=Metricas::clave($n);
  if(Metricas::porNombre($n))Http::fail('«'.$n.'» ya es el nombre de una cifra del sistema. Elige otro.',409);
  if($this->formulaPorClave($c))Http::fail('Ya hay una fórmula llamada «'.$n.'».',409);
  $per=(string)($b['periodo']??'');if($per!==''&&!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$per))Http::fail('El mes no es válido.');
  if(!isset($b['valor'])||!is_numeric($b['valor']))Http::fail('Escribe un valor numérico.');$v=(float)$b['valor'];if(abs($v)>1e14)Http::fail('El valor es demasiado grande.');
  $u=(string)($b['unidad']??'numero');if(!in_array($u,['dinero','numero','porcentaje'],true))Http::fail('Unidad no válida.');
  $otra=$this->db()->one('SELECT unidad FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? LIMIT 1',[$this->t(),$c]);
  if($otra&&$otra['unidad']!==$u)Http::fail('Esta variable ya es de tipo «'.$otra['unidad'].'». Usa el mismo tipo en todos los meses.',409);
  if(!$otra&&(int)$this->db()->one('SELECT COUNT(DISTINCT nombre_clave) n FROM agora_widget_variables WHERE tenant_id=?',[$this->t()])['n']>=200)Http::fail('Se llegó al tope de 200 variables.');
  $this->db()->run('INSERT INTO agora_widget_variables(tenant_id,nombre,nombre_clave,periodo,valor,unidad) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),valor=VALUES(valor),unidad=VALUES(unidad)',[$this->t(),$n,$c,$per,$v,$u]);
  $this->api->a->audit('variable.guardada','widgets',$n,['detalle'=>$n.($per?" ($per)":'')]);
  return ['variable'=>['nombre'=>$n,'periodo'=>$per,'valor'=>$v,'unidad'=>$u]];
 }
 private function borrarVariable(array $b):array{
  $n=self::nombreValido($b['nombre']??$_GET['nombre']??null);$c=Metricas::clave($n);$per=(string)($b['periodo']??$_GET['periodo']??'');
  $this->db()->run('DELETE FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? AND periodo=?',[$this->t(),$c,$per]);
  $this->api->a->audit('variable.borrada','widgets',$n,['detalle'=>$n]);return ['ok'=>true];
 }
}
