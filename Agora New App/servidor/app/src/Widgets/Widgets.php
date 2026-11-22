<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Widgets;
use Agora\Core\{Http,HttpError};
use Agora\Controllers\Api;

/**
 * Agora New App 2.14.0–2.16.0 · Rutas de los widgets (métricas, fórmulas, tableros, gráficos y detalle):
 *   GET    workspace/metricas/catalogo            métricas, fórmulas, variables y funciones disponibles
 *   GET    workspace/metricas?ids=a,b&periodo=…   valores (métricas «mes.vendido» y fórmulas «f:12»), con unidad y motivo
 *   POST   workspace/formulas/probar              vista previa de una fórmula (sin guardar) con el error y su posición
 *   POST/PUT/DELETE workspace/formulas           crear, cambiar y borrar fórmulas (solo administración)
 *   GET/PUT/DELETE  workspace/variables          variables propias (valor general o por mes; escribir: solo administración)
 *   GET/PUT/DELETE  workspace/tableros           el tablero del equipo de un módulo (2.15.0), con gráficos y «quién lo ve» (2.16.0)
 *   GET    workspace/metricas/serie?ids=&hasta=&meses=   valores mes a mes para los gráficos (2.16.0 · fase 4)
 *   GET    workspace/metricas/detalle?id=&periodo=       qué mide una cifra y, si es fórmula, el valor de cada parte (2.16.0 · fase 5)
 *   POST   workspace/formulas/objetivo                   «Buscar objetivo»: qué valor necesita una variable para que la fórmula llegue a una meta (2.18.0)
 *   GET    workspace/alertas?periodo=                    widgets con aviso que están en «Bajo» o «Cerca» (2.18.0)
 *   GET    agency/consolidado?ids=&periodo=              las mismas cifras en todas las subcuentas de la agencia (2.18.0)
 *   workspace/plantillas…                                plantillas por rubro (2.18.0, ver Plantillas.php)
 * Quién crea: Administración, Supervisión y dueños (permiso widgets.editar). El resto ve los valores.
 */
