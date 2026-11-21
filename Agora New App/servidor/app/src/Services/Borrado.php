<?php
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http};

/**
 * Agora New App 2.17.0 · Borrado definitivo de una subcuenta o de un usuario de subcuenta.
 * Subcuenta: se borra todo lo suyo (tablas agora_* por subcuenta y tablas del motor por su conexión, que tiene el mismo número),
 * sus usuarios y la subcuenta. No hay papelera: por eso la pantalla y el servidor piden resolver una operación (Desafio).
 * Usuario: se cierran sus sesiones y accesos, sus registros quedan sin dueño (no se pierden ventas ni contratos) y se borra.
 */
final class Borrado {
 /** Tablas del motor de la cuenta: se borran por su conexión (connection_id = número de la subcuenta). */
 private const MOTOR_ID='ghl_connections';
 public function __construct(private DB $db){}
 private function mysql():bool{return $this->db->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql';}
 /** [tabla => columna] de las tablas que tienen esa columna (solo nombres seguros de la propia base). */
 private function tablasCon(string $col,string $prefijo):array{
  if(!$this->mysql())return [];
  $out=[];foreach($this->db->all('SELECT table_name t FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name=?',[$col]) as $r){$t=(string)($r['t']??$r['TABLE_NAME']??'');if(preg_match('/^[a-z0-9_]{1,64}$/D',$t)&&($prefijo===''?!str_starts_with($t,'agora_'):str_starts_with($t,$prefijo)))$out[]=$t;}
  return $out;
 }
 /** Limpia lo que cuelga de un usuario (sin borrarlo). */
 private function limpiarUsuario(int $id):void{
  foreach(['agora_sessions','agora_access_codes','agora_trusted_devices','agora_user_links','agora_assignments','agora_idempotency','agora_widget_cache'] as $t){try{$this->db->run("DELETE FROM $t WHERE user_id=?",[$id]);}catch(\PDOException){}}
  try{$this->db->run("DELETE FROM agora_dashboards WHERE user_id=? AND alcance<>'equipo'",[$id]);}catch(\PDOException){}
  $this->db->run('UPDATE agora_records SET owner_id=NULL WHERE owner_id=?',[$id]);
 }
 public function usuario(int $tenant,int $id):array{
  $u=$this->db->one('SELECT id,name,email,role FROM agora_users WHERE id=? AND local_tenant_id=?',[$id,$tenant]);if(!$u)Http::fail('Usuario no encontrado en esta subcuenta.',404);
  $this->db->transaction(function()use($id){$this->limpiarUsuario($id);$this->db->run('DELETE FROM agora_users WHERE id=?',[$id]);});
  return $u;
 }
 /** Borra la subcuenta y todo lo suyo. Devuelve cuántas filas se borraron por tabla (para la auditoría). */
 public function subcuenta(int $tenant):array{
  $t=$this->db->one('SELECT id,name FROM agora_tenants WHERE id=?',[$tenant]);if(!$t)Http::fail('La subcuenta no existe.',404);
  $cuenta=[];
  $pdo=$this->db->pdo;$mysql=$this->mysql();
  if($mysql)$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
  try{
   $this->db->transaction(function()use($tenant,&$cuenta,$mysql){
    $del=function(string $sql,array $a,string $k)use(&$cuenta){try{$n=$this->db->run($sql,$a);$n=is_object($n)&&method_exists($n,'rowCount')?$n->rowCount():(int)$n;if($n)$cuenta[$k]=($cuenta[$k]??0)+$n;}catch(\PDOException $e){if(!str_contains($e->getMessage(),"doesn't exist")&&!str_contains($e->getMessage(),'no such table'))throw $e;}};
    foreach($this->db->all('SELECT id FROM agora_users WHERE local_tenant_id=?',[$tenant]) as $u)$this->limpiarUsuario((int)$u['id']);
    // facturas: las líneas cuelgan del documento
    $del('DELETE l FROM agora_fiscal_lines l JOIN agora_fiscal_documents d ON d.id=l.document_id WHERE d.org_id=?',[$tenant],'agora_fiscal_lines');
    $agora=$mysql?$this->tablasCon('tenant_id','agora_'):['agora_audit','agora_cuenta_contactos','agora_cuenta_oportunidades','agora_dashboards','agora_ghl_connections','agora_idempotency','agora_jobs','agora_records','agora_settings','agora_signatures','agora_user_links','agora_widget_cache','agora_widget_formulas','agora_widget_variables','agora_assignments'];
    foreach($agora as $tb)$del("DELETE FROM $tb WHERE tenant_id=?",[$tenant],$tb);
    $fiscal=$mysql?$this->tablasCon('org_id','agora_'):['agora_fiscal_documents','agora_fiscal_clients','agora_fiscal_profiles','agora_fiscal_ranges','agora_fiscal_sequences','agora_fiscal_tasas','agora_fiscal_tasas_ref'];
    foreach($fiscal as $tb)$del("DELETE FROM $tb WHERE org_id=?",[$tenant],$tb);
    // motor de la cuenta: por conexión (mismo número que la subcuenta), nunca por su tenant_id (que es el de la agencia)
    foreach($mysql?$this->tablasCon('connection_id',''):[] as $tb)$del("DELETE FROM $tb WHERE connection_id=?",[$tenant],$tb);
    $del('DELETE FROM '.self::MOTOR_ID.' WHERE id=?',[$tenant],self::MOTOR_ID);
    $del('DELETE FROM agora_users WHERE local_tenant_id=?',[$tenant],'agora_users');
    $del('DELETE FROM agora_tenants WHERE id=?',[$tenant],'agora_tenants');
   });
  } finally {if($mysql)$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
  // si era la subcuenta por la que salen los correos de Ágora, se apaga ese canal (se vuelve a entrar solo con la clave)
  $c=$this->db->setting(0,'correo.canal',null);if(is_array($c)&&(int)($c['connectionId']??0)===$tenant){$c['connectionId']=null;$this->db->saveSetting(0,'correo.canal',$c);}
  return ['nombre'=>$t['name'],'filas'=>$cuenta];
 }
}
