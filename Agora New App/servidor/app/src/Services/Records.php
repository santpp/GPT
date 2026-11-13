<?php
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\{DB,Http}; use Agora\Security\Auth;
final class Records {
 public function __construct(public DB $db,public Auth $auth){}
 public function get(string $kind,string $uid,bool $lock=false):array{
  $sql='SELECT * FROM agora_records WHERE tenant_id=? AND kind=? AND uid=?';$args=[$this->auth->tenant,$kind,$uid];
  if($kind!=='pipeline'&&$this->auth->assignedOnly()){$sql.=' AND owner_id=?';$args[]=$this->auth->user['id'];}
  $r=$this->db->one($sql.($lock?' FOR UPDATE':''),$args);if(!$r)Http::fail('Registro no encontrado en este espacio.',404);return $r;
 }
 public function payload(array $r):array{return array_replace(json_decode($r['data'],true,512,JSON_THROW_ON_ERROR),['id'=>$r['uid'],'version'=>(int)$r['version']]);}
 public function page(string $kind,array $filters=[]):array{
  $limit=min(100,max(1,(int)($_GET['limit']??100)));$cursor=max(0,(int)($_GET['cursor']??0));$args=[$this->auth->tenant,$kind];$where='tenant_id=? AND kind=?';
  if($kind!=='pipeline'&&$this->auth->assignedOnly()){$where.=' AND owner_id=?';$args[]=$this->auth->user['id'];}
  foreach(['status','period'] as $key)if(isset($filters[$key])){$where.=" AND $key=?";$args[]=$filters[$key];}
  $q=trim((string)($_GET['query']??$_GET['q']??''));if($q!==''){$where.=' AND label LIKE ?';$args[]='%'.mb_substr($q,0,120).'%';}
  $total=(int)$this->db->one("SELECT COUNT(*) n FROM agora_records WHERE $where",$args)['n'];$args[]=$cursor;
  $rows=$this->db->all("SELECT * FROM agora_records WHERE $where AND seq>? ORDER BY seq LIMIT ".($limit+1),$args);$more=count($rows)>$limit;if($more)array_pop($rows);
  return ['items'=>array_map(fn($r)=>$this->payload($r),$rows),'total'=>$total,'hasMore'=>$more,'nextCursor'=>$more?(string)end($rows)['seq']:null,'partial'=>$more,'restricted'=>$this->auth->assignedOnly()];
 }
 public function save(string $kind,string $uid,array $data,string $label='',string $status='',string $period='',?int $owner=null,?int $expected=null):array{
  $t=$this->auth->tenant??throw new \LogicException('Missing tenant');$json=DB::json($data);if(strlen($json)>1800000)Http::fail('Registro demasiado grande.',413);
  $old=$this->db->one('SELECT * FROM agora_records WHERE tenant_id=? AND kind=? AND uid=? FOR UPDATE',[$t,$kind,$uid]);
  if($old){if($this->auth->assignedOnly()&&(int)$old['owner_id']!==(int)$this->auth->user['id'])Http::fail('Registro ajeno.',403);if($expected!==null&&(int)$old['version']!==$expected)Http::fail('El registro cambió. Recarga antes de guardar.',409);$this->db->run('UPDATE agora_records SET data=?,label=?,status=?,period=?,owner_id=COALESCE(?,owner_id),version=version+1,updated_at=UTC_TIMESTAMP() WHERE seq=?',[$json,mb_substr($label,0,254),$status,$period,$owner,$old['seq']]);}
  else{$this->db->run('INSERT INTO agora_records(tenant_id,kind,uid,owner_id,label,status,period,data) VALUES(?,?,?,?,?,?,?,?)',[$t,$kind,$uid,$owner??$this->auth->user['id'],mb_substr($label,0,254),$status,$period,$json]);}
  $this->db->run('UPDATE agora_tenants SET revision=revision+1 WHERE id=?',[$t]);$this->auth->audit($old?'edito':'creo',$kind,$uid);return $data+['id'=>$uid,'version'=>$old?(int)$old['version']+1:1];
 }
 public function delete(string $kind,string $uid):void{$r=$this->get($kind,$uid,true);$this->db->run('DELETE FROM agora_records WHERE seq=?',[$r['seq']]);$this->auth->audit('elimino',$kind,$uid);}
}