final class Widgets {
 private array $cache=[];private int $vueltas=0;
 /** 2.18.0 · valores fijados a mano para «Buscar objetivo»: [clave de la variable => valor] en el mes $fijoPer. */
 private array $fijos=[];private string $fijoPer='';
 private const MODS=['MES ANTERIOR'=>-1,'ANO PASADO'=>-12,'MISMO MES DEL ANO PASADO'=>-12];
 public function __construct(private Api $api){}
 private function t():int{return (int)$this->api->a->tenant;}
 private function db(){return $this->api->db;}

 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/metricas/catalogo'&&$m==='GET')return $this->catalogo();
  if($p==='workspace/metricas'&&$m==='GET')return $this->valores();
  if($p==='workspace/metricas/serie'&&$m==='GET')return $this->serie();
  if($p==='workspace/metricas/detalle'&&$m==='GET')return $this->detalle();
  if($p==='workspace/formulas/objetivo'&&$m==='POST')return $this->objetivo($b);
  if($p==='workspace/alertas'&&$m==='GET')return $this->alertas();
  if(str_starts_with($p,'workspace/plantillas'))return (new Plantillas($this->api,$this))->route($p,$m,$b);
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
  [$ruta,$params,$porPeriodo]=Metricas::FUENTES[$f];$k=$f.'|'.($porPeriodo&&$params?$periodo:''); // el reporte trae todos los meses: se pide una vezif(isset($this->cache[$k]))return $this->cache[$k];
  // 2.16.0: lo que consulta la cuenta (cifras del mes) se guarda unos minutos por usuario, para no gastar la cuota con los gráficos.
  $uid=(int)($this->api->a->user['id']??0);$ck=$f.'|'.$periodo;$guarda=$f==='mes'&&$uid>0;
  if($guarda){try{$c=$this->db()->one('SELECT datos FROM agora_widget_cache WHERE tenant_id=? AND user_id=? AND clave=? AND expira>UTC_TIMESTAMP()',[$this->t(),$uid,$ck]);if($c&&is_array($d=json_decode((string)$c['datos'],true)))return $this->cache[$k]=['ok'=>true,'d'=>$d];}catch(\Throwable){}}
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
  if($guarda&&$r['ok']&&is_array($r['d'])){try{$ttl=$periodo<gmdate('Y-m')?600:60;
   $this->db()->run('INSERT INTO agora_widget_cache(tenant_id,user_id,clave,datos,expira) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE datos=VALUES(datos),expira=VALUES(expira)',[$this->t(),$uid,$ck,\Agora\Core\DB::json($r['d']),$ttl]);
   if(random_int(1,50)===1)$this->db()->run('DELETE FROM agora_widget_cache WHERE expira<UTC_TIMESTAMP()');}catch(\Throwable){}}
  return $this->cache[$k]=$r;
 }
 /** Valor de una métrica del diccionario. */
 public function metrica(string $id,string $periodo):array{
  [$nombre,$u,$f,$campo]=Metricas::LISTA[$id];$esc=(float)(Metricas::LISTA[$id][5]??1);$s=$this->fuente($f,$periodo);
  if(!$s['ok'])return ['v'=>null,'u'=>$u,'m'=>$s['m']];
  $v=Metricas::campo($s['d'],strtr($campo,['{moneda}'=>$this->moneda(),'{periodo}'=>$periodo]));
  if($f==='reporte'&&is_numeric($v))return ['v'=>round((float)$v/$esc,2),'u'=>$u,'m'=>''];
  if($f==='reporte')return ['v'=>null,'u'=>$u,'m'=>'El reporte de este mes no trae «'.$nombre.'».'];
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
   if(array_key_exists($clave,$this->fijos)&&$p===$this->fijoPer){$x=$this->variable($clave,$p);return ['v'=>$this->fijos[$clave],'u'=>$x&&$x['v']!==null?$x['u']:($this->unidadVariable($clave)??'numero'),'m'=>''];}
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
 /** Valor de «mes.vendido» o «f:12» en un mes. */
 public function valorDe(string $id,string $periodo):array{
  if(isset(Metricas::LISTA[$id])){$r=$this->metrica($id,$periodo);return ['valor'=>$r['v'],'unidad'=>$r['u'],'motivo'=>$r['m']];}
  if(preg_match('/^f:(\d{1,12})$/D',$id,$mm)&&($f=$this->db()->one('SELECT * FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[(int)$mm[1],$this->t()]))){$this->vueltas=0;return $this->calcularFormula($f,$periodo);}
  return ['valor'=>null,'unidad'=>'numero','motivo'=>'Esa cifra ya no existe.'];
 }
 private static function deHoy(string $id):bool{return isset(Metricas::LISTA[$id])&&!Metricas::FUENTES[Metricas::LISTA[$id][2]][2];}
 /** 2.16.0 · fase 4: los valores mes a mes de hasta 4 cifras (gráficos de línea, barras y área). */
 private function serie():array{
  $hasta=$this->periodo((string)($_GET['hasta']??''));$n=Http::integer($_GET['meses']??6,2,12);
  $ids=array_values(array_unique(array_filter(array_map('trim',explode(',',(string)($_GET['ids']??''))))));
  if(!$ids)Http::fail('Indica qué cifras quieres.');if(count($ids)>4)Http::fail('Un gráfico lleva hasta 4 series.');
  $meses=[];for($i=$n-1;$i>=0;$i--)$meses[]=self::mover($hasta,-$i);
  $out=[];foreach($ids as $id){$out[$id]=[];foreach($meses as $p)$out[$id][]=self::deHoy($id)?['valor'=>null,'unidad'=>'numero','motivo'=>'Es una cifra de hoy: no tiene historia por mes.']:$this->valorDe($id,$p);}
  return ['meses'=>$meses,'series'=>$out];
 }
 /** 2.16.0 · fase 5: qué mide una cifra y, si es una fórmula, cuánto vale cada parte en ese mes. */
 private function detalle():array{
  $id=trim((string)($_GET['id']??''));$periodo=$this->periodo();
  if(isset(Metricas::LISTA[$id])){[$n,$u,$f,,$d]=Metricas::LISTA[$id];return ['id'=>$id,'tipo'=>'metrica','nombre'=>$n,'descripcion'=>$d,'grupo'=>Metricas::FUENTES[$f][3],'deHoy'=>self::deHoy($id),'periodo'=>$periodo,'valor'=>$this->valorDe($id,$periodo),'partes'=>[]];}
  if(!preg_match('/^f:(\d{1,12})$/D',$id,$mm)||!($f=$this->db()->one('SELECT * FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[(int)$mm[1],$this->t()])))Http::fail('Esa cifra ya no existe.',404);
  $partes=[];
  try{$res=$this->resolver($periodo,[Metricas::clave($f['nombre'])]);
   foreach(Formula::refs(Formula::parse($f['expresion'])) as [$nom,$mod]){$k=$nom.($mod!==''?' · '.$mod:'');if(isset($partes[$k]))continue;$this->vueltas=0;
    try{$r=$res($nom,$mod,0);$partes[$k]=['nombre'=>$k,'valor'=>$r['v'],'unidad'=>$r['u'],'motivo'=>$r['m']];}catch(FormulaError $e){$partes[$k]=['nombre'=>$k,'valor'=>null,'unidad'=>'numero','motivo'=>$e->getMessage()];}}
  }catch(FormulaError){}
  return ['id'=>$id,'tipo'=>'formula','nombre'=>$f['nombre'],'descripcion'=>$f['descripcion'],'expresion'=>$f['expresion'],'grupo'=>'Fórmulas del equipo','deHoy'=>false,'periodo'=>$periodo,'valor'=>$this->valorDe($id,$periodo),'partes'=>array_values($partes)];
 }
 private function unidadVariable(string $clave):?string{$r=$this->db()->one('SELECT unidad FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? LIMIT 1',[$this->t(),$clave]);return $r['unidad']??null;}
 /** 2.18.0 · «Buscar objetivo»: el valor que necesita una variable para que la fórmula dé la meta (secante y, si no, bisección). */
 private function objetivo(array $b):array{
  $id=Http::integer($b['id']??0,1,PHP_INT_MAX);$f=$this->db()->one('SELECT * FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[$id,$this->t()]);if(!$f)Http::fail('Esa fórmula no existe.',404);
  $var=is_string($b['variable']??null)?trim($b['variable']):'';$clave=Metricas::clave($var);$unidad=$this->unidadVariable($clave);
  if($var===''||$unidad===null)Http::fail('Elige una de tus variables (por ejemplo, una meta o un sueldo).',422);
  if(!isset($b['meta'])||!is_numeric($b['meta'])||abs((float)$b['meta'])>1e14)Http::fail('Escribe la meta como un número.',422);
  $meta=(float)$b['meta'];$periodo=$this->periodo(isset($b['periodo'])?(string)$b['periodo']:null);
  $actual=$this->variable($clave,$periodo)['v']??null;
  $g=function(float $x)use($f,$periodo,$clave):?float{$this->fijos=[$clave=>$x];$this->fijoPer=$periodo;$this->vueltas=0;$r=$this->calcularFormula($f,$periodo);$this->fijos=[];return is_numeric($r['valor'])?(float)$r['valor']:null;};
  $x0=(float)($actual??0);$x1=$x0==0.0?1.0:$x0*1.1;$y0=$g($x0);$y1=$g($x1);
  if($y0===null||$y1===null)Http::fail('La fórmula no da un número con estos datos de '.$periodo.': no se puede buscar el objetivo.',422);
  if(abs($y1-$y0)<1e-12){$y2=$g($x0+1000);if($y2===null||abs($y2-$y0)<1e-12)Http::fail('«'.$f['nombre'].'» no cambia con «'.$var.'»: esa variable no está en la fórmula.',422);}
  $tol=max(1e-6,abs($meta)*1e-7);$x=null;$it=0;
  for($a=$x0,$fa=$y0-$meta,$c=$x1,$fc=$y1-$meta;$it<60;$it++){
   if(abs($fc)<=$tol){$x=$c;break;}if(abs($fc-$fa)<1e-15)break;
   $n=$c-$fc*($c-$a)/($fc-$fa);if(!is_finite($n)||abs($n)>1e15)break;$yn=$g($n);if($yn===null)break;$a=$c;$fa=$fc;$c=$n;$fc=$yn-$meta;
  }
  if($x===null){ // bisección: busca un tramo donde la fórmula cruce la meta
   $paso=max(1.0,abs($x0));$lo=null;$hi=null;$f0=$y0-$meta;
   // se prueba lejos (hacia arriba y abajo) y también hacia cero, por si la respuesta es mucho más chica que el valor de hoy
   for($k=0;$k<50&&$lo===null;$k++){foreach([$x0+$paso*2**$k,$x0-$paso*2**$k,$x0/2**($k+1),-$x0/2**($k+1)] as $z){if($z==$x0)continue;$yz=$g($z);if($yz!==null&&($yz-$meta)*$f0<=0){[$lo,$hi]=$z<$x0?[$z,$x0]:[$x0,$z];break;}}}
   if($lo===null)Http::fail('No hay un valor de «'.$var.'» con el que «'.$f['nombre'].'» llegue a esa meta.',422);
   $flo=$g($lo)-$meta;for($i=0;$i<200;$i++){$mid=($lo+$hi)/2;$fm=$g($mid)-$meta;if(abs($fm)<=$tol||($hi-$lo)<1e-9){$x=$mid;break;}if($fm*$flo<0)$hi=$mid;else{$lo=$mid;$flo=$fm;}}$x??=($lo+$hi)/2;$it+=$i;
  }
  $x=round($x,$unidad==='dinero'?2:6);$res=$g($x);
  return ['ok'=>true,'formula'=>$f['nombre'],'variable'=>$var,'unidad'=>$unidad,'meta'=>$meta,'valor'=>$x,'actual'=>$actual,'resultado'=>$res,'periodo'=>$periodo,'pasos'=>$it];
 }
 /** Mes de un widget: el del módulo (el pedido), el en curso, el anterior o uno fijo. */
 private function periodoWidget(array $w,string $modulo):string{$per=(string)($w['periodo']??'modulo');$hoy=gmdate('Y-m');return $per==='actual'?$hoy:($per==='anterior'?self::mover($hoy,-1):(preg_match('/^\d{4}-\d{2}$/D',$per)?$per:$modulo));}
 /** 2.18.0 · Avisos dentro de Ágora (sin notificaciones al teléfono, que están en pausa): widgets marcados «avisar» que quedaron en Bajo o Cerca. */
 private function alertas():array{
  $periodo=$this->periodo();$edita=$this->api->a->can('widgets.editar');$out=[];$n=0;
  foreach($this->db()->all("SELECT modulo,config FROM agora_dashboards WHERE tenant_id=? AND alcance='equipo' AND user_id=0 ORDER BY modulo",[$this->t()]) as $d){
   $cfg=json_decode((string)$d['config'],true);if(!is_array($cfg))continue;if(($cfg['visible']??'todos')==='administracion'&&!$edita)continue;
   foreach($cfg['widgets']??[] as $w){
    if(empty($w['alerta'])||empty($w['semaforo']['activo'])||($w['tipo']??'cifra')!=='cifra')continue;if(++$n>40)break 2;
    $id=($w['fuente']??'')==='formula'?'f:'.(int)$w['ref']:(string)$w['ref'];$p=$this->periodoWidget($w,$periodo);$r=$this->valorDe($id,$p);if($r['valor']===null)continue;
    $s=$w['semaforo'];$v=(float)$r['valor'];$menos=($s['mejor']??'mas')==='menos';
    $estado=($menos?$v<=$s['verde']:$v>=$s['verde'])?'meta':(($menos?$v<=$s['ambar']:$v>=$s['ambar'])?'cerca':'bajo');if($estado==='meta')continue;
    $nombre=$w['titulo']?:(isset(Metricas::LISTA[$id])?Metricas::LISTA[$id][0]:($this->db()->one('SELECT nombre FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[(int)$w['ref'],$this->t()])['nombre']??'Fórmula'));
    $out[]=['modulo'=>$d['modulo'],'widget'=>$w['id'],'titulo'=>$nombre,'valor'=>$v,'unidad'=>($w['formato']??'auto')!=='auto'?$w['formato']:$r['unidad'],'estado'=>$estado,'verde'=>$s['verde'],'mejor'=>$menos?'menos':'mas','periodo'=>$p];
   }
  }
  usort($out,fn($a,$b)=>[$a['estado']!=='bajo',$a['modulo']]<=>[$b['estado']!=='bajo',$b['modulo']]);
  return ['periodo'=>$periodo,'alertas'=>$out];
 }
 /** 2.18.0 · Consolidado de la agencia: las mismas cifras (las que van por mes) en cada subcuenta a su alcance, cada una en su moneda. */
 public static function consolidado(Api $api):array{
  $api->a->agency();$periodo=preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',(string)($_GET['periodo']??''))?(string)$_GET['periodo']:gmdate('Y-m');
  $ids=array_values(array_unique(array_filter(array_map('trim',explode(',',(string)($_GET['ids']??''))))));
  if(!$ids){$c=[];foreach(Metricas::LISTA as $id=>[$n,$u,$f])if(Metricas::FUENTES[$f][2])$c[]=['id'=>$id,'nombre'=>$n,'unidad'=>$u,'grupo'=>Metricas::FUENTES[$f][3]];return ['catalogo'=>$c];}
  if(count($ids)>8)Http::fail('Se comparan hasta 8 cifras a la vez.');
  foreach($ids as $id)if(!isset(Metricas::LISTA[$id])||!Metricas::FUENTES[Metricas::LISTA[$id][2]][2])Http::fail('Solo se consolidan cifras por mes del sistema.',422);
  $hdr=$_SERVER['HTTP_X_WORKSPACE_ID']??null;$filas=[];
  try{
   foreach($api->a->tenants() as $t){
    $tid=(int)$t['id'];try{$api->a->authorizeTenant($tid,true);}catch(\Throwable){continue;}
    $_SERVER['HTTP_X_WORKSPACE_ID']=(string)$tid;$api->a->tenant=$tid;$w=new self($api);$v=[];
    foreach($ids as $id){try{$v[$id]=$w->valorDe($id,$periodo);}catch(\Throwable){$v[$id]=['valor'=>null,'unidad'=>Metricas::LISTA[$id][1],'motivo'=>'No se pudo leer.'];}}
    $filas[]=['id'=>$tid,'nombre'=>$t['name'],'rubro'=>$t['industry']??'','moneda'=>strtoupper((string)($t['currency']??'USD')),'estado'=>$t['status']??'activa','valores'=>$v];
   }
  } finally {if($hdr===null)unset($_SERVER['HTTP_X_WORKSPACE_ID']);else $_SERVER['HTTP_X_WORKSPACE_ID']=$hdr;$api->a->tenant=null;}
  return ['periodo'=>$periodo,'metricas'=>array_map(fn($id)=>['id'=>$id,'nombre'=>Metricas::LISTA[$id][0],'unidad'=>Metricas::LISTA[$id][1]],$ids),'filas'=>$filas];
 }
 private function valores():array{
  $periodo=$this->periodo();$ids=array_values(array_unique(array_filter(array_map('trim',explode(',',(string)($_GET['ids']??''))))));
  if(!$ids)Http::fail('Indica qué cifras quieres.');if(count($ids)>40)Http::fail('Se piden hasta 40 cifras a la vez.');
  $out=[];
  foreach($ids as $id)$out[$id]=$this->valorDe($id,$periodo);
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
  foreach($this->db()->all('SELECT modulo,config FROM agora_dashboards WHERE tenant_id=?',[$this->t()]) as $d){foreach((json_decode((string)$d['config'],true)['widgets']??[]) as $w)foreach([$w,...(is_array($w['mas']??null)?$w['mas']:[])] as $x)if(($x['fuente']??'')==='formula'&&(int)($x['ref']??0)===$id)Http::fail('El tablero de «'.$d['modulo'].'» usa esta fórmula. Quita ese widget primero.',409);}
  $this->db()->run('DELETE FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[$id,$this->t()]);$this->api->a->audit('formula.borrada','widgets',(string)$id,['detalle'=>$f['nombre']]);
  return ['ok'=>true];
 }

 /* ---------- tableros (2.15.0 · fase 2): un tablero de widgets por módulo, el del equipo ---------- */
 private const TAM=['chico','ancho'];private const PER=['modulo','actual','anterior'];private const COMP=['','mes_anterior','ano_pasado'];private const FMT=['auto','dinero','numero','porcentaje'];private const TIPOS=['cifra','linea','barras','area','dona','tabla'];
 private static function modulo(string $m):string{if(!preg_match('/^[a-zA-Z][a-zA-Z_]{1,39}$/D',$m))Http::fail('Módulo no válido.');return $m;}
 private function tablero(string $modulo):array{
  $modulo=self::modulo($modulo);$r=$this->db()->one("SELECT config,version,updated_at FROM agora_dashboards WHERE tenant_id=? AND modulo=? AND alcance='equipo' AND user_id=0",[$this->t(),$modulo]);
  $cfg=$r?json_decode((string)$r['config'],true):null;$edita=$this->api->a->can('widgets.editar');$visible=($cfg['visible']??'todos')==='administracion'?'administracion':'todos';
  $ws=is_array($cfg['widgets']??null)?$cfg['widgets']:[];if($visible==='administracion'&&!$edita)$ws=[]; // 2.16.0 · fase 5: un tablero puede ser solo de la administración
  return ['modulo'=>$modulo,'widgets'=>$ws,'visible'=>$visible,'version'=>$r?(int)$r['version']:0,'puedeEditar'=>$edita];
 }
 /** Valida cada widget: solo campos conocidos, textos cortos, referencias que existen. */
 public function widget(mixed $w,int $i):array{
  if(!is_array($w))Http::fail('Widget '.($i+1).' no válido.');
  $id=(string)($w['id']??'');if(!preg_match('/^[a-z0-9]{6,16}$/D',$id))$id=bin2hex(random_bytes(5));
  $tipo=in_array($w['tipo']??'cifra',self::TIPOS,true)?($w['tipo']??'cifra'):'cifra';
  [$fuente,$ref]=$this->referencia($w,$i);
  $mas=[];foreach(array_slice(is_array($w['mas']??null)?$w['mas']:[],0,in_array($tipo,['dona','tabla'],true)?9:3) as $x){if(is_array($x)){$mas[]=array_combine(['fuente','ref'],$this->referencia($x,$i));}}
  if($tipo==='cifra')$mas=[];
  if(in_array($tipo,['linea','barras','area'],true))foreach([['fuente'=>$fuente,'ref'=>$ref],...$mas] as $x)if($x['fuente']==='metrica'&&self::deHoy($x['ref']))Http::fail('El widget '.($i+1).': «'.Metricas::LISTA[$x['ref']][0].'» es una cifra de hoy y no tiene historia por mes para un gráfico.');
  $txt=fn($k,$max)=>mb_substr(trim(strip_tags((string)($w[$k]??''))),0,$max);
  $per=(string)($w['periodo']??'modulo');if(!in_array($per,self::PER,true)&&!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$per))$per='modulo';
  $sem=is_array($w['semaforo']??null)?$w['semaforo']:[];$num=fn($v)=>is_numeric($v)&&abs((float)$v)<1e14?(float)$v:null;
  $semaforo=!empty($sem['activo'])&&$num($sem['verde']??null)!==null&&$num($sem['ambar']??null)!==null?['activo'=>true,'verde'=>$num($sem['verde']),'ambar'=>$num($sem['ambar']),'mejor'=>($sem['mejor']??'mas')==='menos'?'menos':'mas']:['activo'=>false];
  return ['id'=>$id,'fuente'=>$fuente,'ref'=>$ref,'titulo'=>$txt('titulo',60),'nota'=>$txt('nota',120),
   'tamano'=>in_array($w['tamano']??'',self::TAM,true)?$w['tamano']:'chico','periodo'=>$per,
   'comparar'=>in_array($w['comparar']??'',self::COMP,true)?$w['comparar']:'','formato'=>in_array($w['formato']??'',self::FMT,true)?$w['formato']:'auto',
   'decimales'=>isset($w['decimales'])&&is_numeric($w['decimales'])?max(0,min(4,(int)$w['decimales'])):2,'semaforo'=>$tipo==='cifra'?$semaforo:['activo'=>false],
   'alerta'=>$tipo==='cifra'&&$semaforo['activo']&&!empty($w['alerta']),'tipo'=>$tipo,'mas'=>$mas,'meses'=>isset($w['meses'])&&is_numeric($w['meses'])?max(3,min(12,(int)$w['meses'])):6,'top'=>isset($w['top'])&&is_numeric($w['top'])?max(3,min(10,(int)$w['top'])):5];
 }
 /** [fuente, ref] de un widget o de una serie extra, comprobando que exista. */
 private function referencia(array $w,int $i):array{
  $fuente=(string)($w['fuente']??'');$ref=$w['ref']??null;
  if($fuente==='metrica'){if(!is_string($ref)||!isset(Metricas::LISTA[$ref]))Http::fail('El widget '.($i+1).' usa una cifra que no existe.');}
  elseif($fuente==='formula'){$ref=is_numeric($ref)?(int)$ref:0;if(!$this->db()->one('SELECT id FROM agora_widget_formulas WHERE id=? AND tenant_id=?',[$ref,$this->t()]))Http::fail('El widget '.($i+1).' usa una fórmula que ya no existe.');}
  else Http::fail('El widget '.($i+1).' no dice qué mide.');
  return [$fuente,$ref];
 }
 private function guardarTablero(array $b):array{
  $modulo=self::modulo((string)($b['modulo']??''));$ws=$b['widgets']??null;if(!is_array($ws)||!array_is_list($ws))Http::fail('Lista de widgets no válida.');
  if(count($ws)>24)Http::fail('Un tablero lleva hasta 24 widgets.');
  $lista=[];$ids=[];foreach($ws as $i=>$w){$x=$this->widget($w,$i);while(isset($ids[$x['id']]))$x['id']=bin2hex(random_bytes(5));$ids[$x['id']]=1;$lista[]=$x;}
  $ver=isset($b['version'])?(int)$b['version']:-1;$visible=($b['visible']??'todos')==='administracion'?'administracion':'todos';
  return $this->db()->transaction(function()use($modulo,$lista,$ver,$visible){
   $r=$this->db()->one("SELECT id,version FROM agora_dashboards WHERE tenant_id=? AND modulo=? AND alcance='equipo' AND user_id=0 FOR UPDATE",[$this->t(),$modulo]);
   $actual=$r?(int)$r['version']:0;if($ver>=0&&$ver!==$actual)Http::fail('Otra persona cambió este tablero mientras lo editabas. Vuelve a abrirlo para ver lo último.',409);
   $cfg=\Agora\Core\DB::json(['widgets'=>$lista,'visible'=>$visible]);$u=$this->api->a->user['id']??null;
   if($r)$this->db()->run('UPDATE agora_dashboards SET config=?,version=version+1,updated_by=? WHERE id=?',[$cfg,$u,$r['id']]);
   else $this->db()->run("INSERT INTO agora_dashboards(tenant_id,modulo,alcance,user_id,config,version,updated_by) VALUES(?,?,'equipo',0,?,1,?)",[$this->t(),$modulo,$cfg,$u]);
   $this->api->a->audit('tablero.guardado','widgets',$modulo,['detalle'=>count($lista).' widgets']);
   return ['modulo'=>$modulo,'widgets'=>$lista,'visible'=>$visible,'version'=>$actual+1];
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
