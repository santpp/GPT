<?php
declare(strict_types=1);
namespace Agora\Controllers;

use Agora\Core\{DB,Http,HttpError};
use Agora\Security\Auth;
use Agora\Fiscal\{Documents,LibroVentas,Tasas,Esquema};
use Agora\Fiscal\Base\{Db as FDb,Audit as FAudit,Env as FEnv,HttpError as FError};

/**
 * Agora New App 2.11.0 · Facturación: facturas fiscales (SENIAT), notas, factura simple, libro de ventas, datos fiscales y tasas BCV.
 * El motor viene de mi.agora (servidor/app/src/Fiscal). Aquí se conecta con la subcuenta, los roles y la auditoría del ERP.
 * Sin datos fiscales completos, la subcuenta solo emite la factura simple.
 */
final class Facturacion {
 /**
  * 2.11.1 · Monedas. Decisión del usuario: por ahora se factura SOLO en bolívares.
  * Para habilitar dólares y euros basta con poner DIVISAS en true: entonces la administración de cada subcuenta
  * (y los dueños) eligen en Datos fiscales en qué monedas factura esa subcuenta. Bolívares siempre está.
  */
 public const DIVISAS=false;
 private const MONEDAS=['VES','USD','EUR'];
 private FDb $f; private Documents $docs;
 public function __construct(public DB $db,public Auth $auth,public array $config){
  if($db->sqlite())Http::fail('La facturación necesita la base de datos MySQL del servidor.',409);
  FEnv::setKey((string)$config['app_key']);
  $this->f=new FDb($db->pdo);
  $this->docs=new Documents($this->f,new FAudit(function(string $texto,?string $entidad,?string $id,?array $detalle):void{
   $this->auth->audit('facturacion','facturacion',(string)($id??''),['detalle'=>$texto]);
  }));
 }
 private function t():int{return (int)$this->auth->tenant;}
 /** @return list<string> monedas en que factura esta subcuenta (la primera es la de siempre) */
 private function monedas():array{
  if(!self::DIVISAS)return ['VES'];
  $l=$this->db->setting($this->t(),'fiscal.monedas',['VES']);$l=is_array($l)?array_values(array_intersect(self::MONEDAS,$l)):[];
  return in_array('VES',$l,true)?$l:array_merge(['VES'],$l);
 }
 /** La moneda de un borrador: la pedida, si la subcuenta factura en ella; si no, se rechaza. */
 private function conMoneda(array $b):array{
  $m=strtoupper((string)($b['moneda']??''));$ok=$this->monedas();
  if($m==='')$m=$ok[0];
  if(!in_array($m,$ok,true))Http::fail(count($ok)===1?'Esta subcuenta factura solo en bolívares.':'Esta subcuenta no factura en esa moneda.');
  return ['moneda'=>$m]+$b;
 }
 private function puede(string $k):bool{try{$this->auth->permit($k);return true;}catch(HttpError){return false;}}
 private function actor():array{$u=$this->auth->requireUser();return ['user_id'=>(int)$u['id'],'email'=>(string)($u['email']??'')];}
 /** Quien solo ve lo suyo (ejecutivos, usuarios) ve y toca solo los documentos que preparó. */
 private function propio(string $uid):void{
  if(!$this->auth->assignedOnly())return;
  $c=$this->f->value('SELECT created_by FROM agora_fiscal_documents WHERE org_id=? AND uid=?',[$this->t(),$uid]);
  if($c!==null&&(int)$c!==(int)$this->auth->requireUser()['id'])Http::fail('Ese documento lo preparó otra persona.',403);
 }
 private function uid(array $b):string{$u=(string)($b['uid']??$_GET['uid']??'');if(!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D',$u))Http::fail('Documento no válido.',404);return $u;}
 private function motivo(array $b):string{$m=trim((string)($b['motivo']??''));if(mb_strlen($m)<5||mb_strlen($m)>400)Http::fail('Escribe el motivo (entre 5 y 400 caracteres). Queda en la auditoría.');return $m;}

 public function route(string $p,string $m,array $b):mixed{
  try{return $this->atender($p,$m,$b);}
  catch(FError $e){Http::fail($e->getMessage(),$e->status);}
  catch(\PDOException $e){
   foreach(['DOCUMENTO_FISCAL_INMUTABLE','NUMERO_CONTROL_INMUTABLE','TRANSICION_INVALIDA'] as $c)if(str_contains($e->getMessage(),$c))Http::fail('Ese documento ya está emitido: no se puede cambiar ni borrar. Anúlalo o corrígelo con una nota.',409);
   if(($e->errorInfo[1]??0)===1062)Http::fail('Ese número ya está usado en otro documento.',409);
   throw $e;
  }
 }

 private function atender(string $p,string $m,array $b):mixed{
  $t=$this->t();
  $this->auth->permit('facturas.ver');
  if($p==='workspace/fiscal'&&$m==='GET'){
   $perfil=$this->docs->profileOut($t);
   return ['perfil'=>$perfil,'fiscal'=>$perfil['completo'],'tasa'=>(new Tasas($this->f))->estado($t)['vigente'],
    'puede'=>['emitir'=>$this->puede('facturas.emitir'),'anular'=>$this->puede('facturas.anular'),'configurar'=>$this->puede('fiscal.configurar'),'libro'=>$this->puede('libro.ventas')],
    'proteccion'=>['ok'=>!Esquema::faltan($this->db->pdo)],'soloPropios'=>$this->auth->assignedOnly(),
    'monedas'=>$this->monedas(),'divisasDisponibles'=>self::DIVISAS];
  }
  // ---------- Datos fiscales, números de control y protección (administración)
  if($p==='workspace/fiscal/perfil'){
   if($m==='GET')return $this->docs->profileOut($t);
   if($m==='PUT'){$this->auth->permit('fiscal.configurar');$r=$this->docs->saveProfile($t,$b,$this->actor()['user_id']);$this->auth->audit('facturacion','facturacion','perfil',['detalle'=>'actualizó los datos fiscales de la empresa']);return $r;}
  }
  if($p==='workspace/fiscal/rangos'){
   if($m==='GET')return ['rangos'=>$this->docs->ranges($t)];
   if($m==='POST'){$this->auth->permit('fiscal.configurar');$this->docs->addRange($t,$b,$this->actor()['user_id']);$this->auth->audit('facturacion','facturacion','rango',['detalle'=>'registró un rango de números de control']);return ['rangos'=>$this->docs->ranges($t)];}
  }
  if($p==='workspace/fiscal/rangos/anular'&&$m==='POST'){$this->auth->permit('fiscal.configurar');$this->docs->annulRange($t,Http::integer($b['id']??0,1,PHP_INT_MAX));$this->auth->audit('facturacion','facturacion','rango',['detalle'=>'anuló un rango de números de control sin usar']);return ['rangos'=>$this->docs->ranges($t)];}
  if($p==='workspace/fiscal/verificar'&&$m==='GET'){$this->auth->permit('fiscal.configurar');return $this->docs->verifyChain($t);}
  if($p==='workspace/fiscal/proteccion'&&$m==='POST'){$this->auth->permit('fiscal.configurar');$r=Esquema::instalar($this->db->pdo);return ['ok'=>$r['ok'],'mensaje'=>$r['ok']?'Protección activa.':'El usuario de la base de datos no tiene permiso para crearla. Lo emitido sigue protegido por Ágora y por la cadena de huellas.'];}
  // ---------- Monedas de la subcuenta (solo cuando las divisas están habilitadas)
  if($p==='workspace/fiscal/monedas'&&$m==='PUT'){
   $this->auth->permit('fiscal.configurar');
   if(!self::DIVISAS)Http::fail('Por ahora la facturación es solo en bolívares.',409);
   $l=is_array($b['monedas']??null)?array_values(array_intersect(self::MONEDAS,array_map('strval',$b['monedas']))):[];
   if(!in_array('VES',$l,true))array_unshift($l,'VES');
   $this->db->saveSetting($this->t(),'fiscal.monedas',$l);$this->auth->audit('facturacion','facturacion','monedas',['detalle'=>'facturará en: '.implode(', ',$l)]);
   return ['monedas'=>$this->monedas()];
  }
  // ---------- Tasas BCV
  if($p==='workspace/fiscal/tasas'){
   $tasas=new Tasas($this->f);
   if($m==='GET')return $tasas->estado($t);
   if($m==='POST'){$this->auth->permit('facturas.emitir');$tasas->guardarManual($t,$b['fecha']??null,$b['tasa']??null,$this->actor()['user_id']);$this->auth->audit('facturacion','facturacion','tasa',['detalle'=>'escribió la tasa BCV del '.(string)($b['fecha']??'')]);return $tasas->estado($t);}
  }
  if($p==='workspace/fiscal/tasas/actualizar'&&$m==='POST'){$this->auth->permit('fiscal.configurar');$r=(new Tasas($this->f))->actualizar();return $r+(new Tasas($this->f))->estado($t);}
  // ---------- Clientes con datos fiscales guardados (para no escribirlos cada vez)
  if($p==='workspace/fiscal/clientes'&&$m==='GET'){
   $q=mb_substr(trim((string)($_GET['q']??'')),0,60);$s='%'.str_replace(['%','_'],['\\%','\\_'],$q).'%';
   return ['items'=>$this->f->all('SELECT name AS nombre,rif,address AS direccion,email AS correo,phone AS telefono FROM agora_fiscal_clients WHERE org_id=? AND (name LIKE ? OR rif LIKE ?) ORDER BY updated_at DESC LIMIT 12',[$t,$s,$s])];
  }
  // ---------- Documentos
  if($p==='workspace/facturas'){
   if($m==='GET'){$q=$_GET;if($this->auth->assignedOnly())$q['creador']=(string)$this->actor()['user_id'];return $this->docs->list($t,$q);}
   if($m==='POST'){$this->auth->permit('facturas.emitir');return $this->docs->createDraft($t,$this->conMoneda($b),$this->actor()['user_id']);}
  }
  if($p==='workspace/facturas/doc'){
   $uid=$this->uid($b);$this->propio($uid);
   if($m==='GET')return $this->docs->get($t,$uid);
   $this->auth->permit('facturas.emitir');
   if($m==='PUT')return $this->docs->updateDraft($t,$uid,$this->conMoneda($b));
   if($m==='DELETE'){$this->docs->deleteDraft($t,$uid);return ['ok'=>true];}
  }
  if($p==='workspace/facturas/emitir'&&$m==='POST'){$this->auth->permit('facturas.emitir');$uid=$this->uid($b);$this->propio($uid);$d=$this->docs->emit($t,$uid,$this->actor());$this->recordarCliente($d);return $d;}
  if($p==='workspace/facturas/control'&&$m==='POST'){$this->auth->permit('facturas.emitir');$uid=$this->uid($b);$this->propio($uid);return $this->docs->assignControl($t,$uid,(string)($b['control']??''),isset($b['asignado'])&&$b['asignado']!==''?(string)$b['asignado']:null,$this->actor());}
  if($p==='workspace/facturas/anular'&&$m==='POST'){$this->auth->permit('facturas.anular');return $this->docs->annul($t,$this->uid($b),$this->motivo($b),$this->actor(),null);}
  if($p==='workspace/facturas/nota'&&$m==='POST'){$this->auth->permit('facturas.anular');$tipo=strtoupper((string)($b['tipo']??''));if(!in_array($tipo,['NC','ND'],true))Http::fail('Indica si es nota de crédito o de débito.');return $this->docs->note($t,$this->uid($b),$tipo,$b,$this->motivo($b),$this->actor(),null);}
  if($p==='workspace/facturas/ver'&&$m==='GET'){$uid=$this->uid($b);$this->propio($uid);return ['html'=>$this->docs->representation($t,$uid,($_GET['copia']??'')==='1')];}
  // ---------- Libro de ventas
  if($p==='workspace/libro-ventas'&&$m==='GET'){
   $this->auth->permit('libro.ventas');$per=Http::period($_GET['periodo']??gmdate('Y-m'));
   $libro=LibroVentas::build($this->f,$t,$per);$perfil=$this->docs->profile($t)??[];
   return ['libro'=>$libro,'csv'=>LibroVentas::csv($libro,$perfil)];
  }
  Http::fail('Ruta no disponible.',404);
 }

 /** Guarda RIF y domicilio del cliente de un documento emitido, para proponerlos la próxima vez. */
 private function recordarCliente(array $d):void{
  $c=$d['cliente']??[];$n=trim((string)($c['nombre']??''));if($n==='')return;
  $rif=trim((string)($c['rif']??''));$key=$rif!==''?'r:'.strtoupper($rif):'n:'.mb_strtolower(mb_substr($n,0,58));
  try{$this->f->upsert('agora_fiscal_clients',['org_id'=>$this->t(),'doc_key'=>mb_substr($key,0,60),'name'=>mb_substr($n,0,200),'rif'=>mb_substr($rif,0,20),'address'=>mb_substr((string)($c['direccion']??''),0,400),'email'=>$c['correo']??null,'phone'=>$c['telefono']??null,'updated_at'=>gmdate('Y-m-d H:i:s.v')],['org_id','doc_key'],['name','rif','address','email','phone','updated_at']);}catch(\Throwable){}
 }
}
