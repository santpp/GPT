<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;use Agora\Services\Records;
/*
 * 2.1.17 · Venta ganada que alimenta el ecosistema (workspace/ventas*).
 *
 * Al ganar una oportunidad, el formulario "Venta ganada" envía POST workspace/ventas y aquí,
 * en UNA transacción y siempre dentro de $auth->tenant:
 *   1. se crea el registro kind 'venta' (con su plan de cobros calculado),
 *   2. la oportunidad queda won, con monetaryValue = monto, wonAt y ventaId,
 *   3. el contacto recibe los datos del cliente, la etiqueta 'cliente' y los campos personalizados,
 *   4. se crean las tareas de seguimiento (bienvenida y cobros próximos),
 *   5. se descuenta la existencia del producto (cobro 'venta' con stock) en catalogo.productos,
 *   6. se registra la auditoría venta.ganada.
 * Si algo falla, no queda nada a medias (rollback).
 *
 * Rutas:
 *   GET  workspace/ventas            lista (?mes=YYYY-MM &estado= &contactoId= &oportunidadId=) + resumen + pendientes
 *   POST workspace/ventas            crear desde una oportunidad
 *   PUT  workspace/ventas            editar una venta (recalcula el plan y conserva los cobros pagados)
 *   GET  workspace/ventas/contexto   todo lo que el formulario necesita (?oportunidadId= o ?ventaId=)
 *   POST workspace/ventas/cobro      marcar un cobro pagado o pendiente {ventaId, n, pagado}
 *   POST workspace/ventas/pendiente  la persona cerró el formulario: la oportunidad queda "venta por completar"
 */
final class Ventas {
 private const COBROS=['recurrente','unico','venta'];
 private const FRECUENCIAS=['semanal','quincenal','mensual','trimestral','semestral','anual'];
 private const MONEDAS=['USD','VES','EUR'];
 private const ESTADOS=['al_dia','vencida','pagada'];
 private const ID='/^[A-Za-z0-9_-]{1,80}$/D';
 /** Cobros que se generan por adelantado cuando el plan recurrente no tiene fin. */
 private const SIN_FIN=12;
 /** Se crea una tarea por cada cobro que cae en los próximos N días (siempre al menos el primero). */
 private const DIAS_TAREA=31;

 public function __construct(public DB $db,public Auth $auth,public Records $records){}

 public function route(string $p,string $m,array $b):array{
  if($p==='workspace/ventas'){
   if($m==='GET')return $this->listar();
   if($m==='POST')return $this->crear($b);
   if($m==='PUT')return $this->editar($b);
  }
  if($p==='workspace/ventas/contexto'&&$m==='GET')return $this->contexto();
  if($p==='workspace/ventas/cobro'&&$m==='POST')return $this->cobro($b);
  if($p==='workspace/ventas/pendiente'&&$m==='POST')return $this->pendiente($b);
  Http::fail('Ruta no disponible.',404);
 }

