<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;use Agora\Services\Records;
/*
 * 2.1.17 · campos personalizados y carga/descarga masiva (workspace/campos*, workspace/migracion*).
 * Sin migraciones: los campos de cada módulo viven en agora_settings con la clave «campos.<modulo>».
 *  GET  workspace/campos                      Módulos que se pueden migrar.
 *  GET  workspace/campos?modulo=X             Campos del módulo (los de fábrica fusionados con lo guardado).
 *  PUT  workspace/campos {modulo,campos}      Guarda orden, nombre, visibilidad, requeridos y campos propios (admin).
 *  PUT  workspace/campos {modulo,restaurar}   Vuelve a los campos de fábrica (admin).
 *  GET  workspace/migracion/plantilla?modulo=X  Columnas para la plantilla vacía y una fila de ejemplo.
 *  GET  workspace/migracion/exportar?modulo=X   Datos actuales con los nombres y el orden de los campos (por páginas de 1.000).
 *  POST workspace/migracion/importar {modulo,filas,modo,desde}  Hasta 500 filas por lote. modo: crear | actualizar | simular.
 * Los campos propios (personalizado=true) se guardan en customFields del registro como [{id,name,value}],
 * el mismo formato que usa la ficha de contactos.
 */
final class Migracion {
 public const TIPOS=['texto','numero','fecha','correo','telefono','lista','si_no'];
 public const LOTE=500;
 private const ID='/^[A-Za-z0-9_-]{1,80}$/D';
 /* Campo de fábrica: [clave, título, tipo, requerido, opciones, códigos internos de las opciones, ayuda]. Requerido = obligatorio siempre. */
 private const MODULOS=[
  'contactos'=>['nombre'=>'Contactos','icono'=>'users','kind'=>'contact','importa'=>true,'desc'=>'Las fichas de tus clientes y prospectos.','campos'=>[
   ['id','ID','texto',false,null,null,'Déjalo vacío para crear. Con el ID (o el correo) se actualiza la ficha.'],
   ['nombre','Nombre completo','texto',true,null,null,''],
   ['correo','Correo','correo',false,null,null,''],
   ['telefono','Teléfono','telefono',false,null,null,'Con código de país, por ejemplo +58 414 1234567.'],
   ['empresa','Empresa','texto',false,null,null,''],
   ['direccion','Dirección','texto',false,null,null,''],
   ['ciudad','Ciudad','texto',false,null,null,''],
   ['estado','Estado o provincia','texto',false,null,null,''],
   ['pais','País','texto',false,null,null,''],
   ['sitio','Sitio web','texto',false,null,null,''],
   ['etiquetas','Etiquetas','texto',false,null,null,'Sepáralas con coma.'],
   ['fuente','Origen','texto',false,null,null,'Por ejemplo Instagram, Referido.'],
   ['responsable','Responsable','correo',false,null,null,'Correo de un usuario de la subcuenta.'],
  ]],
  'oportunidades'=>['nombre'=>'Oportunidades','icono'=>'trend','kind'=>'opportunity','importa'=>true,'desc'=>'Los negocios de tus embudos.','campos'=>[
   ['id','ID','texto',false,null,null,'Déjalo vacío para crear.'],
   ['nombre','Nombre de la oportunidad','texto',true,null,null,''],
   ['contacto','Contacto (correo o ID)','texto',true,null,null,'El contacto debe existir: carga primero los contactos.'],
   ['embudo','Embudo','texto',false,null,null,'Nombre del embudo. Vacío = el primero.'],
   ['etapa','Etapa','texto',false,null,null,'Nombre de la etapa. Vacío = la primera.'],
   ['valor','Valor','numero',false,null,null,''],
   ['estado','Estado','lista',false,['Abierta','Ganada','Perdida','Abandonada'],['open','won','lost','abandoned'],''],
   ['fecha','Fecha de creación','fecha',false,null,null,'Formato 2026-11-13 o 13/11/2026.'],
   ['responsable','Responsable','correo',false,null,null,'Correo de un usuario de la subcuenta.'],
  ]],
  'productos'=>['nombre'=>'Productos y servicios','icono'=>'wallet','kind'=>null,'importa'=>true,'desc'=>'Tu catálogo de rubros y productos.','campos'=>[
   ['id','ID','texto',false,null,null,'Déjalo vacío para crear. Con el ID, el código o el nombre se actualiza.'],
   ['nombre','Nombre','texto',true,null,null,''],
   ['codigo','Código','texto',false,null,null,'Letras, números, punto y guion.'],
   ['rubro','Rubro','texto',false,null,null,'Si no existe, se crea.'],
   ['tipo','Tipo','lista',false,['Servicio','Producto'],['servicio','producto'],''],
   ['cobro','Forma de cobro','lista',true,['Recurrente','Pago único','Venta única'],['recurrente','unico','venta'],''],
   ['precio','Precio','numero',true,null,null,''],
   ['moneda','Moneda','lista',false,['USD','VES','EUR'],['USD','VES','EUR'],'Vacío = USD.'],
   ['frecuencia','Frecuencia','lista',false,['Semanal','Quincenal','Mensual','Trimestral','Semestral','Anual'],['semanal','quincenal','mensual','trimestral','semestral','anual'],'Solo si es recurrente.'],
   ['duracion','Número de cobros','numero',false,null,null,'Solo si es recurrente. Vacío = sin fin.'],
   ['existencia','Existencia','numero',false,null,null,'Solo si es venta única.'],
   ['descripcion','Descripción','texto',false,null,null,''],
   ['activo','Activo','si_no',false,null,null,'Sí o No. Vacío = Sí.'],
  ]],
  'ventas'=>['nombre'=>'Ventas','icono'=>'coins','kind'=>'venta','importa'=>true,'desc'=>'Las ventas ganadas, con su cobro.','campos'=>[
   ['id','ID','texto',false,null,null,'Déjalo vacío para crear.'],
   ['fecha','Fecha','fecha',true,null,null,'Formato 2026-11-13 o 13/11/2026.'],
   ['cliente','Cliente','texto',true,null,null,''],
   ['documento','Cédula o RIF','texto',false,null,null,''],
   ['correo','Correo','correo',false,null,null,''],
   ['telefono','Teléfono','telefono',false,null,null,''],
   ['producto','Producto','texto',false,null,null,''],
   ['monto','Monto','numero',true,null,null,''],
   ['moneda','Moneda','lista',false,['USD','VES','EUR'],['USD','VES','EUR'],'Vacío = USD.'],
   ['cobro','Forma de cobro','lista',false,['Recurrente','Pago único','Venta única'],['recurrente','unico','venta'],''],
   ['frecuencia','Frecuencia','lista',false,['Semanal','Quincenal','Mensual','Trimestral','Semestral','Anual'],['semanal','quincenal','mensual','trimestral','semestral','anual'],''],
   ['cuotas','Cuotas','numero',false,null,null,''],
   ['fechaInicio','Fecha de inicio','fecha',false,null,null,''],
   ['vendedor','Vendedor','texto',false,null,null,''],
   ['oportunidadId','ID de la oportunidad','texto',false,null,null,''],
  ]],
  'tareas'=>['nombre'=>'Tareas','icono'=>'check','kind'=>'task','importa'=>true,'desc'=>'Los pendientes del equipo.','campos'=>[
   ['id','ID','texto',false,null,null,'Déjalo vacío para crear.'],
   ['titulo','Tarea','texto',true,null,null,''],
   ['detalle','Detalle','texto',false,null,null,''],
   ['vence','Fecha límite','fecha',false,null,null,''],
   ['hora','Hora','texto',false,null,null,'Formato 24 h, por ejemplo 14:30.'],
   ['estado','Estado','lista',false,['Pendiente','Hecha'],['pendiente','hecha'],'Vacío = Pendiente.'],
   ['responsable','Responsable','correo',false,null,null,'Correo de un usuario. Vacío = tú.'],
   ['contacto','Contacto (correo o ID)','texto',false,null,null,''],
  ]],
  'usuarios'=>['nombre'=>'Usuarios','icono'=>'team','kind'=>null,'importa'=>false,'desc'=>'Solo descarga: los usuarios se crean desde Usuarios y roles.','campos'=>[
   ['nombre','Nombre','texto',true,null,null,''],
   ['correo','Correo','correo',true,null,null,''],
   ['rol','Rol','texto',false,null,null,''],
   ['estado','Estado','texto',false,null,null,''],
   ['ultimoAcceso','Último acceso','texto',false,null,null,''],
  ]],
 ];
 private array $cacheUsuarios=[];
 public function __construct(public DB $db,public Auth $auth,public Records $records){}

 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/campos'&&$m==='GET')return isset($_GET['modulo'])?$this->obtener((string)$_GET['modulo']):$this->modulos();
  if($p==='workspace/campos'&&$m==='PUT')return $this->guardar($b);
  if($p==='workspace/migracion/plantilla'&&$m==='GET')return $this->plantilla((string)($_GET['modulo']??''));
  if($p==='workspace/migracion/exportar'&&$m==='GET')return $this->exportar((string)($_GET['modulo']??''));
  if($p==='workspace/migracion/importar'&&$m==='POST')return $this->importar($b);
  Http::fail('Ruta no disponible.',404);
 }
 private function tenant():int{return (int)($this->auth->tenant??Http::fail('Selecciona una subcuenta.',403));}
 private function puedeEditar():bool{return in_array($this->auth->user['role']??'',['MASTER','SUPERADMIN','ADMIN'],true);}
 private static function def(string $modulo):array{return self::MODULOS[$modulo]??Http::fail('Módulo de datos desconocido.',404);}
 public static function norm(string $s):string{$s=mb_strtolower(trim($s));$s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);return preg_replace('/[^a-z0-9]+/','',$s)??'';}

 /* ---------- Campos ---------- */
 private function modulos():array{
  $out=[];foreach(self::MODULOS as $id=>$d)$out[]=['id'=>$id,'nombre'=>$d['nombre'],'icono'=>$d['icono'],'descripcion'=>$d['desc'],'importa'=>$d['importa']];
  return ['modulos'=>$out,'canEdit'=>$this->puedeEditar()];
 }
 /** Campos de fábrica del módulo, con el formato público. */
 public static function base(string $modulo):array{
  $o=[];foreach(self::def($modulo)['campos'] as $i=>[$clave,$titulo,$tipo,$req,$opc,$cod,$ayuda])$o[]=['clave'=>$clave,'titulo'=>$titulo,'tituloBase'=>$titulo,'tipo'=>$tipo,'opciones'=>$opc,'orden'=>$i,'visible'=>true,'requerido'=>$req,'bloqueado'=>$req,'personalizado'=>false,'ayuda'=>$ayuda];
  return $o;
 }
 /** Campos fusionados (fábrica + lo guardado por la subcuenta), ordenados. */
 public function campos(string $modulo):array{
  $base=self::base($modulo);$guardado=$this->db->setting($this->tenant(),'campos.'.$modulo,null);
  $g=[];foreach((is_array($guardado)&&is_array($guardado['campos']??null))?$guardado['campos']:[] as $c)if(is_array($c)&&is_string($c['clave']??null))$g[$c['clave']]=$c;
  $out=[];
  foreach($base as $c){$x=$g[$c['clave']]??null;if($x){$c['titulo']=is_string($x['titulo']??null)&&$x['titulo']!==''?$x['titulo']:$c['titulo'];$c['orden']=(int)($x['orden']??$c['orden']);if(!$c['bloqueado']){$c['visible']=($x['visible']??true)!==false;$c['requerido']=($x['requerido']??false)===true;}}$out[]=$c;}
  foreach($g as $x)if(($x['personalizado']??false)===true&&preg_match('/^cf_[a-z0-9_]{1,36}$/D',$x['clave']))$out[]=['clave'=>$x['clave'],'titulo'=>(string)($x['titulo']??$x['clave']),'tituloBase'=>null,'tipo'=>in_array($x['tipo']??'',self::TIPOS,true)?$x['tipo']:'texto','opciones'=>is_array($x['opciones']??null)?array_values($x['opciones']):null,'orden'=>(int)($x['orden']??999),'visible'=>($x['visible']??true)!==false,'requerido'=>($x['requerido']??false)===true,'bloqueado'=>false,'personalizado'=>true,'ayuda'=>''];
  usort($out,fn($a,$b)=>$a['orden']<=>$b['orden']);
  foreach($out as $i=>&$c)$c['orden']=$i;unset($c);
  return $out;
 }
 private function obtener(string $modulo):array{
  $d=self::def($modulo);$g=$this->db->setting($this->tenant(),'campos.'.$modulo,null);
  return ['modulo'=>$modulo,'nombre'=>$d['nombre'],'descripcion'=>$d['desc'],'importa'=>$d['importa'],'campos'=>$this->campos($modulo),'canEdit'=>$this->puedeEditar(),'actualizado'=>is_array($g)?($g['actualizado']??null):null,'por'=>is_array($g)?($g['por']??null):null];
 }
 private function guardar(array $b):array{
  $this->auth->manage();$t=$this->tenant();
  $modulo=Http::text($b,'modulo',30);$d=self::def($modulo);
  if(($b['restaurar']??false)===true){$this->db->saveSetting($t,'campos.'.$modulo,['campos'=>[],'actualizado'=>gmdate('c'),'por'=>$this->auth->user['name']??'']);$this->auth->audit('campos.actualizados','migracion',$modulo,['restaurados'=>true]);return $this->obtener($modulo)+['ok'=>true];}
  $lista=$b['campos']??null;if(!is_array($lista)||!array_is_list($lista)||count($lista)>80)Http::fail('Lista de campos inválida (hasta 80).');
  $base=[];foreach(self::base($modulo) as $c)$base[$c['clave']]=$c;
  $vistos=[];$titulos=[];$propios=0;$out=[];
  foreach($lista as $i=>$c){
   if(!is_array($c))Http::fail('Campo inválido.');$clave=Http::text($c,'clave',40);
   if(isset($vistos[$clave]))Http::fail("El campo {$clave} está repetido.");$vistos[$clave]=1;
   $titulo=preg_replace('/\s+/u',' ',Http::text($c,'titulo',60));
   $n=self::norm($titulo);if($n==='')Http::fail('Cada campo necesita un nombre con letras o números.');
   if(isset($titulos[$n]))Http::fail("Dos campos se llaman «{$titulo}». Usa nombres distintos para poder reconocer las columnas del Excel.");$titulos[$n]=1;
   $orden=isset($c['orden'])?Http::integer($c['orden'],0,999):$i;
   if(isset($base[$clave])){
    $bl=$base[$clave]['bloqueado'];
    $out[]=['clave'=>$clave,'titulo'=>$titulo,'orden'=>$orden,'visible'=>$bl?true:($c['visible']??true)!==false,'requerido'=>$bl?true:($c['requerido']??false)===true];
    continue;
   }
   if(($c['personalizado']??false)!==true||!preg_match('/^cf_[a-z0-9_]{1,36}$/D',$clave))Http::fail("El campo «{$titulo}» no existe en este módulo.");
   if(!$d['importa'])Http::fail('Este módulo es solo de descarga: no admite campos propios.');
   if(++$propios>40)Http::fail('Hasta 40 campos propios por módulo.');
   $tipo=$c['tipo']??'texto';if(!in_array($tipo,self::TIPOS,true))Http::fail("Tipo inválido en «{$titulo}».");
   $opc=null;
   if($tipo==='lista'){
    $v=$c['opciones']??null;if(!is_array($v)||!array_is_list($v)||!count($v)||count($v)>50)Http::fail("Escribe entre 1 y 50 opciones para «{$titulo}».");
    $opc=[];$on=[];foreach($v as $o){$o=is_string($o)?trim($o):'';if($o===''||mb_strlen($o)>60)Http::fail("Las opciones de «{$titulo}» deben tener entre 1 y 60 caracteres.");$k=self::norm($o);if(isset($on[$k]))continue;$on[$k]=1;$opc[]=$o;}
   }
   $out[]=['clave'=>$clave,'titulo'=>$titulo,'tipo'=>$tipo,'opciones'=>$opc,'orden'=>$orden,'visible'=>($c['visible']??true)!==false,'requerido'=>($c['requerido']??false)===true,'personalizado'=>true];
  }
  /* Un campo de fábrica que no vino se conserva como estaba. */
  $antes=$this->db->setting($t,'campos.'.$modulo,null);
  $this->db->saveSetting($t,'campos.'.$modulo,['campos'=>$out,'actualizado'=>gmdate('c'),'por'=>$this->auth->user['name']??'']);
  $this->auth->audit('campos.actualizados','migracion',$modulo,['campos'=>count($out),'propios'=>$propios,'antes'=>is_array($antes)?count($antes['campos']??[]):0]);
  return $this->obtener($modulo)+['ok'=>true];
 }

 /* ---------- Plantilla y exportación ---------- */
 private static function columnas(array $campos):array{return array_values(array_map(fn($c)=>['clave'=>$c['clave'],'titulo'=>$c['titulo'],'tipo'=>$c['tipo'],'requerido'=>$c['requerido'],'opciones'=>$c['opciones'],'ayuda'=>$c['ayuda']??''],array_filter($campos,fn($c)=>$c['visible'])));}
 private function plantilla(string $modulo):array{
  $this->auth->manage();$d=self::def($modulo);$campos=$this->campos($modulo);
  $ej=['texto'=>'Texto','numero'=>100,'fecha'=>gmdate('Y-m-d'),'correo'=>'nombre@ejemplo.com','telefono'=>'+58 414 1234567','si_no'=>'Sí'];
  $fila=[];foreach(self::columnas($campos) as $c)$fila[$c['clave']]=$c['clave']==='id'?'':($c['tipo']==='lista'?($c['opciones'][0]??''):($ej[$c['tipo']]??''));
  return ['modulo'=>$modulo,'nombre'=>$d['nombre'],'importa'=>$d['importa'],'columnas'=>self::columnas($campos),'ejemplo'=>[$fila]];
 }
 private function exportar(string $modulo):array{
  $this->auth->manage();$t=$this->tenant();$d=self::def($modulo);$campos=$this->campos($modulo);$cols=self::columnas($campos);
  $filas=[];$total=0;$sig=null;
  if($modulo==='productos'){
   $cat=$this->db->setting($t,'catalogo.productos',['rubros'=>[],'productos'=>[]]);$rub=array_column($cat['rubros']??[],'nombre','id');
   foreach($cat['productos']??[] as $p)$filas[]=$this->aFila($modulo,$campos,['id'=>$p['id'],'nombre'=>$p['nombre'],'codigo'=>$p['codigo']??'','rubro'=>$rub[$p['rubroId']??'']??'','tipo'=>$p['tipo']??'servicio','cobro'=>$p['cobro']??'','precio'=>$p['precio']??0,'moneda'=>$p['moneda']??'USD','frecuencia'=>$p['frecuencia']??'','duracion'=>$p['duracion']??'','existencia'=>$p['stock']??'','descripcion'=>$p['descripcion']??'','activo'=>($p['activo']??true)!==false],$p['customFields']??[]);
   $total=count($filas);
  }elseif($modulo==='usuarios'){
   $rows=$this->db->all('SELECT name,email,role,active,last_login_at FROM agora_users WHERE local_tenant_id=? ORDER BY id LIMIT 5000',[$t]);
   foreach($rows as $u)$filas[]=$this->aFila($modulo,$campos,['nombre'=>$u['name'],'correo'=>$u['email'],'rol'=>$u['role'],'estado'=>(int)$u['active']?'Activo':'Inactivo','ultimoAcceso'=>(string)($u['last_login_at']??'')],[]);
   $total=count($filas);
  }else{
   $kind=$d['kind'];$cursor=max(0,(int)($_GET['cursor']??0));$limit=min(1000,max(1,(int)($_GET['limit']??1000)));
   $where='tenant_id=? AND kind=?';$args=[$t,$kind];if($this->auth->assignedOnly()){$where.=' AND owner_id=?';$args[]=$this->auth->user['id'];}
   $total=(int)$this->db->one("SELECT COUNT(*) n FROM agora_records WHERE $where",$args)['n'];
   $rows=$this->db->all("SELECT seq,uid,owner_id,data FROM agora_records WHERE $where AND seq>? ORDER BY seq LIMIT ".($limit+1),[...$args,$cursor]);
   if(count($rows)>$limit){array_pop($rows);$sig=(string)end($rows)['seq'];}
   $ref=$this->referencias($kind,$rows);
   foreach($rows as $r){$x=json_decode($r['data'],true)?:[];$x['id']=$r['uid'];$filas[]=$this->aFila($modulo,$campos,$this->deRegistro($modulo,$x,(int)$r['owner_id'],$ref),$x['customFields']??[]);}
  }
  if((int)($_GET['cursor']??0)===0)$this->auth->audit('migracion.exportada','migracion',$modulo,['filas'=>$total]);
  return ['modulo'=>$modulo,'nombre'=>$d['nombre'],'columnas'=>$cols,'filas'=>$filas,'total'=>$total,'siguiente'=>$sig];
 }
 /** Nombres de contactos, embudos y usuarios para mostrar textos en vez de identificadores. */
 private function referencias(string $kind,array $rows):array{
  $ref=['contactos'=>[],'embudos'=>[],'usuarios'=>[]];$t=$this->tenant();
  foreach($this->db->all('SELECT id,name,email FROM agora_users WHERE local_tenant_id=? OR id IN (SELECT user_id FROM agora_assignments WHERE tenant_id=?) LIMIT 2000',[$t,$t]) as $u)$ref['usuarios'][(int)$u['id']]=$u;
  if($kind==='opportunity'||$kind==='task'){
   $ids=[];foreach($rows as $r){$x=json_decode($r['data'],true)?:[];if(!empty($x['contactId']))$ids[(string)$x['contactId']]=1;}
   foreach(array_chunk(array_keys($ids),400) as $chunk){$in=implode(',',array_fill(0,count($chunk),'?'));foreach($this->db->all("SELECT uid,data FROM agora_records WHERE tenant_id=? AND kind='contact' AND uid IN ($in)",[$t,...$chunk]) as $c){$x=json_decode($c['data'],true)?:[];$ref['contactos'][$c['uid']]=$x['email']??'' ?: ($x['name']??$c['uid']);}}
  }
  if($kind==='opportunity')foreach($this->db->all("SELECT uid,data FROM agora_records WHERE tenant_id=? AND kind='pipeline' LIMIT 200",[$t]) as $p)$ref['embudos'][$p['uid']]=json_decode($p['data'],true)?:[];
  return $ref;
 }
 private function deRegistro(string $modulo,array $x,int $owner,array $ref):array{
  $correoDe=fn($id)=>$ref['usuarios'][(int)$id]['email']??'';
  if($modulo==='contactos')return ['id'=>$x['id'],'nombre'=>$x['name']??trim(($x['firstName']??'').' '.($x['lastName']??'')),'correo'=>$x['email']??'','telefono'=>$x['phone']??'','empresa'=>$x['companyName']??'','direccion'=>$x['address1']??'','ciudad'=>$x['city']??'','estado'=>$x['state']??'','pais'=>$x['country']??'','sitio'=>$x['website']??'','etiquetas'=>implode(', ',array_filter((array)($x['tags']??[]),'is_string')),'fuente'=>$x['source']??'','responsable'=>$correoDe($x['assignedTo']??$owner)];
  if($modulo==='oportunidades'){$p=$ref['embudos'][$x['pipelineId']??'']??[];$etapa='';foreach($p['stages']??[] as $s)if(($s['id']??'')===($x['pipelineStageId']??''))$etapa=$s['name']??'';return ['id'=>$x['id'],'nombre'=>$x['name']??'','contacto'=>$ref['contactos'][$x['contactId']??'']??($x['contactId']??''),'embudo'=>$p['name']??($x['pipelineId']??''),'etapa'=>$etapa,'valor'=>$x['monetaryValue']??'','estado'=>$x['status']??'open','fecha'=>substr((string)($x['dateAdded']??''),0,10),'responsable'=>$correoDe($x['assignedTo']??$owner)];}
  if($modulo==='tareas')return ['id'=>$x['id'],'titulo'=>$x['titulo']??'','detalle'=>$x['detalle']??'','vence'=>$x['vence']??'','hora'=>$x['hora']??'','estado'=>$x['estado']??'pendiente','responsable'=>$correoDe($x['responsableId']??$owner),'contacto'=>$ref['contactos'][$x['contactId']??'']??($x['contacto']??'')];
  $o=['id'=>$x['id']];foreach(self::def($modulo)['campos'] as [$k])if($k!=='id')$o[$k]=$x[$k]??'';if(is_array($o['cliente']??null))$o['cliente']=(string)($o['cliente']['nombre']??'');return $o; // ventas: las claves se guardan tal cual (la venta ganada guarda cliente como objeto)
 }
 /** Fila de salida: claves de los campos visibles; listas con su etiqueta; sí/no en texto. */
 private function aFila(string $modulo,array $campos,array $v,mixed $custom):array{
  $cf=self::customMapa($custom);$cod=[];foreach(self::def($modulo)['campos'] as $c)if($c[5])$cod[$c[0]]=array_combine($c[5],$c[4]);
  $o=[];
  foreach($campos as $c){if(!$c['visible'])continue;$k=$c['clave'];$x=$c['personalizado']?($cf[$k]??''):($v[$k]??'');
   if(isset($cod[$k])&&is_string($x))$x=$cod[$k][$x]??$x;
   if($c['tipo']==='si_no'&&is_bool($x))$x=$x?'Sí':'No';
   if(is_array($x))$x=implode(', ',array_map('strval',$x));
   $o[$k]=$x===null?'':$x;}
  return $o;
 }
 private static function customMapa(mixed $cf):array{
  $o=[];if(!is_array($cf))return $o;
  if(array_is_list($cf)){foreach($cf as $f)if(is_array($f)&&(isset($f['id'])||isset($f['clave'])))$o[(string)($f['id']??$f['clave'])]=$f['value']??$f['valor']??'';} // {id,value} o {clave,valor} (venta ganada)
  else foreach($cf as $k=>$v)$o[(string)$k]=$v;
  return $o;
 }

 /* ---------- Validación de celdas ---------- */
 public static function numero(mixed $v):?float{
  if(is_int($v)||is_float($v))return is_finite((float)$v)?(float)$v:null;
  $s=preg_replace('/[\s\x{00A0}$€]|bs\.?|usd|ves|eur/iu','',(string)$v)??'';
  if($s===''||!preg_match('/^-?[0-9.,]+$/D',$s))return null;
  $c=strrpos($s,',');$p=strrpos($s,'.');
  if($c!==false&&$p!==false){if($c>$p)$s=str_replace(['.',','],['','.'],$s);else $s=str_replace(',','',$s);}
  elseif($c!==false){if(substr_count($s,',')>1)$s=str_replace(',','',$s);else $s=str_replace(',','.',$s);}
  elseif($p!==false&&preg_match('/^-?\d{1,3}(\.\d{3})+$/D',$s))$s=str_replace('.','',$s);
  return is_numeric($s)?(float)$s:null;
 }
 public static function fecha(mixed $v):?string{
  if(is_int($v)||is_float($v)||(is_string($v)&&preg_match('/^\d{4,5}(\.\d+)?$/D',trim($v)))){$n=(float)$v;if($n<1||$n>2958465)return null;$ts=(int)round(($n-25569)*86400);return gmdate('Y-m-d',$ts);}
  $s=trim((string)$v);
  if(preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ][0-9:.]+Z?)?$/D',$s,$m))[$y,$mo,$d]=[(int)$m[1],(int)$m[2],(int)$m[3]];
  elseif(preg_match('~^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$~D',$s,$m)){[$d,$mo,$y]=[(int)$m[1],(int)$m[2],(int)$m[3]];if($y<100)$y+=2000;}
  else return null;
  return checkdate($mo,$d,$y)?sprintf('%04d-%02d-%02d',$y,$mo,$d):null;
 }
 public static function siNo(mixed $v):?bool{
  if(is_bool($v))return $v;$n=self::norm((string)$v);
  if(in_array($n,['si','s','x','1','true','verdadero','activo','yes'],true))return true;
  if(in_array($n,['no','n','0','false','falso','inactivo'],true))return false;
  return null;
 }
 /** Valida una celda: [valor normalizado|null, mensaje de error|null]. Vacío = null sin error. */
 public static function celda(array $c,mixed $v,?array $codigos=null):array{
  if(is_string($v)){$v=trim($v);if(preg_match('/^\'[=+\-@]/',$v))$v=substr($v,1);} // la descarga antepone ' a lo que parecía fórmula
  if($v===null||$v==='')return [null,$c['requerido']?'Es obligatorio.':null];
  if(is_array($v)||is_object($v))return [null,'Valor inválido.'];
  switch($c['tipo']){
   case 'numero':$n=self::numero($v);if($n===null)return [null,'Debe ser un número.'];if(abs($n)>1e12)return [null,'Número fuera de rango.'];return [round($n,4),null];
   case 'fecha':$f=self::fecha($v);return $f?[$f,null]:[null,'Fecha inválida. Usa 2026-11-13 o 13/11/2026.'];
   case 'correo':$s=mb_strtolower((string)$v);return filter_var($s,FILTER_VALIDATE_EMAIL)&&strlen($s)<=254?[$s,null]:[null,'Correo inválido.'];
   case 'telefono':$s=(string)$v;return preg_match('/^\+?[0-9 ().\-]{4,40}$/D',$s)&&preg_match_all('/\d/',$s)>=4?[$s,null]:[null,'Teléfono inválido.'];
   case 'si_no':$b=self::siNo($v);return $b===null?[null,'Escribe Sí o No.']:[$b,null];
   case 'lista':
    $n=self::norm((string)$v);$opc=$c['opciones']??[];
    foreach($opc as $i=>$o)if(self::norm((string)$o)===$n||($codigos&&self::norm((string)($codigos[$i]??''))===$n))return [$codigos[$i]??$o,null];
    return [null,'Elige una de estas opciones: '.implode(', ',array_slice($opc,0,8)).'.'];
   default:$s=(string)$v;if(mb_strlen($s)>2000)return [null,'Texto demasiado largo (máximo 2.000 caracteres).'];return [$s,null];
  }
 }

 /* ---------- Importación ---------- */
 private function importar(array $b):array{
  /* 2.1.19: «Agregar manual» crea un solo registro: basta el permiso de edición (lo revisa Api); la carga masiva sigue siendo de administración. */
  $manual=($b['manual']??false)===true&&($b['modo']??'')==='crear'&&is_array($b['filas']??null)&&count($b['filas'])===1&&in_array($b['modulo']??'',['contactos','oportunidades','ventas','tareas'],true);
  if(!$manual)$this->auth->manage();$t=$this->tenant();
  $modulo=Http::text($b,'modulo',30);$d=self::def($modulo);if(!$d['importa'])Http::fail('Este módulo es solo de descarga.');
  $modo=$b['modo']??'';if(!in_array($modo,['crear','actualizar','simular'],true))Http::fail('Modo de importación inválido.');
  $filas=$b['filas']??null;if(!is_array($filas)||!array_is_list($filas)||count($filas)>self::LOTE)Http::fail('Envía hasta '.self::LOTE.' filas por lote.');
  $desde=isset($b['desde'])?Http::integer($b['desde'],1,10000000):2;
  $campos=$this->campos($modulo);$cod=[];foreach($d['campos'] as $c)if($c[5])$cod[$c[0]]=$c[5];
  $res=['creados'=>0,'actualizados'=>0,'errores'=>[],'filas'=>count($filas),'simulado'=>$modo==='simular'];
  $validas=[];
  foreach($filas as $i=>$fila){
   $n=$desde+$i;$err=[];
   if(!is_array($fila)||array_is_list($fila)&&$fila!==[]){$res['errores'][]=['fila'=>$n,'campo'=>'','mensaje'=>'Fila inválida.'];continue;}
   $v=[];$cf=[];
   foreach($campos as $c){
    $k=$c['clave'];if(!array_key_exists($k,$fila)){if($c['requerido']&&$c['visible'])$err[]=['fila'=>$n,'campo'=>$c['titulo'],'mensaje'=>'Es obligatorio.'];continue;}
    [$val,$e]=self::celda($c,$fila[$k],$cod[$k]??null);
    if($e){$err[]=['fila'=>$n,'campo'=>$c['titulo'],'mensaje'=>$e];continue;}
    if($val===null)continue;
    if($c['personalizado'])$cf[]=['id'=>$k,'name'=>$c['titulo'],'value'=>$val];else $v[$k]=$val;
   }
   if(isset($v['id'])&&!preg_match(self::ID,(string)$v['id']))$err[]=['fila'=>$n,'campo'=>'ID','mensaje'=>'Usa solo letras, números, guion y guion bajo (hasta 80).'];
   if($err){array_push($res['errores'],...$err);continue;}
   $validas[]=[$n,$v,$cf];
  }
  if($modulo==='productos')$this->importarProductos($validas,$modo,$res,$campos);
  else foreach($validas as [$n,$v,$cf]){
   try{$this->importarRegistro($modulo,$d['kind'],$n,$v,$cf,$modo,$res,$campos);}
   catch(\Agora\Core\HttpError $e){$res['errores'][]=['fila'=>$n,'campo'=>'','mensaje'=>$e->getMessage()];}
  }
  if(count($res['errores'])>2000)$res['errores']=array_slice($res['errores'],0,2000);
  if($modo!=='simular'&&($res['creados']||$res['actualizados']))$this->auth->audit('migracion.importada','migracion',$modulo,['modo'=>$modo,'filas'=>count($filas),'creados'=>$res['creados'],'actualizados'=>$res['actualizados'],'errores'=>count($res['errores']),'desde'=>$desde]);
  return $res;
 }
 private function existe(string $kind,string $uid):?array{
  $r=$this->db->one('SELECT uid,owner_id,data,status,period,label FROM agora_records WHERE tenant_id=? AND kind=? AND uid=?',[$this->tenant(),$kind,$uid]);
  return $r?(json_decode($r['data'],true)?:[])+['__owner'=>(int)$r['owner_id']]:null;
 }
 /** Busca un contacto por ID o por correo. Devuelve [uid,datos] o null. */
 private function contacto(string $v):?array{
  $v=trim($v);if($v==='')return null;
  if(preg_match(self::ID,$v)&&($x=$this->existe('contact',$v)))return [$v,$x];
  if(str_contains($v,'@')){$e=mb_strtolower($v);foreach($this->db->all("SELECT uid,data FROM agora_records WHERE tenant_id=? AND kind='contact' AND LOWER(label) LIKE ? LIMIT 20",[$this->tenant(),'%'.$e.'%']) as $r){$x=json_decode($r['data'],true)?:[];if(mb_strtolower((string)($x['email']??''))===$e)return [$r['uid'],$x];}}
  return null;
 }
 private function usuario(string $correo):?array{
  $k=mb_strtolower(trim($correo));if(array_key_exists($k,$this->cacheUsuarios))return $this->cacheUsuarios[$k];
  $t=$this->tenant();
  return $this->cacheUsuarios[$k]=$this->db->one('SELECT id,name,email FROM agora_users WHERE LOWER(email)=? AND active=1 AND (local_tenant_id=? OR id IN (SELECT user_id FROM agora_assignments WHERE tenant_id=?))',[$k,$t,$t]);
 }
 private static function mezclarCf(mixed $antes,array $nuevos):array{
  $o=[];if(is_array($antes)&&array_is_list($antes))foreach($antes as $f)if(is_array($f)&&isset($f['id']))$o[(string)$f['id']]=$f;
  elseif(is_array($antes))foreach($antes as $k=>$v)$o[(string)$k]=['id'=>(string)$k,'value'=>$v];
  foreach($nuevos as $f)$o[$f['id']]=$f;
  return array_values($o);
 }
 private function importarRegistro(string $modulo,string $kind,int $n,array $v,array $cf,string $modo,array &$res,array $campos):void{
  $falla=function(string $campo,string $m)use($n,&$res){$res['errores'][]=['fila'=>$n,'campo'=>$campo,'mensaje'=>$m];};
  $titulo=[];foreach($campos as $c)$titulo[$c['clave']]=$c['titulo'];
  $uid=isset($v['id'])?(string)$v['id']:null;$old=$uid?$this->existe($kind,$uid):null;
  if(!$old&&$modulo==='contactos'&&$modo!=='crear'&&!empty($v['correo'])&&($c=$this->contacto($v['correo']))){[$uid,$old]=$c;$old+=['__owner'=>null];}
  if($old&&$modo==='crear'){$falla($titulo['id']??'ID','Ya existe un registro con este ID. Elige «Crear y actualizar» para actualizarlo.');return;}
  $owner=null;
  if(!empty($v['responsable'])){$u=$this->usuario((string)$v['responsable']);if(!$u){$falla($titulo['responsable']??'Responsable','No hay un usuario activo con ese correo en esta subcuenta.');return;}$owner=(int)$u['id'];}
  $data=$old??[];unset($data['__owner'],$data['version']);
  if($modulo==='contactos'){
   $map=['nombre'=>'name','correo'=>'email','telefono'=>'phone','empresa'=>'companyName','direccion'=>'address1','ciudad'=>'city','estado'=>'state','pais'=>'country','sitio'=>'website','fuente'=>'source'];
   foreach($map as $k=>$r)if(isset($v[$k]))$data[$r]=mb_substr((string)$v[$k],0,254);
   if(isset($v['etiquetas']))$data['tags']=array_values(array_unique(array_filter(array_map(fn($x)=>mb_substr(trim($x),0,80),explode(',',(string)$v['etiquetas'])),'strlen')));
   $data['tags']??=[];if($owner)$data['assignedTo']=$owner;
   if(empty($data['name'])){$falla($titulo['nombre']??'Nombre','Es obligatorio.');return;}
   $label=trim(($data['name']??'').' '.($data['email']??'').' '.($data['phone']??''));$status=(string)($data['status']??'');$period='';
  }elseif($modulo==='oportunidades'){
   if(isset($v['contacto'])){$c=$this->contacto((string)$v['contacto']);if(!$c){$falla($titulo['contacto']??'Contacto','No existe un contacto con ese correo o ID. Carga primero los contactos.');return;}$data['contactId']=$c[0];$data['contactName']=$c[1]['name']??'';}
   if(empty($data['contactId'])){$falla($titulo['contacto']??'Contacto','Es obligatorio.');return;}
   $pipes=array_map(fn($r)=>['id'=>$r['uid']]+(json_decode($r['data'],true)?:[]),$this->db->all("SELECT uid,data FROM agora_records WHERE tenant_id=? AND kind='pipeline' ORDER BY seq LIMIT 200",[$this->tenant()]));
   if(!$pipes){$falla($titulo['embudo']??'Embudo','La subcuenta no tiene embudos. Crea uno antes de cargar oportunidades.');return;}
   $pipe=null;if(isset($v['embudo'])){foreach($pipes as $p)if($p['id']===$v['embudo']||self::norm((string)($p['name']??''))===self::norm((string)$v['embudo']))$pipe=$p;if(!$pipe){$falla($titulo['embudo']??'Embudo','No existe un embudo con ese nombre.');return;}}
   else{foreach($pipes as $p)if($p['id']===($data['pipelineId']??''))$pipe=$p;$pipe??=$pipes[0];}
   $stages=$pipe['stages']??[];$stage=null;
   if(isset($v['etapa'])){foreach($stages as $s)if(($s['id']??'')===$v['etapa']||self::norm((string)($s['name']??''))===self::norm((string)$v['etapa']))$stage=$s['id'];if(!$stage){$falla($titulo['etapa']??'Etapa','Esa etapa no existe en el embudo «'.($pipe['name']??'').'».');return;}}
   else{$ids=array_column($stages,'id');$stage=in_array($data['pipelineStageId']??'',$ids,true)&&($data['pipelineId']??'')===$pipe['id']?$data['pipelineStageId']:($ids[0]??'');}
   $data['pipelineId']=$pipe['id'];$data['pipelineStageId']=$stage;
   if(isset($v['nombre']))$data['name']=mb_substr((string)$v['nombre'],0,190);
   if(isset($v['valor'])){if($v['valor']<0||$v['valor']>100000000){$falla($titulo['valor']??'Valor','Debe estar entre 0 y 100.000.000.');return;}$data['monetaryValue']=round((float)$v['valor'],2);}
   $data['monetaryValue']??=0;
   $prev=$data['status']??null;if(isset($v['estado']))$data['status']=$v['estado'];$data['status']??='open';
   if($data['status']==='won'&&$prev!=='won')$data['wonAt']=isset($v['fecha'])?$v['fecha'].'T12:00:00Z':gmdate('c');
   if(isset($v['fecha']))$data['dateAdded']=$v['fecha'].'T12:00:00Z';$data['dateAdded']??=gmdate('c');
   if($owner)$data['assignedTo']=$owner;
   if(empty($data['name'])){$falla($titulo['nombre']??'Nombre','Es obligatorio.');return;}
   $label=$data['name'];$status=$data['status'];$period=substr((string)($data['wonAt']??$data['dateAdded']),0,7);
  }elseif($modulo==='tareas'){
   if(isset($v['hora'])&&!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/D',(string)$v['hora'])){$falla($titulo['hora']??'Hora','Usa el formato 24 h, por ejemplo 14:30.');return;}
   foreach(['titulo'=>250,'detalle'=>2000] as $k=>$max)if(isset($v[$k]))$data[$k]=mb_substr((string)$v[$k],0,$max);
   if(isset($v['vence']))$data['vence']=$v['vence'];if(isset($v['hora']))$data['hora']=strlen((string)$v['hora'])===4?'0'.$v['hora']:(string)$v['hora'];
   if(isset($v['estado']))$data['estado']=$v['estado'];$data['estado']??='pendiente';
   if(isset($v['contacto'])){$c=$this->contacto((string)$v['contacto']);if(!$c){$falla($titulo['contacto']??'Contacto','No existe un contacto con ese correo o ID.');return;}$data['contactId']=$c[0];$data['contacto']=$c[1]['name']??null;}
   if($owner){$u=$this->usuario((string)$v['responsable']);$data['responsableId']=$owner;$data['responsable']=$u['name'];}
   if(!$old){$data+=['detalle'=>'','vence'=>null,'hora'=>'','responsable'=>$this->auth->user['name']??'','responsableId'=>(int)$this->auth->user['id'],'origen'=>'migracion','contactId'=>null,'conversationId'=>null,'contacto'=>null];$owner??=(int)$this->auth->user['id'];}
   if(empty($data['titulo'])){$falla($titulo['titulo']??'Tarea','Es obligatorio.');return;}
   $label=$data['titulo'];$status=$data['estado'];$period=substr((string)($data['vence']??''),0,7);
  }else{ // ventas
   foreach($v as $k=>$x)if($k!=='id')$data[$k]=is_string($x)?mb_substr($x,0,500):$x;
   if(isset($v['monto'])&&($v['monto']<0||$v['monto']>1000000000)){$falla($titulo['monto']??'Monto','Debe estar entre 0 y 1.000.000.000.');return;}
   if(isset($v['cuotas'])&&($v['cuotas']<0||$v['cuotas']>600||floor((float)$v['cuotas'])!=(float)$v['cuotas'])){$falla($titulo['cuotas']??'Cuotas','Debe ser un número entero entre 0 y 600.');return;}
   $data['moneda']??='USD';if(!$old)$data['origen']='migracion';
   if(is_array($old['cliente']??null)&&is_string($data['cliente']??null))$data['cliente']=['nombre'=>$data['cliente']]+$old['cliente'];
   elseif(is_string($data['cliente']??null)&&$data['cliente']!=='')$data['cliente']=['nombre'=>$data['cliente'],'documento'=>(string)($data['documento']??''),'correo'=>(string)($data['correo']??''),'telefono'=>(string)($data['telefono']??''),'direccion'=>'','empresa'=>''];
   foreach(['fecha','cliente','monto'] as $k)if(!isset($data[$k])||$data[$k]===''){$falla($titulo[$k]??$k,'Es obligatorio.');return;}
   $label=trim(($data['cliente']['nombre']??'').' '.($data['documento']??'').' '.($data['correo']??''));$status=(string)($data['cobro']??'');$period=substr((string)$data['fecha'],0,7);
  }
  if($cf)$data['customFields']=self::mezclarCf($data['customFields']??null,$cf);
  $uid??=bin2hex(random_bytes(12));$data['id']=$uid;
  if($modo!=='simular')$this->records->save($kind,$uid,$data,$label,$status,$period,$owner);
  $old?$res['actualizados']++:$res['creados']++;
 }
 /** Productos: se validan como en Productos y servicios y se guardan con Personalizar::productos (misma validación y auditoría). */
 private function importarProductos(array $validas,string $modo,array &$res,array $campos):void{
  $t=$this->tenant();$cat=$this->db->setting($t,'catalogo.productos',['rubros'=>[],'productos'=>[]]);
  $rubros=$cat['rubros']??[];$prods=$cat['productos']??[];
  $titulo=[];foreach($campos as $c)$titulo[$c['clave']]=$c['titulo'];
  $porId=[];$porCod=[];$porNom=[];foreach($prods as $i=>$p){$porId[$p['id']]=$i;if(!empty($p['codigo']))$porCod[strtoupper($p['codigo'])]=$i;$porNom[self::norm($p['nombre'])]=$i;}
  $cambio=false;
  foreach($validas as [$n,$v,$cf]){
   $falla=function(string $k,string $m)use($n,&$res,$titulo){$res['errores'][]=['fila'=>$n,'campo'=>$titulo[$k]??$k,'mensaje'=>$m];};
   $i=null;if(isset($v['id'])&&isset($porId[$v['id']]))$i=$porId[$v['id']];elseif($modo!=='crear'&&!empty($v['codigo'])&&isset($porCod[strtoupper((string)$v['codigo'])]))$i=$porCod[strtoupper((string)$v['codigo'])];elseif($modo!=='crear'&&isset($v['nombre'])&&isset($porNom[self::norm((string)$v['nombre'])]))$i=$porNom[self::norm((string)$v['nombre'])];
   if($i!==null&&$modo==='crear'){$falla('id','Ya existe un producto con este ID. Elige «Crear y actualizar».');continue;}
   $p=$i!==null?$prods[$i]:['id'=>(string)($v['id']??('p-'.bin2hex(random_bytes(4)))),'rubroId'=>'','tipo'=>'servicio','moneda'=>'USD','activo'=>true,'descripcion'=>'','codigo'=>''];
   if(isset($v['nombre'])){if(mb_strlen((string)$v['nombre'])>120){$falla('nombre','Máximo 120 caracteres.');continue;}$p['nombre']=(string)$v['nombre'];}
   if(isset($v['codigo'])){$cod=strtoupper((string)$v['codigo']);if(!preg_match('/^[A-Z0-9._-]{1,30}$/D',$cod)){$falla('codigo','Solo letras, números, punto, guion y guion bajo (hasta 30).');continue;}if(isset($porCod[$cod])&&$porCod[$cod]!==$i){$falla('codigo',"El código {$cod} ya lo usa otro producto.");continue;}$p['codigo']=$cod;}
   foreach(['tipo'=>'tipo','cobro'=>'cobro','moneda'=>'moneda','frecuencia'=>'frecuencia'] as $k=>$r)if(isset($v[$k]))$p[$r]=$v[$k];
   if(isset($v['precio'])){if($v['precio']<0||$v['precio']>100000000){$falla('precio','Debe estar entre 0 y 100.000.000.');continue;}$p['precio']=round((float)$v['precio'],2);}
   if(isset($v['descripcion'])){if(mb_strlen((string)$v['descripcion'])>500){$falla('descripcion','Máximo 500 caracteres.');continue;}$p['descripcion']=(string)$v['descripcion'];}
   if(isset($v['activo']))$p['activo']=$v['activo'];
   if(empty($p['nombre'])){$falla('nombre','Es obligatorio.');continue;}
   if(!in_array($p['cobro']??'',Personalizar::COBROS,true)){$falla('cobro','Es obligatorio.');continue;}
   if(!isset($p['precio'])){$falla('precio','Es obligatorio.');continue;}
   $p['frecuencia']=$p['cobro']==='recurrente'?($p['frecuencia']??null?:'mensual'):null;
   if(isset($v['duracion'])){$x=$v['duracion'];if($x<1||$x>600||floor($x)!=$x){$falla('duracion','Debe ser un número entero entre 1 y 600.');continue;}$p['duracion']=(int)$x;}
   $p['duracion']=$p['cobro']==='recurrente'?($p['duracion']??null):null;
   if(isset($v['existencia'])){$x=$v['existencia'];if($x<0||$x>10000000||floor($x)!=$x){$falla('existencia','Debe ser un número entero entre 0 y 10.000.000.');continue;}$p['stock']=(int)$x;}
   $p['stock']=$p['cobro']==='venta'?($p['stock']??null):null;
   if(isset($v['rubro'])){$rid=null;foreach($rubros as $r)if(self::norm($r['nombre'])===self::norm((string)$v['rubro']))$rid=$r['id'];
    if(!$rid){if(count($rubros)>=100){$falla('rubro','Llegaste al máximo de 100 rubros.');continue;}$rid='r-'.bin2hex(random_bytes(4));$rubros[]=['id'=>$rid,'nombre'=>mb_substr((string)$v['rubro'],0,80),'descripcion'=>''];}
    $p['rubroId']=$rid;}
   if($cf)$p['customFields']=self::mezclarCf($p['customFields']??null,$cf);
   if($i===null){if(count($prods)>=1000){$falla('nombre','Llegaste al máximo de 1.000 productos.');continue;}$prods[]=$p;$i=count($prods)-1;$porId[$p['id']]=$i;$res['creados']++;}
   else{$prods[$i]=$p;$res['actualizados']++;}
   if(!empty($p['codigo']))$porCod[$p['codigo']]=$i;$porNom[self::norm($p['nombre'])]=$i;$cambio=true;
  }
  if($modo==='simular'||!$cambio)return;
  (new Personalizar($this->db,$this->auth))->productos('PUT',['rubros'=>$rubros,'productos'=>$prods]);
  /* Personalizar no conoce los campos propios: se agregan después de su validación. */
  $cfs=[];foreach($prods as $p)if(!empty($p['customFields']))$cfs[$p['id']]=$p['customFields'];
  if($cfs){$g=$this->db->setting($t,'catalogo.productos',['rubros'=>[],'productos'=>[]]);foreach($g['productos'] as &$p)if(isset($cfs[$p['id']]))$p['customFields']=$cfs[$p['id']];unset($p);$this->db->saveSetting($t,'catalogo.productos',$g);}
 }
}
