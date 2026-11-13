<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
/*
 * 2.1.19 · Tablas editables (los «trackers» de cada módulo: facturación, ventas, costos, catálogo…).
 * Sin migraciones:
 *  - Columnas de cada tabla      agora_settings «tabla.<clave>»          {columnas:[…], actualizado, por}
 *  - Valores de columnas propias agora_settings «tabla.<clave>.valores»  {firma:{columna:valor}}
 *  - Filas agregadas a mano      agora_records kind «fila», status = <clave>, period = AAAA-MM
 *
 *  GET    workspace/tablas?modulo=M&periodo=P   Columnas, filas manuales y valores de las tablas del módulo.
 *  PUT    workspace/tablas {clave,modulo,columnas}          Guarda columnas (administración).
 *  POST   workspace/tablas/filas {clave,modulo,periodo,datos}  Agrega una fila a mano.
 *  PUT    workspace/tablas/filas {clave,id,datos}             Edita una fila manual.
 *  DELETE workspace/tablas/filas?clave=&id=                   Quita una fila manual.
 *  POST   workspace/tablas/importar {clave,modulo,periodo,filas,simular}  Carga masiva (hasta 500 filas por lote).
 *  PUT    workspace/tablas/valor {clave,firma,columna,valor}  Valor de una columna propia en una fila del reporte.
 *
 * Una columna: {id, base, titulo, tipo, opciones, visible, requerido, orden}. id = «b:<nombre normalizado>»
 * para las columnas que trae el módulo y «cf_<nombre>» para las que agrega la empresa.
 */