 /* ---------- Lectura ---------- */
 private function todas():array{
  $args=[$this->auth->tenant];$sql="SELECT uid,data,version FROM agora_records WHERE tenant_id=? AND kind='venta'";
  if($this->auth->assignedOnly()){$sql.=' AND owner_id=?';$args[]=$this->auth->user['id'];}
  $out=[];foreach($this->db->all($sql.' ORDER BY seq DESC LIMIT 3000',$args) as $r){$d=json_decode($r['data'],true);if(!is_array($d))continue;$d['id']=$r['uid'];$d['version']=(int)$r['version'];$d['estado']=self::estadoDe($d);$out[]=$d;}
  return $out;
 }
 public function listar():array{
  $mes=isset($_GET['mes'])&&$_GET['mes']!==''?Http::period($_GET['mes']):null;
  $estado=(string)($_GET['estado']??'');if($estado!==''&&!in_array($estado,self::ESTADOS,true))Http::fail('Estado inválido.');
  $contacto=(string)($_GET['contactoId']??'');$op=(string)($_GET['oportunidadId']??'');
  $todas=$this->todas();$hoy=gmdate('Y-m-d');$limite=gmdate('Y-m-d',strtotime('+'.self::DIAS_TAREA.' days'));
  $items=array_values(array_filter($todas,fn($v)=>(!$mes||substr((string)$v['fecha'],0,7)===$mes)&&($estado===''||$v['estado']===$estado)&&($contacto===''||($v['contactoId']??'')===$contacto)&&($op===''||($v['oportunidadId']??'')===$op)));
  // Resumen del mes pedido (o del mes en curso): ventas, vendido, cobrado y por cobrar, por moneda.
  $ym=$mes??gmdate('Y-m');$res=['mes'=>$ym,'cantidad'=>0,'vendido'=>[],'cobrado'=>[],'porCobrar'=>[],'vencido'=>[]];
  $proximos=[];
  foreach($todas as $v){
   $mon=$v['moneda']??'USD';
   if(substr((string)$v['fecha'],0,7)===$ym){$res['cantidad']++;$res['vendido'][$mon]=round(($res['vendido'][$mon]??0)+(float)$v['monto'],2);}
   foreach($v['plan']??[] as $c){
    $f=(string)$c['fecha'];$pag=($c['estado']??'')==='pagado';
    if(substr($f,0,7)===$ym){$k=$pag?'cobrado':'porCobrar';$res[$k][$mon]=round(($res[$k][$mon]??0)+(float)$c['monto'],2);}
    if(!$pag&&$f<$hoy)$res['vencido'][$mon]=round(($res['vencido'][$mon]??0)+(float)$c['monto'],2);
    if(!$pag&&$f<=$limite)$proximos[]=['ventaId'=>$v['id'],'cliente'=>$v['cliente']['nombre']??'','producto'=>$v['producto']??'','n'=>(int)$c['n'],'de'=>count($v['plan']),'sinFin'=>!empty($v['sinFin']),'fecha'=>$f,'monto'=>(float)$c['monto'],'moneda'=>$mon,'vencido'=>$f<$hoy,'vendedor'=>$v['vendedor']??''];
   }
  }
  usort($proximos,fn($a,$b)=>strcmp($a['fecha'],$b['fecha']));
  // Oportunidades ganadas que quedaron sin venta registrada (la persona cerró el formulario).
  $args=[$this->auth->tenant];$sql="SELECT uid,data FROM agora_records WHERE tenant_id=? AND kind='opportunity' AND status='won'";
  if($this->auth->assignedOnly()){$sql.=' AND owner_id=?';$args[]=$this->auth->user['id'];}
  $pend=[];foreach($this->db->all($sql.' ORDER BY seq DESC LIMIT 2000',$args) as $r){$d=json_decode($r['data'],true);if(is_array($d)&&!empty($d['ventaPendiente'])&&empty($d['ventaId']))$pend[]=['id'=>$r['uid'],'name'=>$d['name']??'Oportunidad','contactId'=>$d['contactId']??'','monetaryValue'=>$d['monetaryValue']??null,'wonAt'=>$d['wonAt']??null];}
  return ['items'=>$items,'total'=>count($items),'resumen'=>$res,'proximos'=>array_slice($proximos,0,60),
   'vinculos'=>array_map(fn($v)=>['ventaId'=>$v['id'],'oportunidadId'=>$v['oportunidadId']??'','contactoId'=>$v['contactoId']??'','estado'=>$v['estado']],$todas),
   'pendientes'=>$pend,'canEdit'=>$this->puedeEscribir(),'restricted'=>$this->auth->assignedOnly()];
 }
 public function contexto():array{
  $ventaId=(string)($_GET['ventaId']??'');$venta=null;
  if($ventaId!==''){$venta=$this->records->payload($this->records->get('venta',$ventaId));$opId=(string)($venta['oportunidadId']??'');}
  else $opId=(string)($_GET['oportunidadId']??'');
  if(!preg_match(self::ID,$opId))Http::fail('Falta la oportunidad.');
  $op=$this->records->payload($this->records->get('opportunity',$opId));
  if(!$venta&&!empty($op['ventaId'])){try{$venta=$this->records->payload($this->records->get('venta',(string)$op['ventaId']));}catch(\Throwable){$venta=null;}}
  $contacto=null;try{$contacto=$this->records->payload($this->records->get('contact',(string)($op['contactId']??'')));}catch(\Throwable){$contacto=null;}
  $catalogo=$this->db->setting((int)$this->auth->tenant,'catalogo.productos',['rubros'=>[],'productos'=>[]]);
  $owner=$this->db->one("SELECT owner_id FROM agora_records WHERE tenant_id=? AND kind='opportunity' AND uid=?",[$this->auth->tenant,$opId]);
  $op['ownerId']=$owner?(int)$owner['owner_id']:null;
  return ['oportunidad'=>$op,'contacto'=>$contacto,'venta'=>$venta,'catalogo'=>['rubros'=>$catalogo['rubros']??[],'productos'=>$catalogo['productos']??[]],
   'perfil'=>$this->db->setting((int)$this->auth->tenant,'cuenta.perfil',null),'vendedores'=>$this->vendedores(),'yo'=>(int)$this->auth->user['id'],
   'canEdit'=>$this->puedeEscribir(),'puedeCambiarVendedor'=>$this->administra()];
 }

