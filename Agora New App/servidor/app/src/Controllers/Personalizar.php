<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;
/*
 * 2.1.16 · Personalización por subcuenta. Sin migraciones: todo vive en agora_settings.
 *  - cuenta.perfil       Tipo de cuenta (lo define la agencia al crear o editar la subcuenta).
 *  - catalogo.productos  Rubros y productos manuales con su sistema de cobro.
 *  - ui.personalizar     Tablero del Inicio, secciones y tarjetas del ARP y módulos visibles.
 *  - tags                Etiquetas del CRM (las lee la ficha de contactos).
 */
final class Personalizar {
 public const TIPOS_CLIENTE=['servicios','producto','mixto','interna'];
 public const NEGOCIOS=['servicios','productos','mixto','suscripciones'];
 public const COBROS_CUENTA=['recurrente','unico','venta','mixto'];
 public const COBROS=['recurrente','unico','venta'];
 public const FRECUENCIAS=['semanal','quincenal','mensual','trimestral','semestral','anual'];
 public const MONEDAS=['USD','VES','EUR'];
 private const ID='/^[A-Za-z0-9_-]{1,40}$/D';
 private const CLAVE='/^[A-Za-z0-9:_-]{1,80}$/D';
 public function __construct(public DB $db,public Auth $auth){}

 /** Normaliza el tipo de cuenta. null = sin definir. */
 public static function perfil(mixed $p):?array{
  if($p===null||$p==='')return null;
  if(!is_array($p))Http::fail('Tipo de cuenta inválido.');
  $uno=fn(string $k,array $ok,string $def)=>in_array($p[$k]??$def,$ok,true)?($p[$k]??$def):Http::fail('Revisa el tipo de cuenta: '.$k.'.');
  $out=['tipoCliente'=>$uno('tipoCliente',self::TIPOS_CLIENTE,'servicios'),'negocio'=>$uno('negocio',self::NEGOCIOS,'servicios'),'cobro'=>$uno('cobro',self::COBROS_CUENTA,'recurrente')];
  $out['frecuencia']=in_array($out['cobro'],['recurrente','mixto'],true)?$uno('frecuencia',self::FRECUENCIAS,'mensual'):null;
  $dia=$p['diaCobro']??null;$out['diaCobro']=($dia===null||$dia==='')?null:Http::integer($dia,1,28);
  $out['factura']=($p['factura']??false)===true;
  $out['notas']=Http::text($p,'notas',300,false);
  return $out;
 }
 public function perfilDe(int $tenant):?array{$p=$this->db->setting($tenant,'cuenta.perfil',null);return is_array($p)?$p:null;}

 /* ---------- Productos y rubros ---------- */
 public function productos(string $m,array $b):array{
  $t=(int)$this->auth->tenant;
  if($m==='GET')return $this->db->setting($t,'catalogo.productos',['rubros'=>[],'productos'=>[]])+['canEdit'=>in_array($this->auth->user['role'],['MASTER','SUPERADMIN','ADMIN'],true)];
  $this->auth->manage();
  $rub=$b['rubros']??[];$prod=$b['productos']??[];
  if(!is_array($rub)||!array_is_list($rub)||count($rub)>100)Http::fail('Hasta 100 rubros.');
  if(!is_array($prod)||!array_is_list($prod)||count($prod)>1000)Http::fail('Hasta 1.000 productos.');
  $rubros=[];foreach($rub as $r){if(!is_array($r))Http::fail('Rubro inválido.');$id=Http::text($r,'id',40);if(!preg_match(self::ID,$id))Http::fail('Identificador de rubro inválido.');$rubros[$id]=['id'=>$id,'nombre'=>Http::text($r,'nombre',80),'descripcion'=>Http::text($r,'descripcion',240,false)];}
  $productos=[];$codigos=[];
  foreach($prod as $p){
   if(!is_array($p))Http::fail('Producto inválido.');$id=Http::text($p,'id',40);if(!preg_match(self::ID,$id))Http::fail('Identificador de producto inválido.');
   $nombre=Http::text($p,'nombre',120);$rubro=(string)($p['rubroId']??'');if($rubro!==''&&!isset($rubros[$rubro]))Http::fail("El rubro de «{$nombre}» no existe.");
   $cobro=$p['cobro']??'';if(!in_array($cobro,self::COBROS,true))Http::fail("Elige cómo se cobra «{$nombre}».");
   $precio=$p['precio']??0;if(!is_numeric($precio)||$precio<0||$precio>100000000)Http::fail("Precio inválido en «{$nombre}».");
   $moneda=$p['moneda']??'USD';if(!in_array($moneda,self::MONEDAS,true))Http::fail('Moneda inválida.');
   $codigo=strtoupper(Http::text($p,'codigo',30,false));if($codigo!==''){if(!preg_match('/^[A-Z0-9._-]{1,30}$/D',$codigo))Http::fail("Código inválido en «{$nombre}».");if(isset($codigos[$codigo]))Http::fail("El código {$codigo} está repetido.");$codigos[$codigo]=1;}
   $frec=null;$dur=null;$stock=null;
   if($cobro==='recurrente'){$frec=$p['frecuencia']??'mensual';if(!in_array($frec,self::FRECUENCIAS,true))Http::fail('Frecuencia inválida.');$d=$p['duracion']??null;$dur=($d===null||$d==='')?null:Http::integer($d,1,600);}
   if($cobro==='venta'){$s=$p['stock']??null;$stock=($s===null||$s==='')?null:Http::integer($s,0,10000000);}
   $tipo=in_array($p['tipo']??'servicio',['servicio','producto'],true)?($p['tipo']??'servicio'):'servicio';
   $productos[]=['id'=>$id,'rubroId'=>$rubro,'nombre'=>$nombre,'codigo'=>$codigo,'tipo'=>$tipo,'cobro'=>$cobro,'precio'=>round((float)$precio,2),'moneda'=>$moneda,'frecuencia'=>$frec,'duracion'=>$dur,'stock'=>$stock,'descripcion'=>Http::text($p,'descripcion',500,false),'activo'=>($p['activo']??true)!==false];
  }
  $antes=$this->db->setting($t,'catalogo.productos',['rubros'=>[],'productos'=>[]]);
  // 2.1.17: los campos propios (Migración de datos) no se editan aquí: se conservan por id.
  $cfAntes=[];foreach($antes['productos']??[] as $x)if(!empty($x['customFields']))$cfAntes[$x['id']]=$x['customFields'];
  foreach($productos as &$x)if(isset($cfAntes[$x['id']]))$x['customFields']=$cfAntes[$x['id']];unset($x);
  $data=['rubros'=>array_values($rubros),'productos'=>$productos,'actualizado'=>gmdate('c')];
  $this->db->saveSetting($t,'catalogo.productos',$data);
  $this->auth->audit('productos.actualizados','productos','',['antes'=>count($antes['productos']??[]).' productos','despues'=>count($productos).' productos','rubros'=>count($rubros)]);
  return $data+['ok'=>true,'canEdit'=>true];
 }

