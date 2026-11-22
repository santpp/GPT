<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\DB;
/**
 * Agora New App 2.9.0 · Espejo de la cuenta: contactos y oportunidades (incluidas las ganadas).
 * Cada lectura de la cuenta sobrescribe la fila del mismo identificador (tenant + id de la cuenta): nunca hay duplicados.
 * Solo se lee: lo que se cambia va primero a la cuenta y después se vuelve a escribir aquí. No guarda conversaciones.
 */
final class Espejo {
 private const ID='/^[A-Za-z0-9_-]{1,80}$/D';
 public function __construct(private DB $db,private int $tenant){}

 private static function txt(mixed $v,int $max):?string{return is_string($v)&&trim($v)!==''?mb_substr(trim($v),0,$max):null;}
 private static function fecha(mixed $v):?string{if(is_numeric($v)){$t=(int)$v;if($t>100000000000)$t=intdiv($t,1000);}else{$t=is_string($v)?strtotime($v):false;}return $t?gmdate('Y-m-d H:i:s',$t):null;}

 /** Un contacto tal como lo da la cuenta (ficha completa o fila de una lista). */
 public function contacto(array $c):void{
  $id=$c['id']??null;if(!is_string($id)||!preg_match(self::ID,$id))return;
  $nombre=self::txt($c['contactName']??$c['name']??null,190)??trim((self::txt($c['firstName']??null,90)??'').' '.(self::txt($c['lastName']??null,90)??''));
  $tags=is_array($c['tags']??null)?array_values(array_filter(array_map(fn($t)=>self::txt($t,100),$c['tags']))):null;
  $this->db->run('INSERT INTO agora_cuenta_contactos(tenant_id,ghl_id,nombre,correo,telefono,asignado_a,etiquetas,creado_en,datos,leido_en) VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),correo=VALUES(correo),telefono=VALUES(telefono),asignado_a=VALUES(asignado_a),etiquetas=VALUES(etiquetas),creado_en=VALUES(creado_en),datos=VALUES(datos),leido_en=VALUES(leido_en)',
   [$this->tenant,$id,$nombre?:'Contacto sin nombre',($e=self::txt($c['email']??null,190))?mb_strtolower($e):null,self::txt($c['phone']??null,60),self::txt($c['assignedTo']??null,80),$tags===null?null:DB::json($tags),self::fecha($c['dateAdded']??null),DB::json($c)]);
 }
 /** Una oportunidad tal como la da la cuenta. Conserva el enlace con la venta de Ágora si ya lo tenía. */
 public function oportunidad(array $o):void{
  $id=$o['id']??null;if(!is_string($id)||!preg_match(self::ID,$id))return;
  $estado=strtolower((string)($o['status']??'open'));if(!in_array($estado,['open','won','lost','abandoned'],true))$estado='open';
  $ganada=$estado==='won'?self::fecha($o['wonAt']??$o['lastStatusChangeAt']??$o['updatedAt']??null):null;
  $cid=$o['contactId']??($o['contact']['id']??null);
  $this->db->run('INSERT INTO agora_cuenta_oportunidades(tenant_id,ghl_id,nombre,contacto_id,contacto_nombre,embudo_id,etapa_id,estado,valor,asignado_a,ganada_en,creada_en,datos,leido_en) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),contacto_id=VALUES(contacto_id),contacto_nombre=VALUES(contacto_nombre),embudo_id=VALUES(embudo_id),etapa_id=VALUES(etapa_id),estado=VALUES(estado),valor=VALUES(valor),asignado_a=VALUES(asignado_a),ganada_en=VALUES(ganada_en),creada_en=VALUES(creada_en),datos=VALUES(datos),leido_en=VALUES(leido_en)',
   [$this->tenant,$id,self::txt($o['name']??null,190)??'',is_string($cid)&&preg_match(self::ID,$cid)?$cid:null,self::txt($o['contactName']??($o['contact']['name']??null),190),self::txt($o['pipelineId']??null,80),self::txt($o['pipelineStageId']??null,80),$estado,
    is_numeric($o['monetaryValue']??$o['value']??null)?round((float)($o['monetaryValue']??$o['value']),2):0,self::txt($o['assignedTo']??null,80),$ganada,self::fecha($o['createdAt']??$o['dateAdded']??null),DB::json($o)]);
 }
 public function enlazarVenta(string $opId,string $ventaId):void{$this->db->run('UPDATE agora_cuenta_oportunidades SET venta_id=? WHERE tenant_id=? AND ghl_id=?',[$ventaId,$this->tenant,$opId]);}
 public function op(string $id):?array{return $this->db->one('SELECT * FROM agora_cuenta_oportunidades WHERE tenant_id=? AND ghl_id=?',[$this->tenant,$id]);}
 public function contactoPorId(string $id):?array{return $this->db->one('SELECT * FROM agora_cuenta_contactos WHERE tenant_id=? AND ghl_id=?',[$this->tenant,$id]);}
 /** Contacto de la cuenta con ese correo o teléfono (para enlazar ventas sin duplicar clientes). */
 public function buscarContacto(?string $correo,?string $telefono):?array{
  $correo=$correo?mb_strtolower(trim($correo)):'';$tel=$telefono?preg_replace('/[^\d+]/','',$telefono):'';
  if($correo!==''&&($r=$this->db->one('SELECT * FROM agora_cuenta_contactos WHERE tenant_id=? AND correo=? LIMIT 1',[$this->tenant,$correo])))return $r;
  if(strlen($tel)>=7&&($r=$this->db->one('SELECT * FROM agora_cuenta_contactos WHERE tenant_id=? AND telefono=? LIMIT 1',[$this->tenant,$tel])))return $r;
  return null;
 }
 /** Ganadas de un mes (YYYY-MM) según el espejo. */
 public function ganadas(string $ym):array{
  $desde=$ym.'-01 00:00:00';$hasta=gmdate('Y-m-d H:i:s',strtotime($desde.' +1 month'));
  return $this->db->all("SELECT * FROM agora_cuenta_oportunidades WHERE tenant_id=? AND estado='won' AND ganada_en>=? AND ganada_en<? ORDER BY ganada_en DESC LIMIT 2000",[$this->tenant,$desde,$hasta]);
 }
 /**
  * Recorre una respuesta del motor y guarda en el espejo los contactos y las oportunidades que trae.
  * Rutas: contactos (lista y ficha), oportunidades (tablero, búsqueda, ganadas y cambios).
  */
 public function deRespuesta(string $path,array $body):void{
  $p=preg_replace('~^/api/~','',$path);
  try{
   if($p==='workspace/contacts')foreach((array)($body['items']??[]) as $c){if(is_array($c))$this->contacto($c);}
   elseif(in_array($p,['workspace/contact','workspace/record'],true)){
    $c=$body['contact']??($body['profile']??null);if(is_array($c))$this->contacto($c);
    foreach((array)($body['opportunities']??[]) as $o)if(is_array($o))$this->oportunidad($o+(is_array($c)&&isset($c['id'])?['contactId'=>$c['id']]:[]));
   }
   elseif($p==='workspace/board'){foreach((array)($body['stages']??[]) as $s)foreach((array)($s['items']??[]) as $o)if(is_array($o))$this->oportunidad($o+['pipelineId'=>$body['pipelineId']??($body['pipeline']['id']??null),'pipelineStageId'=>$s['id']??null]);}
   elseif($p==='workspace/opportunities'){foreach((array)($body['items']??$body['opportunities']??[]) as $o)if(is_array($o))$this->oportunidad($o);}
   elseif($p==='workspace/won'){foreach((array)($body['items']??[]) as $o)if(is_array($o))$this->oportunidad($o+['status'=>'won','monetaryValue'=>$o['value']??null]);}
   elseif(preg_match('~^workspace/opportunity/(new|edit|status)$~',$p)&&is_array($body['opportunity']??null))$this->oportunidad($body['opportunity']);
  }catch(\Throwable){/* el espejo nunca rompe la pantalla */}
 }
}
