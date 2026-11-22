<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Widgets;
use Agora\Core\{Http,DB};
use Agora\Controllers\Api;

/**
 * Agora New App 2.18.0 · Plantillas de widgets por rubro.
 *   GET    workspace/plantillas           las de Ágora (incluidas) y las de los dueños, primero las del rubro de la subcuenta
 *   POST   workspace/plantillas           guardar los widgets de esta subcuenta como plantilla (solo dueños: Master y Superadmin)
 *   POST   workspace/plantillas/aplicar   aplicar una plantilla («agregar» o «reemplazar»); crea las fórmulas y variables que falten
 *   DELETE workspace/plantillas           borrar una plantilla propia (solo dueños)
 * Las fórmulas se guardan por nombre (no por número) para poder llevarlas a otra subcuenta. Nunca llevan datos de clientes.
 */
final class Plantillas {
 private const W=['tipo'=>'cifra','titulo'=>'','nota'=>'','tamano'=>'chico','periodo'=>'modulo','comparar'=>'','formato'=>'auto','decimales'=>2,'semaforo'=>['activo'=>false],'mas'=>[],'meses'=>6,'top'=>5];
 /** Plantillas incluidas en Ágora. Usan cifras del sistema y unas pocas fórmulas. */
 public static function incluidas():array{
  $m=fn(string $ref,array $o=[])=>['fuente'=>'metrica','ref'=>$ref]+$o+self::W;
  $f=fn(string $nombre,array $o=[])=>['fuente'=>'formula','ref'=>$nombre]+$o+self::W;
  $s=fn(array $refs)=>array_map(fn($r)=>['fuente'=>'metrica','ref'=>$r],$refs);
  return [
   'b:educacion'=>['nombre'=>'Educación con reporte mensual','rubro'=>'Educación','descripcion'=>'Inscritos, recaudación y costo por inscrito, con las cifras del reporte de las asesoras.',
    'formulas'=>[['nombre'=>'Costo por inscrito','expresion'=>'{Inversión en campañas del reporte} ÷ {Inscritos del reporte}','formato'=>'dinero','decimales'=>2,'descripcion'=>'Inversión en campañas dividida entre los inscritos del mes.']],'variables'=>[],
    'tableros'=>['inicio'=>['visible'=>'todos','widgets'=>[$m('rep.inscritos',['comparar'=>'mes_anterior']),$m('rep.vendido',['comparar'=>'mes_anterior']),$m('rep.pendiente'),$f('Costo por inscrito')]],
     'ventas'=>['visible'=>'todos','widgets'=>[$m('rep.vendido',['tipo'=>'linea','titulo'=>'Vendido y pendiente por mes','tamano'=>'ancho','mas'=>$s(['rep.pendiente'])]),$m('rep.inscritos',['tipo'=>'barras','titulo'=>'Inscritos por mes'])]],
     'costos'=>['visible'=>'administracion','widgets'=>[$m('rep.costos_fijos',['tipo'=>'dona','titulo'=>'En qué se va el dinero','mas'=>$s(['rep.costos_variables','rep.anuncios'])])]],
     'publicidad'=>['visible'=>'todos','widgets'=>[$m('rep.anuncios'),$m('rep.cpl'),$m('rep.resultados',['tipo'=>'barras','titulo'=>'Resultados de campañas por mes'])]]]],
   'b:ventas'=>['nombre'=>'Ventas y metas','rubro'=>'','descripcion'=>'Vendido, meta, margen y ticket promedio, con su evolución por mes. Sirve para cualquier rubro.',
    'formulas'=>[['nombre'=>'Margen del mes','expresion'=>'{Vendido del mes} − {Costos fijos}','formato'=>'dinero','decimales'=>2,'descripcion'=>'Lo vendido menos los costos fijos.'],
     ['nombre'=>'Ticket promedio','expresion'=>'{Vendido del mes} ÷ {Cierres del mes}','formato'=>'dinero','decimales'=>2,'descripcion'=>'Lo vendido entre la cantidad de cierres.']],'variables'=>[],
    'tableros'=>['comercial'=>['visible'=>'todos','widgets'=>[$m('mes.avance',['semaforo'=>['activo'=>true,'verde'=>100,'ambar'=>70,'mejor'=>'mas'],'alerta'=>true]),$f('Ticket promedio'),$f('Margen del mes',['comparar'=>'mes_anterior']),
      $m('mes.vendido',['tipo'=>'barras','titulo'=>'Vendido por mes','tamano'=>'ancho','meses'=>12]),$m('mes.vendido',['tipo'=>'linea','titulo'=>'Vendido y meta','tamano'=>'ancho','mas'=>$s(['mes.meta'])])]]]],
   'b:cobranza'=>['nombre'=>'Cobranza','rubro'=>'Telecomunicaciones / ISP','descripcion'=>'Cobrado, por cobrar y vencido, y qué parte de lo vendido ya entró.',
    'formulas'=>[['nombre'=>'Cobrado sobre vendido','expresion'=>'PORCENTAJE({Cobrado}; {Vendido registrado})','formato'=>'porcentaje','decimales'=>1,'descripcion'=>'Qué parte de lo vendido ya se cobró.']],'variables'=>[],
    'tableros'=>['facturacion'=>['visible'=>'todos','widgets'=>[$m('ventas.cobrado',['comparar'=>'mes_anterior']),$m('ventas.por_cobrar'),$m('ventas.vencido',['semaforo'=>['activo'=>true,'verde'=>0,'ambar'=>500,'mejor'=>'menos'],'alerta'=>true]),$f('Cobrado sobre vendido',['semaforo'=>['activo'=>true,'verde'=>90,'ambar'=>70,'mejor'=>'mas']]),
      $m('ventas.cobrado',['tipo'=>'linea','titulo'=>'Cobrado y por cobrar','tamano'=>'ancho','mas'=>$s(['ventas.por_cobrar'])])]]]],
   'b:atencion'=>['nombre'=>'Atención al cliente','rubro'=>'','descripcion'=>'Conversaciones sin leer, esperando respuesta y fuera de tiempo, para el día a día.',
    'formulas'=>[],'variables'=>[],
    'tableros'=>['comercial'=>['visible'=>'todos','widgets'=>[$m('cuenta.sin_leer',['semaforo'=>['activo'=>true,'verde'=>5,'ambar'=>15,'mejor'=>'menos'],'alerta'=>true]),$m('cuenta.esperando'),$m('dia.fuera_tiempo',['semaforo'=>['activo'=>true,'verde'=>0,'ambar'=>3,'mejor'=>'menos'],'alerta'=>true]),$m('dia.leads')]]]],
  ];
 }
 public function __construct(private Api $api,private Widgets $w){}
 private function t():int{return (int)$this->api->a->tenant;}
 private function db(){return $this->api->db;}
 private function dueno():bool{return in_array($this->api->a->user['role']??'',['MASTER','SUPERADMIN'],true);}
 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/plantillas'&&$m==='GET')return $this->lista();
  if($p==='workspace/plantillas'&&$m==='POST')return $this->guardar($b);
  if($p==='workspace/plantillas/aplicar'&&$m==='POST')return $this->aplicar($b);
  if($p==='workspace/plantillas'&&$m==='DELETE')return $this->borrar($b);
  Http::fail('Ruta no encontrada.',404);
 }
 private function rubro():string{return (string)($this->db()->one('SELECT industry FROM agora_tenants WHERE id=?',[$this->t()])['industry']??'');}
 private function todas():array{
  $out=self::incluidas();foreach($out as &$x)$x['propia']=false;unset($x);
  foreach($this->db()->all('SELECT * FROM agora_widget_plantillas ORDER BY nombre LIMIT 200') as $r){$d=json_decode((string)$r['datos'],true);if(!is_array($d))continue;$out['p:'.$r['id']]=['nombre'=>$r['nombre'],'rubro'=>$r['rubro'],'descripcion'=>$r['descripcion'],'propia'=>true]+$d;}
  return $out;
 }
 private function lista():array{
  $rubro=$this->rubro();$l=[];
  foreach($this->todas() as $id=>$x){$n=0;foreach($x['tableros'] as $t)$n+=count($t['widgets']);
   $l[]=['id'=>$id,'nombre'=>$x['nombre'],'rubro'=>$x['rubro'],'descripcion'=>$x['descripcion'],'propia'=>$x['propia'],'modulos'=>array_keys($x['tableros']),'widgets'=>$n,'formulas'=>array_column($x['formulas'],'nombre'),'delRubro'=>$x['rubro']===''||$x['rubro']===$rubro];}
  usort($l,fn($a,$b)=>[!$a['delRubro'],$a['rubro']==='',$a['nombre']]<=>[!$b['delRubro'],$b['rubro']==='',$b['nombre']]);
  return ['rubro'=>$rubro,'plantillas'=>$l,'puedeCrear'=>$this->dueno(),'puedeAplicar'=>$this->api->a->can('widgets.editar')];
 }
 /** Guarda los widgets de esta subcuenta como plantilla: fórmulas por nombre, variables sin valores por mes. */
 private function guardar(array $b):array{
  if(!$this->dueno())Http::fail('Solo los dueños crean plantillas.',403);
  $nombre=trim(strip_tags((string)($b['nombre']??'')));if(mb_strlen($nombre)<3||mb_strlen($nombre)>80)Http::fail('El nombre de la plantilla debe tener entre 3 y 80 caracteres.');
  $rubro=mb_substr(trim(strip_tags((string)($b['rubro']??''))),0,60);$desc=mb_substr(trim(strip_tags((string)($b['descripcion']??''))),0,240);
  $fs=[];foreach($this->w->formulas() as $f)$fs[$f['id']]=$f;
  $tableros=[];$usadas=[];
  foreach($this->db()->all("SELECT modulo,config FROM agora_dashboards WHERE tenant_id=? AND alcance='equipo' AND user_id=0",[$this->t()]) as $d){
   $c=json_decode((string)$d['config'],true);$ws=[];
   foreach($c['widgets']??[] as $w){$conv=function(array $x)use($fs,&$usadas){if(($x['fuente']??'')==='formula'){$f=$fs[(int)$x['ref']]??null;if(!$f)return null;$usadas[$f['nombre']]=$f;return ['fuente'=>'formula','ref'=>$f['nombre']];}return ['fuente'=>'metrica','ref'=>$x['ref']];};
    $r=$conv($w);if(!$r)continue;$w=array_merge($w,$r);$w['mas']=array_values(array_filter(array_map($conv,$w['mas']??[])));unset($w['id']);$ws[]=$w;}
   if($ws)$tableros[$d['modulo']]=['visible'=>($c['visible']??'todos')==='administracion'?'administracion':'todos','widgets'=>$ws];
  }
  if(!$tableros)Http::fail('Esta subcuenta todavía no tiene widgets para guardar como plantilla.');
  // también las fórmulas que usan otras fórmulas y las variables que usan
  $vars=[];for($i=0;$i<5;$i++)foreach($usadas as $f){try{foreach(Formula::refs(Formula::parse($f['expresion'])) as [$n]){$c=Metricas::clave($n);foreach($fs as $o)if(Metricas::clave($o['nombre'])===$c)$usadas[$o['nombre']]=$o;$v=$this->db()->one("SELECT nombre,unidad,valor FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? ORDER BY periodo='' DESC LIMIT 1",[$this->t(),$c]);if($v)$vars[$v['nombre']]=['nombre'=>$v['nombre'],'unidad'=>$v['unidad'],'valor'=>(float)$v['valor']];}}catch(FormulaError){}}
  $datos=['formulas'=>array_values(array_map(fn($f)=>['nombre'=>$f['nombre'],'expresion'=>$f['expresion'],'formato'=>$f['formato'],'decimales'=>$f['decimales'],'descripcion'=>$f['descripcion']],$usadas)),'variables'=>array_values($vars),'tableros'=>$tableros];
  if((int)$this->db()->one('SELECT COUNT(*) n FROM agora_widget_plantillas')['n']>=100)Http::fail('Se llegó al tope de 100 plantillas.');
  $this->db()->run('INSERT INTO agora_widget_plantillas(nombre,rubro,descripcion,datos,creado_por) VALUES(?,?,?,?,?)',[$nombre,$rubro,$desc,DB::json($datos),$this->api->a->user['id']??null]);
  $id=(int)$this->db()->pdo->lastInsertId();$this->api->a->audit('plantilla.creada','widgets',(string)$id,['detalle'=>$nombre]);
  return ['ok'=>true,'id'=>'p:'.$id,'modulos'=>array_keys($tableros),'formulas'=>count($usadas),'variables'=>count($vars)];
 }
 private function aplicar(array $b):array{
  if(!$this->api->a->can('widgets.editar'))Http::fail('Solo la administración aplica plantillas.',403);
  $todas=$this->todas();$id=(string)($b['id']??'');$x=$todas[$id]??null;if(!$x)Http::fail('Esa plantilla no existe.',404);
  $modo=($b['modo']??'agregar')==='reemplazar'?'reemplazar':'agregar';$u=$this->api->a->user['id']??null;$creadas=0;$avisos=[];
  return $this->db()->transaction(function()use($x,$modo,$u,&$creadas,&$avisos,$id){
   foreach($x['variables'] as $v){$c=Metricas::clave($v['nombre']);if(!$this->db()->one('SELECT 1 FROM agora_widget_variables WHERE tenant_id=? AND nombre_clave=? LIMIT 1',[$this->t(),$c]))$this->db()->run("INSERT INTO agora_widget_variables(tenant_id,nombre,nombre_clave,periodo,valor,unidad) VALUES(?,?,?,'',?,?)",[$this->t(),$v['nombre'],$c,(float)$v['valor'],in_array($v['unidad'],['dinero','numero','porcentaje'],true)?$v['unidad']:'numero']);}
   $mapa=[];
   foreach($x['formulas'] as $f){$c=Metricas::clave($f['nombre']);$ya=$this->db()->one('SELECT id FROM agora_widget_formulas WHERE tenant_id=? AND nombre_clave=?',[$this->t(),$c]);
    if($ya){$mapa[$c]=(int)$ya['id'];continue;}
    try{Formula::parse($f['expresion']);}catch(FormulaError){$avisos[]='La fórmula «'.$f['nombre'].'» no se pudo crear.';continue;}
    $this->db()->run('INSERT INTO agora_widget_formulas(tenant_id,nombre,nombre_clave,expresion,formato,decimales,descripcion,creado_por) VALUES(?,?,?,?,?,?,?,?)',[$this->t(),$f['nombre'],$c,$f['expresion'],$f['formato'],(int)$f['decimales'],$f['descripcion'],$u]);$mapa[$c]=(int)$this->db()->pdo->lastInsertId();$creadas++;}
   $mods=[];
   foreach($x['tableros'] as $modulo=>$t){
    if(!preg_match('/^[a-zA-Z][a-zA-Z_]{1,39}$/D',$modulo))continue;
    $r=$this->db()->one("SELECT id,config,version FROM agora_dashboards WHERE tenant_id=? AND modulo=? AND alcance='equipo' AND user_id=0 FOR UPDATE",[$this->t(),$modulo]);
    $antes=$r?(json_decode((string)$r['config'],true)['widgets']??[]):[];$nuevos=[];
    foreach($t['widgets'] as $i=>$w){$ok=true;$fix=function(array $q)use($mapa,&$ok){if(($q['fuente']??'')==='formula'){$c=Metricas::clave((string)$q['ref']);if(!isset($mapa[$c])){$ok=false;return $q;}$q['ref']=$mapa[$c];}return $q;};
     $w=$fix($w);$w['mas']=array_map($fix,$w['mas']??[]);if(!$ok)continue;$w['id']=bin2hex(random_bytes(5));
     try{$nuevos[]=$this->w->widget($w,$i);}catch(\Agora\Core\HttpError $e){$avisos[]=$e->getMessage();}}
    $lista=array_slice($modo==='reemplazar'?$nuevos:[...$antes,...$nuevos],0,24);
    $cfg=DB::json(['widgets'=>$lista,'visible'=>$modo==='reemplazar'||!$r?$t['visible']:(json_decode((string)$r['config'],true)['visible']??'todos')]);
    if($r)$this->db()->run('UPDATE agora_dashboards SET config=?,version=version+1,updated_by=? WHERE id=?',[$cfg,$u,$r['id']]);
    else $this->db()->run("INSERT INTO agora_dashboards(tenant_id,modulo,alcance,user_id,config,version,updated_by) VALUES(?,?,'equipo',0,?,1,?)",[$this->t(),$modulo,$cfg,$u]);
    $mods[]=$modulo;
   }
   $this->api->a->audit('plantilla.aplicada','widgets',$id,['detalle'=>$x['nombre'].' ('.$modo.')']);
   return ['ok'=>true,'modulos'=>$mods,'formulasCreadas'=>$creadas,'avisos'=>$avisos];
  });
 }
 private function borrar(array $b):array{
  if(!$this->dueno())Http::fail('Solo los dueños borran plantillas.',403);
  $id=(string)($b['id']??'');if(!preg_match('/^p:(\d{1,12})$/D',$id,$m))Http::fail('Las plantillas incluidas en Ágora no se borran.',403);
  $r=$this->db()->one('SELECT nombre FROM agora_widget_plantillas WHERE id=?',[(int)$m[1]]);if(!$r)Http::fail('Esa plantilla no existe.',404);
  $this->db()->run('DELETE FROM agora_widget_plantillas WHERE id=?',[(int)$m[1]]);$this->api->a->audit('plantilla.borrada','widgets',$id,['detalle'=>$r['nombre']]);return ['ok'=>true];
 }
}