 /* ---------- Escritura ---------- */
 public function crear(array $b):array{
  $this->auth->write();
  return $this->db->transaction(function()use($b){
   $opId=Http::text($b,'oportunidadId',80);if(!preg_match(self::ID,$opId))Http::fail('Oportunidad inválida.');
   $opRow=$this->records->get('opportunity',$opId,true);$op=$this->records->payload($opRow);
   if(!empty($op['ventaId']))Http::fail('Esta oportunidad ya tiene una venta registrada. Edítala desde Ventas.',409);
   $ctId=(string)($op['contactId']??'');if(isset($b['contactoId'])&&$b['contactoId']!==''&&(string)$b['contactoId']!==$ctId)Http::fail('El contacto no corresponde a la oportunidad.');
   $ctRow=$this->records->get('contact',$ctId,true);
   $catalogo=$this->db->setting((int)$this->auth->tenant,'catalogo.productos',['rubros'=>[],'productos'=>[]]);
   $v=$this->leer($b,$catalogo,(int)$opRow['owner_id'],null);
   $id=bin2hex(random_bytes(12));$hoy=gmdate('c');
   $venta=['id'=>$id,'fecha'=>$v['fechaVenta'],'creada'=>$hoy,'oportunidadId'=>$opId,'oportunidad'=>$op['name']??'','contactoId'=>$ctId]+$v['datos'];
   $venta['plan']=$v['plan'];$venta['total']=$v['total'];$venta['sinFin']=$v['sinFin'];
   $venta['registradaPor']=$this->auth->user['name']??'';$venta['tareas']=[];
   // 5. Existencia: solo productos de venta única con control de stock.
   $this->moverStock($catalogo,$venta['productoId'],$venta['cobro']==='venta'?-$venta['cantidad']:0);
   // 4. Tareas de seguimiento.
   $venta['tareas']=$this->tareas($venta,$ctId);
   // 1. Registro venta.
   $venta['estado']=self::estadoDe($venta);
   $this->records->save('venta',$id,$venta,($venta['cliente']['nombre']??'').' · '.$venta['producto'],$venta['estado'],substr($venta['fecha'],0,7),$v['vendedorId']);
   // 2. Oportunidad.
   $this->actualizarOportunidad($opRow,$op,$venta,$v['vendedorId']);
   // 3. Contacto.
   $this->actualizarContacto($ctRow,$venta);
   // 6. Auditoría.
   $this->auth->audit('venta.ganada','ventas',$id,['oportunidad'=>$opId,'contacto'=>$ctId,'producto'=>$venta['producto'],'monto'=>$venta['monto'],'moneda'=>$venta['moneda'],'cobro'=>$venta['cobro'],'cobros'=>count($venta['plan'])]);
   return ['ok'=>true,'venta'=>$venta];
  });
 }
 public function editar(array $b):array{
  $this->auth->write();
  return $this->db->transaction(function()use($b){
   $id=Http::text($b,'id',80);if(!preg_match(self::ID,$id))Http::fail('Venta inválida.');
   $row=$this->records->get('venta',$id,true);$antes=$this->records->payload($row);unset($antes['version']);
   if(isset($b['version'])&&(int)$b['version']!==(int)$row['version'])Http::fail('La venta cambió. Recarga antes de guardar.',409);
   $catalogo=$this->db->setting((int)$this->auth->tenant,'catalogo.productos',['rubros'=>[],'productos'=>[]]);
   $v=$this->leer($b+['oportunidadId'=>$antes['oportunidadId']??''],$catalogo,(int)$row['owner_id'],$antes);
   // Existencia: se devuelve lo de antes y se descuenta lo nuevo.
   if(($antes['cobro']??'')==='venta')$catalogo=$this->moverStock($catalogo,(string)$antes['productoId'],(int)($antes['cantidad']??1),true);
   $venta=array_replace($antes,$v['datos']);$venta['fecha']=$v['fechaVenta'];
   if($venta['cobro']==='venta')$this->moverStock($catalogo,$venta['productoId'],-$venta['cantidad']);
   // Plan: se recalcula y se conservan los cobros ya pagados (por número).
   $pagados=[];foreach($antes['plan']??[] as $c)if(($c['estado']??'')==='pagado')$pagados[(int)$c['n']]=$c;
   $plan=$v['plan'];foreach($plan as &$c)if(isset($pagados[$c['n']]))$c=array_replace($c,['estado'=>'pagado','pagadoEn'=>$pagados[$c['n']]['pagadoEn']??null,'pagadoPor'=>$pagados[$c['n']]['pagadoPor']??null]);unset($c);
   $venta['plan']=$plan;$venta['total']=$v['total'];$venta['sinFin']=$v['sinFin'];
   $venta['editada']=gmdate('c');$venta['editadaPor']=$this->auth->user['name']??'';$venta['estado']=self::estadoDe($venta);
   $this->records->save('venta',$id,$venta,($venta['cliente']['nombre']??'').' · '.$venta['producto'],$venta['estado'],substr($venta['fecha'],0,7),$v['vendedorId']);
   // La oportunidad y el contacto siguen a la venta: no puede quedar un dato distinto en cada módulo.
   $opId=(string)($venta['oportunidadId']??'');
   if($opId!==''){try{$opRow=$this->records->get('opportunity',$opId,true);$this->actualizarOportunidad($opRow,$this->records->payload($opRow),$venta,$v['vendedorId']);}catch(\Agora\Core\HttpError $e){if($e->getCode()!==404)throw $e;}}
   $ct=(string)($venta['contactoId']??'');
   if($ct!==''){try{$this->actualizarContacto($this->records->get('contact',$ct,true),$venta);}catch(\Agora\Core\HttpError $e){if($e->getCode()!==404)throw $e;}}
   $cambios=[];foreach(['monto','moneda','cobro','frecuencia','cuotas','fechaInicio','diaCobro','productoId','vendedor','notas'] as $k)if(($antes[$k]??null)!==($venta[$k]??null))$cambios[$k]=['antes'=>$antes[$k]??null,'despues'=>$venta[$k]??null];
   if(($antes['cliente']??null)!==$venta['cliente'])$cambios['cliente']='actualizado';
   $this->auth->audit('venta.editada','ventas',$id,['cambios'=>$cambios]);
   return ['ok'=>true,'venta'=>$venta];
  });
 }
 public function cobro(array $b):array{
  $this->auth->write();
  return $this->db->transaction(function()use($b){
   $id=Http::text($b,'ventaId',80);$n=Http::integer($b['n']??0,1,1000);$pagado=($b['pagado']??true)!==false;
   $row=$this->records->get('venta',$id,true);$v=$this->records->payload($row);unset($v['version']);
   $ok=false;foreach($v['plan'] as &$c)if((int)$c['n']===$n){$ok=true;$c['estado']=$pagado?'pagado':'pendiente';$c['pagadoEn']=$pagado?gmdate('c'):null;$c['pagadoPor']=$pagado?($this->auth->user['name']??''):null;}unset($c);
   if(!$ok)Http::fail('Ese cobro no existe en la venta.',404);
   // Plan sin fin: siempre quedan cobros por delante.
   if(!empty($v['sinFin'])&&count(array_filter($v['plan'],fn($c)=>$c['estado']!=='pagado'))<3){
    $ult=end($v['plan']);$ext=self::plan('recurrente',(float)$v['monto'],(string)$v['frecuencia'],self::SIN_FIN+1,(string)$ult['fecha'],$v['diaCobro']??null);
    foreach(array_slice($ext,1) as $c){$c['n']=(int)$ult['n']+(int)$c['n']-1;$v['plan'][]=$c;}
   }
   $v['estado']=self::estadoDe($v);
   $this->records->save('venta',$id,$v,(string)$row['label'],$v['estado'],(string)$row['period']);
   // La tarea de ese cobro sigue al cobro.
   foreach($v['tareas']??[] as $t)if(($t['tipo']??'')==='cobro'&&(int)($t['n']??0)===$n){
    try{$tr=$this->records->get('task',(string)$t['id'],true);$td=$this->records->payload($tr);unset($td['version']);$td['estado']=$pagado?'hecha':'pendiente';$this->records->save('task',(string)$t['id'],$td,(string)$tr['label'],$td['estado'],(string)$tr['period']);}catch(\Agora\Core\HttpError $e){if($e->getCode()!==404)throw $e;}
   }
   $this->auth->audit($pagado?'venta.cobro.pagado':'venta.cobro.pendiente','ventas',$id,['n'=>$n]);
   return ['ok'=>true,'venta'=>$v];
  });
 }
 public function pendiente(array $b):array{
  $this->auth->write();
  return $this->db->transaction(function()use($b){
   $opId=Http::text($b,'oportunidadId',80);$row=$this->records->get('opportunity',$opId,true);$op=$this->records->payload($row);unset($op['version']);
   if(!empty($op['ventaId']))return ['ok'=>true,'yaRegistrada'=>true];
   if(($op['status']??'')!=='won')Http::fail('La oportunidad no está ganada.');
   if(!empty($op['ventaPendiente']))return ['ok'=>true,'pendiente'=>true];
   $op['ventaPendiente']=true;
   $this->records->save('opportunity',$opId,$op,(string)$row['label'],'won',(string)$row['period']);
   $nombre=$op['contactName']??null;if(!$nombre){try{$nombre=$this->records->payload($this->records->get('contact',(string)($op['contactId']??'')))['name']??null;}catch(\Throwable){$nombre=null;}}
   $tid=bin2hex(random_bytes(12));$owner=(int)$row['owner_id'];$hoy=gmdate('Y-m-d');
   $t=['id'=>$tid,'titulo'=>'Completar los datos de la venta'.($nombre?' de '.$nombre:''),'detalle'=>'Quedó ganada sin el formulario de venta: '.($op['name']??''),'vence'=>$hoy,'hora'=>'','estado'=>'pendiente','responsable'=>$this->nombreDe($owner),'responsableId'=>$owner,'origen'=>'venta','contactId'=>$op['contactId']??null,'conversationId'=>null,'contacto'=>$nombre,'oportunidadId'=>$opId];
   $this->records->save('task',$tid,$t,$t['titulo'],'pendiente',substr($hoy,0,7),$owner);
   $this->auth->audit('venta.pendiente','ventas',$opId,['tarea'=>$tid]);
   return ['ok'=>true,'pendiente'=>true,'tareaId'=>$tid];
  });
 }