 /* ---------- Tablero, ARP, CRM y módulos ---------- */
 public static function config(mixed $c):array{
  if(!is_array($c)||strlen(DB::json($c))>40000)Http::fail('Personalización inválida.');
  $lista=function($v,int $max){if(!is_array($v))return [];$o=[];foreach(array_slice(array_values($v),0,$max) as $x)if(is_string($x)&&preg_match(self::CLAVE,$x))$o[]=$x;return array_values(array_unique($o));};
  $tb=is_array($c['tablero']??null)?$c['tablero']:[];$arp=is_array($c['arp']??null)?$c['arp']:[];$mod=is_array($c['modulos']??null)?$c['modulos']:[];$crm=is_array($c['crm']??null)?$c['crm']:[];
  $titulo=is_string($tb['titulo']??null)?mb_substr(trim($tb['titulo']),0,60):'';
  return [
   'tablero'=>['visible'=>($tb['visible']??true)!==false,'titulo'=>$titulo,'tarjetas'=>$lista($tb['tarjetas']??[],16)],
   'arp'=>['orden'=>$lista($arp['orden']??[],20),'ocultas'=>$lista($arp['ocultas']??[],80),'secciones'=>$lista($arp['secciones']??[],20)],
   'crm'=>['oportunidad'=>is_string($crm['oportunidad']??null)?mb_substr(trim($crm['oportunidad']),0,30):'','contacto'=>is_string($crm['contacto']??null)?mb_substr(trim($crm['contacto']),0,30):''],
   'modulos'=>['ocultos'=>$lista($mod['ocultos']??[],40)],
  ];
 }
 public function personal(string $m,array $b):array{
  $t=(int)$this->auth->tenant;
  if($m==='GET')return ['config'=>$this->db->setting($t,'ui.personalizar',null),'perfil'=>$this->perfilDe($t)];
  $this->auth->manage();$c=self::config($b['config']??null);$c['actualizado']=gmdate('c');$c['por']=$this->auth->user['name']??'';
  $this->db->saveSetting($t,'ui.personalizar',$c);$this->db->run('UPDATE agora_tenants SET revision=revision+1 WHERE id=?',[$t]);
  $this->auth->audit('personalizacion.actualizada','ajuste','',['tarjetas'=>count($c['tablero']['tarjetas']),'modulosOcultos'=>count($c['modulos']['ocultos'])]);
  return ['ok'=>true,'config'=>$c];
 }
 public function etiquetas(array $b):array{
  $this->auth->manage();$v=$b['items']??null;if(!is_array($v)||!array_is_list($v)||count($v)>200)Http::fail('Hasta 200 etiquetas.');
  $o=[];foreach($v as $x){$x=is_string($x)?trim(mb_strtolower($x)):'';if($x===''||mb_strlen($x)>40)Http::fail('Cada etiqueta debe tener entre 1 y 40 caracteres.');$o[$x]=1;}
  $this->db->saveSetting((int)$this->auth->tenant,'tags',array_keys($o));$this->auth->audit('etiquetas.actualizadas','crm','',['total'=>count($o)]);
  return ['ok'=>true,'items'=>array_keys($o)];
 }
}