final class Tablas {
 private const CLAVE='/^[a-zA-Z]{2,16}\.[a-z0-9]{6,12}$/D';
 private const COL='/^(b:[a-z0-9]{1,60}|cf_[a-z0-9_]{1,36})$/D';
 private const FILA='/^[a-z0-9]{8,40}$/D';
 public const MAX_FILAS=5000;
 public const LOTE=500;
 public function __construct(public DB $db,public Auth $auth){}

 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/tablas'&&$m==='GET')return $this->leer((string)($_GET['modulo']??''),(string)($_GET['periodo']??''));
  if($p==='workspace/tablas'&&$m==='PUT')return $this->guardarColumnas($b);
  if($p==='workspace/tablas/filas'&&$m==='POST')return $this->crear($b);
  if($p==='workspace/tablas/filas'&&$m==='PUT')return $this->editar($b);
  if($p==='workspace/tablas/filas'&&$m==='DELETE')return $this->quitar((string)($_GET['clave']??$b['clave']??''),(string)($_GET['id']??$b['id']??''));
  if($p==='workspace/tablas/importar'&&$m==='POST')return $this->importar($b);
  if($p==='workspace/tablas/valor'&&$m==='PUT')return $this->valor($b);
  Http::fail('Ruta no disponible.',404);
 }
 private function t():int{return (int)($this->auth->tenant??Http::fail('Selecciona una subcuenta.',403));}
 private function admin():bool{return in_array($this->auth->user['role']??'',['MASTER','SUPERADMIN','ADMIN'],true);}
 private static function clave(mixed $v):string{if(!is_string($v)||!preg_match(self::CLAVE,$v))Http::fail('Tabla desconocida.');return $v;}
 private static function modulo(mixed $v):string{if(!is_string($v)||!preg_match('/^[a-zA-Z]{2,16}$/D',$v))Http::fail('Módulo inválido.');return $v;}
 private static function periodo(mixed $v):string{if($v===null||$v==='')return '';return Http::period($v);}
 private function columnas(string $clave):array{$c=$this->db->setting($this->t(),'tabla.'.$clave,null);return is_array($c)&&is_array($c['columnas']??null)?$c['columnas']:[];}

 private function leer(string $modulo,string $periodo):array{
  $modulo=self::modulo($modulo);$periodo=self::periodo($periodo);$t=$this->t();
  $tablas=[];$valores=[];
  foreach($this->db->all('SELECT setting_key,data FROM agora_settings WHERE tenant_id=? AND setting_key LIKE ?',[$t,'tabla.'.$modulo.'.%']) as $r){
   $k=substr($r['setting_key'],6);$d=json_decode($r['data'],true)?:[];
   if(str_ends_with($k,'.valores'))$valores[substr($k,0,-8)]=$d;else $tablas[$k]=$d;
  }
  $args=[$t,'fila',$modulo.'.%'];$sql='SELECT uid,status,period,data,created_at FROM agora_records WHERE tenant_id=? AND kind=? AND status LIKE ?';
  if($periodo!==''){$sql.=' AND (period=? OR period=\'\')';$args[]=$periodo;}
  $filas=[];foreach($this->db->all($sql.' ORDER BY seq LIMIT '.self::MAX_FILAS,$args) as $r){$d=json_decode($r['data'],true)?:[];$filas[$r['status']][]=['id'=>$r['uid'],'periodo'=>$r['period'],'datos'=>$d['datos']??[],'creado'=>$d['creado']??$r['created_at'],'por'=>$d['por']??''];}
  return ['modulo'=>$modulo,'periodo'=>$periodo,'tablas'=>$tablas,'filas'=>$filas,'valores'=>$valores,'canEdit'=>$this->admin()];
 }

 /** Normaliza y valida la lista de columnas que manda el navegador. */
 public static function limpiarColumnas(mixed $lista):array{
  if(!is_array($lista)||!array_is_list($lista)||!count($lista)||count($lista)>80)Http::fail('Lista de columnas inválida (de 1 a 80).');
  $out=[];$ids=[];$nombres=[];$propias=0;$visibles=0;
  foreach($lista as $i=>$c){
   if(!is_array($c))Http::fail('Columna inválida.');
   $id=(string)($c['id']??'');if(!preg_match(self::COL,$id))Http::fail('Columna inválida.');
   if(isset($ids[$id]))Http::fail('Hay una columna repetida.');$ids[$id]=1;
   $titulo=preg_replace('/\s+/u',' ',Http::text($c,'titulo',60));$n=Migracion::norm($titulo);
   if($n==='')Http::fail('Cada columna necesita un nombre con letras o números.');
   if(isset($nombres[$n]))Http::fail("Dos columnas se llaman «{$titulo}». Usa nombres distintos: así Ágora reconoce cada columna del Excel.");$nombres[$n]=1;
   $tipo=$c['tipo']??'texto';if(!in_array($tipo,Migracion::TIPOS,true))$tipo='texto';
   $opc=null;
   if($tipo==='lista'){
    $v=$c['opciones']??null;if(!is_array($v)||!array_is_list($v)||!count($v)||count($v)>50)Http::fail("Escribe entre 1 y 50 opciones para «{$titulo}».");
    $opc=[];foreach($v as $o){$o=trim((string)$o);if($o===''||mb_strlen($o)>60)Http::fail("Revisa las opciones de «{$titulo}».");$opc[]=$o;}$opc=array_values(array_unique($opc));
   }
   $propia=str_starts_with($id,'cf_');if($propia&&++$propias>40)Http::fail('Hasta 40 columnas propias por tabla.');
   $visible=($c['visible']??true)!==false;if($visible)$visibles++;
   $out[]=['id'=>$id,'base'=>$propia?null:mb_substr((string)($c['base']??$titulo),0,80),'titulo'=>$titulo,'tipo'=>$tipo,'opciones'=>$opc,'visible'=>$visible,'requerido'=>($c['requerido']??false)===true,'orden'=>$i];
  }
  if(!$visibles)Http::fail('Deja al menos una columna visible.');
  return $out;
 }
 private function guardarColumnas(array $b):array{
  $this->auth->manage();$clave=self::clave($b['clave']??null);self::modulo($b['modulo']??explode('.',$clave)[0]);
  if(($b['restaurar']??false)===true){$this->db->run('DELETE FROM agora_settings WHERE tenant_id=? AND setting_key=?',[$this->t(),'tabla.'.$clave]);$this->auth->audit('tabla.restaurada','tablas',$clave);return ['ok'=>true,'clave'=>$clave,'tabla'=>null];}
  $cols=self::limpiarColumnas($b['columnas']??null);
  $d=['columnas'=>$cols,'actualizado'=>gmdate('c'),'por'=>$this->auth->user['name']??''];
  $this->db->saveSetting($this->t(),'tabla.'.$clave,$d);
  $this->auth->audit('tabla.columnas','tablas',$clave,['columnas'=>array_map(fn($c)=>$c['titulo'].($c['visible']?'':' (oculta)'),$cols)]);
  return ['ok'=>true,'clave'=>$clave,'tabla'=>$d];
 }
 /** [datos limpios, errores] de una fila, según las columnas guardadas (sin columnas guardadas, todo es texto). */
 private function validar(string $clave,mixed $datos,?array $cols=null):array{
  if(!is_array($datos)||($datos!==[]&&array_is_list($datos)))return [[],[['campo'=>'','mensaje'=>'Fila inválida.']]];
  $cols??=$this->columnas($clave);$por=[];foreach($cols as $c)$por[$c['id']]=$c;
  $out=[];$err=[];
  foreach($datos as $k=>$v){
   if(!is_string($k)||!preg_match(self::COL,$k))continue;
   $c=$por[$k]??['titulo'=>$k,'tipo'=>'texto','requerido'=>false,'opciones'=>null];
   [$val,$e]=Migracion::celda(['tipo'=>$c['tipo'],'requerido'=>false,'opciones'=>$c['opciones']??null],$v);
   if($e){$err[]=['campo'=>$c['titulo'],'mensaje'=>$e];continue;}
   if($val!==null&&$val!=='')$out[$k]=is_string($val)?mb_substr($val,0,2000):$val;
  }
  foreach($cols as $c)if(($c['requerido']??false)&&($c['visible']??true)&&!array_key_exists($c['id'],$out))$err[]=['campo'=>$c['titulo'],'mensaje'=>'Es obligatorio.'];
  if(!$out&&!$err)$err[]=['campo'=>'','mensaje'=>'La fila está vacía.'];
  if(strlen(DB::json($out))>60000)$err[]=['campo'=>'','mensaje'=>'La fila es demasiado grande.'];
  return [$out,$err];
 }
 private function cuantas(string $clave):int{return (int)$this->db->one('SELECT COUNT(*) n FROM agora_records WHERE tenant_id=? AND kind=? AND status=?',[$this->t(),'fila',$clave])['n'];}
 private function insertar(string $clave,string $periodo,array $datos):array{
  $uid=bin2hex(random_bytes(10));$d=['datos'=>$datos,'creado'=>gmdate('c'),'por'=>$this->auth->user['name']??''];
  $this->db->run('INSERT INTO agora_records(tenant_id,kind,uid,owner_id,label,status,period,data) VALUES(?,?,?,?,?,?,?,?)',[$this->t(),'fila',$uid,$this->auth->user['id']??null,mb_substr(implode(' · ',array_map('strval',array_slice($datos,0,4))),0,254),$clave,$periodo,DB::json($d)]);
  return ['id'=>$uid,'periodo'=>$periodo]+$d;
 }
 private function crear(array $b):array{
  $clave=self::clave($b['clave']??null);$periodo=self::periodo($b['periodo']??'');
  if($this->cuantas($clave)>=self::MAX_FILAS)Http::fail('Esta tabla llegó al máximo de '.self::MAX_FILAS.' filas agregadas a mano.');
  [$datos,$err]=$this->validar($clave,$b['datos']??null);
  if($err)Http::fail(implode(' · ',array_map(fn($e)=>($e['campo']?$e['campo'].': ':'').$e['mensaje'],$err)));
  $fila=$this->insertar($clave,$periodo,$datos);
  $this->db->run('UPDATE agora_tenants SET revision=revision+1 WHERE id=?',[$this->t()]);
  $this->auth->audit('tabla.fila.creada','tablas',$clave,['id'=>$fila['id'],'periodo'=>$periodo]);
  return ['ok'=>true,'fila'=>$fila];
 }
 private function propia(string $clave,string $id):array{
  if(!preg_match(self::FILA,$id))Http::fail('Fila inválida.');
  $r=$this->db->one('SELECT * FROM agora_records WHERE tenant_id=? AND kind=? AND uid=? AND status=?',[$this->t(),'fila',$id,$clave]);
  if(!$r)Http::fail('La fila ya no existe.',404);
  if(!$this->admin()&&(int)$r['owner_id']!==(int)($this->auth->user['id']??0))Http::fail('Solo quien agregó la fila o la administración puede cambiarla.',403);
  return $r;
 }
 private function editar(array $b):array{
  $clave=self::clave($b['clave']??null);$r=$this->propia($clave,(string)($b['id']??''));
  [$datos,$err]=$this->validar($clave,$b['datos']??null);
  if($err)Http::fail(implode(' · ',array_map(fn($e)=>($e['campo']?$e['campo'].': ':'').$e['mensaje'],$err)));
  $d=json_decode($r['data'],true)?:[];$d['datos']=$datos;$d['editado']=gmdate('c');$d['editadoPor']=$this->auth->user['name']??'';
  $this->db->run('UPDATE agora_records SET data=?,label=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE seq=?',[DB::json($d),mb_substr(implode(' · ',array_map('strval',array_slice($datos,0,4))),0,254),$r['seq']]);
  $this->auth->audit('tabla.fila.editada','tablas',$clave,['id'=>$r['uid']]);
  return ['ok'=>true,'fila'=>['id'=>$r['uid'],'periodo'=>$r['period'],'datos'=>$datos,'creado'=>$d['creado']??'','por'=>$d['por']??'']];
 }
 private function quitar(string $clave,string $id):array{
  $clave=self::clave($clave);$r=$this->propia($clave,$id);
  $this->db->run('DELETE FROM agora_records WHERE seq=?',[$r['seq']]);
  $this->auth->audit('tabla.fila.eliminada','tablas',$clave,['id'=>$id]);
  return ['ok'=>true];
 }
 private function importar(array $b):array{
  $this->auth->manage();
  $clave=self::clave($b['clave']??null);$periodo=self::periodo($b['periodo']??'');$simular=($b['simular']??false)===true;
  $filas=$b['filas']??null;if(!is_array($filas)||!array_is_list($filas)||count($filas)>self::LOTE)Http::fail('Envía hasta '.self::LOTE.' filas por lote.');
  $desde=isset($b['desde'])?Http::integer($b['desde'],1,10000000):2;
  $libres=self::MAX_FILAS-$this->cuantas($clave);$cols=$this->columnas($clave);
  $res=['filas'=>count($filas),'creados'=>0,'errores'=>[],'simulado'=>$simular];
  foreach($filas as $i=>$f){
   $n=$desde+$i;[$datos,$err]=$this->validar($clave,$f,$cols);
   if($err){foreach($err as $e)$res['errores'][]=['fila'=>$n]+$e;continue;}
   if($res['creados']>=$libres){$res['errores'][]=['fila'=>$n,'campo'=>'','mensaje'=>'La tabla llegó al máximo de '.self::MAX_FILAS.' filas.'];continue;}
   if(!$simular)$this->insertar($clave,$periodo,$datos);
   $res['creados']++;
  }
  if(!$simular&&$res['creados']){$this->db->run('UPDATE agora_tenants SET revision=revision+1 WHERE id=?',[$this->t()]);$this->auth->audit('tabla.importada','tablas',$clave,['filas'=>count($filas),'creados'=>$res['creados'],'errores'=>count($res['errores']),'periodo'=>$periodo]);}
  return $res;
 }
 private function valor(array $b):array{
  $clave=self::clave($b['clave']??null);$firma=(string)($b['firma']??'');$col=(string)($b['columna']??'');
  if(!preg_match('/^[a-z0-9]{4,16}$/D',$firma)||!preg_match('/^cf_[a-z0-9_]{1,36}$/D',$col))Http::fail('Celda inválida.');
  $cols=$this->columnas($clave);$c=null;foreach($cols as $x)if($x['id']===$col)$c=$x;
  if(!$c)Http::fail('Esa columna ya no existe.');
  [$val,$e]=Migracion::celda(['tipo'=>$c['tipo'],'requerido'=>false,'opciones'=>$c['opciones']??null],$b['valor']??null);
  if($e)Http::fail($c['titulo'].': '.$e);
  $t=$this->t();$v=$this->db->setting($t,'tabla.'.$clave.'.valores',[]);if(!is_array($v))$v=[];
  if($val===null||$val==='')unset($v[$firma][$col]);else $v[$firma][$col]=is_string($val)?mb_substr($val,0,2000):$val;
  if(isset($v[$firma])&&!$v[$firma])unset($v[$firma]);
  if(count($v)>self::MAX_FILAS)Http::fail('Esta tabla llegó al máximo de valores propios.');
  $this->db->saveSetting($t,'tabla.'.$clave.'.valores',$v);
  $this->auth->audit('tabla.valor','tablas',$clave,['columna'=>$c['titulo']]);
  return ['ok'=>true,'valor'=>$val];
 }
}