 /* ---------- Piezas ---------- */
 private function puedeEscribir():bool{return !in_array($this->auth->user['role'],['USUARIO','CONSULTA','VISOR'],true);}
 private function administra():bool{return in_array($this->auth->user['role'],['MASTER','SUPERADMIN','ADMIN','SUPERVISOR'],true);}
 private function vendedores():array{return $this->db->all('SELECT id,name FROM agora_users WHERE local_tenant_id=? AND active=1 ORDER BY name LIMIT 200',[$this->auth->tenant]);}
 private function nombreDe(int $id):string{$u=$this->db->one('SELECT name FROM agora_users WHERE id=?',[$id]);return $u['name']??'';}
 private static function fecha(string $v,string $campo):string{if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)||!checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4)))Http::fail("Revisa la fecha: $campo.");return $v;}

 /** Valida y normaliza el formulario. $antes = venta existente (edición). */
 private function leer(array $b,array $catalogo,int $ownerActual,?array $antes):array{
  $c=is_array($b['cliente']??null)?$b['cliente']:Http::fail('Faltan los datos del cliente.');
  $cliente=['nombre'=>Http::text($c,'nombre',190),'documento'=>Http::text($c,'documento',40,false),'correo'=>strtolower(Http::text($c,'correo',254,false)),'telefono'=>Http::text($c,'telefono',40,false),'direccion'=>Http::text($c,'direccion',254,false),'empresa'=>Http::text($c,'empresa',190,false)];
  if($cliente['documento']!==''&&!preg_match('/^[A-Za-z0-9 .\/-]{3,40}$/D',$cliente['documento']))Http::fail('Revisa el documento del cliente.');
  if($cliente['correo']!==''&&!filter_var($cliente['correo'],FILTER_VALIDATE_EMAIL))Http::fail('Revisa el correo del cliente.');
  if($cliente['telefono']!==''&&!preg_match('/^[0-9+() .-]{7,40}$/D',$cliente['telefono']))Http::fail('Revisa el teléfono del cliente.');
  // Producto: tiene que existir en el catálogo de ESTA subcuenta.
  $pid=Http::text($b,'productoId',40);$prod=null;foreach($catalogo['productos']??[] as $p)if(($p['id']??'')===$pid)$prod=$p;
  if(!$prod)Http::fail('Ese producto no está en el catálogo de esta subcuenta.');
  if(($prod['activo']??true)===false&&($antes['productoId']??'')!==$pid)Http::fail('Ese producto está pausado. Actívalo en Productos y servicios.');
  $rubro='';foreach($catalogo['rubros']??[] as $r)if(($r['id']??'')===($prod['rubroId']??''))$rubro=(string)$r['nombre'];
  $cobro=(string)($b['cobro']??$prod['cobro']);if(!in_array($cobro,self::COBROS,true))Http::fail('Elige el sistema de cobro.');
  $monto=$b['monto']??null;if(!is_numeric($monto)||$monto<=0||$monto>100000000)Http::fail('El monto debe ser mayor que cero.');$monto=round((float)$monto,2);
  $moneda=(string)($b['moneda']??$prod['moneda']??'USD');if(!in_array($moneda,self::MONEDAS,true))Http::fail('Moneda inválida.');
  $cantidad=1;if($cobro==='venta'){$cantidad=Http::integer($b['cantidad']??1,1,100000);}
  $cuotas=null;$frecuencia=null;
  if($cobro==='recurrente'){
   $q=$b['cuotas']??null;$cuotas=($q===null||$q==='')?null:Http::integer($q,1,600);
   $frecuencia=(string)($b['frecuencia']??$prod['frecuencia']??'mensual');
  }elseif($cobro==='unico'){
   $cuotas=Http::integer($b['cuotas']??1,1,120);
   if($cuotas>1)$frecuencia=(string)($b['frecuencia']??'mensual');
  }else $cuotas=1;
  if($frecuencia!==null&&!in_array($frecuencia,self::FRECUENCIAS,true))Http::fail('Frecuencia inválida.');
  $inicio=self::fecha(Http::text($b,'fechaInicio',10),'fecha de inicio');
  $min=gmdate('Y-m-d',strtotime('-400 days'));$max=gmdate('Y-m-d',strtotime('+800 days'));if($inicio<$min||$inicio>$max)Http::fail('La fecha de inicio está muy lejos de hoy.');
  $fechaVenta=isset($b['fecha'])&&$b['fecha']!==''?self::fecha(Http::text($b,'fecha',10),'fecha de la venta'):($antes['fecha']??gmdate('Y-m-d'));
  if($fechaVenta>gmdate('Y-m-d',strtotime('+1 day')))Http::fail('La fecha de la venta no puede ser futura.');
  $dia=$b['diaCobro']??null;$diaCobro=($dia===null||$dia==='')?null:Http::integer($dia,1,28);
  // Vendedor: por defecto el dueño de la oportunidad; cambiarlo requiere supervisar.
  $vid=isset($b['vendedorId'])&&$b['vendedorId']!==''&&$b['vendedorId']!==null?Http::integer($b['vendedorId'],1,PHP_INT_MAX):$ownerActual;
  if($vid!==$ownerActual&&!$this->administra())Http::fail('Solo supervisión o administración puede cambiar el vendedor.',403);
  if(!$this->db->one('SELECT id FROM agora_users WHERE id=? AND local_tenant_id=?',[$vid,$this->auth->tenant]))Http::fail('El vendedor no pertenece a esta subcuenta.');
  // Campos personalizados (los define Ajustes → Campos para 'ventas').
  $cf=$b['customFields']??[];if(!is_array($cf)||!array_is_list($cf)||count($cf)>40)Http::fail('Hasta 40 campos personalizados.');
  $custom=[];foreach($cf as $x){if(!is_array($x))Http::fail('Campo personalizado inválido.');$k=Http::text($x,'clave',60);if(!preg_match('/^[A-Za-z0-9_.-]{1,60}$/D',$k))Http::fail('Clave de campo inválida.');$val=$x['valor']??'';if(is_bool($val))$val=$val?'Sí':'No';if(!is_scalar($val)&&$val!==null)Http::fail('Valor inválido en '.$k.'.');$custom[$k]=['clave'=>$k,'nombre'=>Http::text($x,'nombre',80,false)?:$k,'valor'=>mb_substr(trim((string)$val),0,1000)];}
  $custom=array_values($custom);
  $plan=self::plan($cobro,$monto,(string)$frecuencia,$cobro==='recurrente'?($cuotas??self::SIN_FIN):$cuotas,$inicio,$diaCobro);
  $total=round(array_sum(array_column($plan,'monto')),2);
  return ['fechaVenta'=>$fechaVenta,'vendedorId'=>$vid,'plan'=>$plan,'total'=>$total,'sinFin'=>$cobro==='recurrente'&&$cuotas===null,
   'datos'=>['cliente'=>$cliente,'documento'=>$cliente['documento'],'correo'=>$cliente['correo'],'telefono'=>$cliente['telefono'],
    'productoId'=>$pid,'producto'=>(string)$prod['nombre'],'codigo'=>(string)($prod['codigo']??''),'rubro'=>$rubro,'cantidad'=>$cantidad,
    'monto'=>$monto,'moneda'=>$moneda,'cobro'=>$cobro,'frecuencia'=>$frecuencia,'cuotas'=>$cuotas,'fechaInicio'=>$inicio,'diaCobro'=>$diaCobro,
    'vendedorId'=>$vid,'vendedor'=>$this->nombreDe($vid),'notas'=>Http::text($b,'notas',2000,false),'customFields'=>$custom]];
 }

 /**
  * Plan de cobros. recurrente: un cobro de $monto por periodo ($cuotas cobros).
  * unico en cuotas: $monto se reparte (la última cuota absorbe el redondeo). venta / unico: un cobro.
  * El primer cobro cae en la fecha de inicio; los siguientes, en el día de cobro (si hay) de cada periodo.
  */
 public static function plan(string $cobro,float $monto,string $frecuencia,?int $cuotas,string $inicio,?int $dia):array{
  $n=$cobro==='venta'?1:max(1,(int)$cuotas);
  $montos=array_fill(0,$n,round($monto,2));
  if($cobro==='unico'&&$n>1){$base=floor($monto/$n*100)/100;$montos=array_fill(0,$n,$base);$montos[$n-1]=round($monto-$base*($n-1),2);}
  $meses=['mensual'=>1,'trimestral'=>3,'semestral'=>6,'anual'=>12][$frecuencia]??null;$dias=['semanal'=>7,'quincenal'=>15][$frecuencia]??null;
  if(!$meses&&!$dias)$meses=1;
  [$y,$m,$d]=array_map('intval',explode('-',$inicio));$out=[];
  for($i=0;$i<$n;$i++){
   if($i===0)$f=$inicio;
   elseif($dias)$f=gmdate('Y-m-d',strtotime($inicio.' +'.($dias*$i).' days'));
   else{$t=$m-1+$meses*$i;$yy=$y+intdiv($t,12);$mm=$t%12+1;$dd=min($dia??$d,(int)gmdate('t',gmmktime(0,0,0,$mm,1,$yy)));$f=sprintf('%04d-%02d-%02d',$yy,$mm,$dd);}
   $out[]=['n'=>$i+1,'fecha'=>$f,'monto'=>$montos[$i],'estado'=>'pendiente'];
  }
  return $out;
 }
 public static function estadoDe(array $v):string{
  $plan=$v['plan']??[];if(!$plan)return 'al_dia';$hoy=gmdate('Y-m-d');$pend=array_filter($plan,fn($c)=>($c['estado']??'')!=='pagado');
  if(!$pend&&empty($v['sinFin']))return 'pagada';
  foreach($pend as $c)if((string)$c['fecha']<$hoy)return 'vencida';
  return 'al_dia';
 }
 /** Suma $delta a la existencia del producto (negativo = descuenta). Sin control de stock no hace nada. */
 private function moverStock(array $catalogo,string $pid,int $delta,bool $soloDevolver=false):array{
  if($delta===0)return $catalogo;
  foreach($catalogo['productos'] as $i=>$p){
   if(($p['id']??'')!==$pid||($p['cobro']??'')!=='venta'||!array_key_exists('stock',$p)||$p['stock']===null)continue;
   $nuevo=(int)$p['stock']+$delta;
   if($nuevo<0&&!$soloDevolver)Http::fail('No hay existencia suficiente de «'.$p['nombre'].'» (quedan '.(int)$p['stock'].').');
   $catalogo['productos'][$i]['stock']=max(0,$nuevo);$catalogo['actualizado']=gmdate('c');
   $this->db->saveSetting((int)$this->auth->tenant,'catalogo.productos',$catalogo);
   $this->auth->audit('productos.existencia','productos',$pid,['antes'=>(int)$p['stock'],'despues'=>max(0,$nuevo),'motivo'=>'venta']);
  }
  return $catalogo;
 }
 /** Bienvenida + cobros que caen en los próximos días (siempre al menos el primero). */
 private function tareas(array $venta,string $ctId):array{
  $owner=(int)$venta['vendedorId'];$resp=$venta['vendedor'];$cliente=$venta['cliente']['nombre']??'';$hoy=gmdate('Y-m-d');$limite=gmdate('Y-m-d',strtotime('+'.self::DIAS_TAREA.' days'));
  $nueva=function(string $titulo,string $detalle,string $vence,string $tipo,?int $n)use($owner,$resp,$cliente,$ctId,$venta):array{
   $id=bin2hex(random_bytes(12));
   $t=['id'=>$id,'titulo'=>mb_substr($titulo,0,250),'detalle'=>mb_substr($detalle,0,2000),'vence'=>$vence,'hora'=>'','estado'=>'pendiente','responsable'=>$resp,'responsableId'=>$owner,'origen'=>'venta','contactId'=>$ctId,'conversationId'=>null,'contacto'=>$cliente,'ventaId'=>$venta['id'],'oportunidadId'=>$venta['oportunidadId']];
   $this->records->save('task',$id,$t,$t['titulo'],'pendiente',substr($vence,0,7),$owner);
   return ['id'=>$id,'tipo'=>$tipo,'n'=>$n];
  };
  $out=[$nueva('Dar la bienvenida a '.$cliente,'Venta de '.$venta['producto'].'. Confirma datos, accesos y fecha de inicio.',$hoy,'bienvenida',null)];
  $total=count($venta['plan']);
  foreach($venta['plan'] as $i=>$c){
   if($i>0&&$c['fecha']>$limite)break;
   $cuota=$total>1?' (cobro '.$c['n'].($venta['sinFin']?'':' de '.$total).')':'';
   $out[]=$nueva('Cobrar '.$venta['producto'].' a '.$cliente.$cuota,number_format((float)$c['monto'],2,',','.').' '.$venta['moneda'],max($hoy,(string)$c['fecha']),'cobro',(int)$c['n']);
  }
  return $out;
 }
 private function actualizarOportunidad(array $row,array $op,array $venta,int $vendedor):void{
  unset($op['version']);
  $op['status']='won';$op['monetaryValue']=$venta['monto'];$op['wonAt']=$op['wonAt']??gmdate('c');$op['ventaId']=$venta['id'];$op['ventaPendiente']=false;
  if($vendedor!==(int)$row['owner_id'])$op['assignedTo']=$vendedor;
  $this->records->save('opportunity',(string)$row['uid'],$op,(string)$row['label'],'won',substr((string)$op['wonAt'],0,7),$vendedor);
 }
 private function actualizarContacto(array $row,array $venta):void{
  $d=$this->records->payload($row);unset($d['version']);$c=$venta['cliente'];
  if($c['nombre']!=='')$d['name']=$c['nombre'];
  foreach(['email'=>'correo','phone'=>'telefono','companyName'=>'empresa','address1'=>'direccion','documento'=>'documento'] as $k=>$src)if(($c[$src]??'')!=='')$d[$k]=$c[$src];
  $tags=array_values(array_filter(is_array($d['tags']??null)?$d['tags']:[],'is_string'));if(!in_array('cliente',array_map('mb_strtolower',$tags),true))$tags[]='cliente';$d['tags']=array_slice($tags,0,50);
  // Campos personalizados de la venta en la ficha (id 'venta.<clave>', se reemplazan si ya estaban).
  $cf=is_array($d['customFields']??null)&&array_is_list($d['customFields'])?$d['customFields']:[];
  $idx=[];foreach($cf as $i=>$x)if(is_array($x)&&isset($x['id']))$idx[(string)$x['id']]=$i;
  $extra=array_merge([['clave'=>'producto','nombre'=>'Producto contratado','valor'=>$venta['producto']]],$c['documento']!==''?[['clave'=>'documento','nombre'=>'Cédula o RIF','valor'=>$c['documento']]]:[],$venta['customFields']);
  foreach($extra as $x){$fid='venta.'.$x['clave'];$item=['id'=>$fid,'name'=>$x['nombre'],'value'=>$x['valor'],'folder'=>'Venta'];if(isset($idx[$fid]))$cf[$idx[$fid]]=$item;else $cf[]=$item;}
  if(strlen(DB::json($cf))>20000)Http::fail('La ficha del contacto tiene demasiados campos.');
  $d['customFields']=$cf;$d['clienteDesde']=$d['clienteDesde']??$venta['fecha'];
  $this->records->save('contact',(string)$row['uid'],$d,trim(($d['name']??'').' '.($d['email']??'').' '.($d['phone']??'')),(string)$row['status'],(string)$row['period']);
 }
}
