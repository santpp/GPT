<?php
declare(strict_types=1);
namespace Agora\Controllers;
use Agora\Core\{DB,Http};use Agora\Security\Auth;use Agora\Services\Records;
final class Messaging {
 public Records $r;public function __construct(public DB $db,public Auth $a){$this->r=new Records($db,$a);}
 public function reminders():object{
  $args=[$this->a->tenant];$scope='';if($this->a->assignedOnly()){$scope=' AND owner_id=?';$args[]=$this->a->user['id'];}
  $rows=$this->db->all("SELECT data FROM agora_records WHERE tenant_id=? AND kind='task' AND status='pendiente' AND uid LIKE 'reminder\_%'$scope ORDER BY seq DESC LIMIT 100",$args);$map=[];foreach($rows as $r){$d=json_decode($r['data'],true);$map[$d['conversationId']]=['at'=>$d['at'],'nota'=>$d['detalle']];}return (object)$map;
 }
 public function handle(string $p,string $m,array $b):array{
  $cid=(string)($_GET['conversationId']??$b['conversationId']??'');$conv=$this->r->payload($this->r->get('conversation',$cid,$m!=='GET'));$contact=(string)$conv['contactId'];
  if($p==='workspace/contact'&&$m==='GET'){$_GET['contactId']=$contact;return (new Workspace($this->db,$this->a))->contacts('GET',[])+['notes'=>[],'tasks'=>[],'appointments'=>[]];}
  if($p==='workspace/messages'&&$m==='GET'){$args=[$this->a->tenant,$cid];$before=max(0,(int)($_GET['before']??$_GET['lastMessageId']??0));$where='';if($before){$where=' AND seq<?';$args[]=$before;}$rows=$this->db->all("SELECT * FROM agora_records WHERE tenant_id=? AND kind='message' AND status=?$where ORDER BY seq DESC LIMIT 101",$args);$more=count($rows)>100;if($more)array_pop($rows);return ['items'=>array_reverse(array_map(fn($r)=>$this->r->payload($r),$rows)),'hasMore'=>$more,'lastMessageId'=>$more?(string)end($rows)['seq']:null,'nextCursor'=>$more?(string)end($rows)['seq']:null,'contactId'=>$contact,'conversationId'=>$cid,'syncIntervalSeconds'=>120,'channels'=>array_map(fn($type)=>['type'=>$type,'label'=>$type,'available'=>isset($this->a->config['ghl']['tenants'][$this->a->tenant]),'needsSubject'=>$type==='Email'],['SMS','Email','WhatsApp','IG','FB'])];}
  if($p==='workspace/activity'&&$m==='GET'){$rows=$this->db->all("SELECT * FROM agora_records WHERE tenant_id=? AND kind='note' AND status=? ORDER BY seq DESC LIMIT 100",[$this->a->tenant,$cid]);return ['items'=>[],'notes'=>array_map(fn($r)=>$this->r->payload($r),$rows),'tasks'=>[],'appointments'=>[]];}
  if($p==='workspace/assign'&&$m==='GET')return ['assignees'=>(new Workspace($this->db,$this->a))->assignees(),'assignedTo'=>$conv['assignedTo']??null];
  $this->a->write();
  if($p==='workspace/read'&&$m==='POST'){$conv['unreadCount']=0;unset($conv['version']);$this->r->save('conversation',$cid,$conv,$conv['contactName']??'');return ['ok'=>true];}
  if($p==='workspace/send'&&$m==='POST'){
   if(!isset($this->a->config['ghl']['tenants'][$this->a->tenant]))Http::fail('No hay canal de mensajería conectado.',503);
   $text=Http::text(['body'=>$b['body']??$b['message']??''],'body',10000);$type=$b['type']??$b['channel']??'';$types=['TYPE_SMS'=>'SMS','TYPE_EMAIL'=>'Email','TYPE_WHATSAPP'=>'WhatsApp','TYPE_INSTAGRAM'=>'IG','TYPE_FACEBOOK'=>'FB'];$type=$types[$type]??$type;if(!in_array($type,['SMS','Email','WhatsApp','IG','FB'],true))Http::fail('Canal no válido.');
   $subject=$type==='Email'?Http::text($b,'subject',190):'';
   $id=bin2hex(random_bytes(12));$msg=['id'=>$id,'body'=>$text,'direction'=>'outbound','dateAdded'=>gmdate('c'),'type'=>$type,'status'=>'queued','conversationId'=>$cid,'contactId'=>$contact];$this->r->save('message',$id,$msg,'Mensaje en cola',$cid);
   $this->db->run("INSERT INTO agora_jobs(tenant_id,kind,payload) VALUES(?,'ghl.send',?)",[$this->a->tenant,DB::json(['messageId'=>$id,'conversationId'=>$cid,'contactId'=>$contact,'type'=>$type,'message'=>$text,'subject'=>$subject])]);return ['ok'=>true,'queued'=>true,'message'=>$msg,'id'=>$id,'delivery'=>'queued','messageId'=>$id,'deliveryStatus'=>'queued'];
  }
  if($p==='workspace/assign'&&$m==='POST'){$this->a->permit('conversaciones.reasignar');$owner=Http::integer($b['assignedTo']??0,1,PHP_INT_MAX);if(!$this->db->one('SELECT id FROM agora_users WHERE id=? AND local_tenant_id=? AND active=1',[$owner,$this->a->tenant]))Http::fail('Responsable no disponible.');$conv['assignedTo']=(string)$owner;unset($conv['version']);$this->r->save('conversation',$cid,$conv,$conv['contactName']??'');$this->db->run("UPDATE agora_records SET owner_id=? WHERE tenant_id=? AND ((kind='conversation' AND uid=?) OR (kind='contact' AND uid=?))",[$owner,$this->a->tenant,$cid,$contact]);return ['assignedTo'=>(string)$owner,'moved'=>1];}
  if($p==='workspace/reminders'&&in_array($m,['POST','DELETE'])){
   $uid='reminder_'.$cid;
   if($m==='DELETE'){$r=$this->db->one("SELECT * FROM agora_records WHERE tenant_id=? AND kind='task' AND uid=?",[$this->a->tenant,$uid]);if($r)$this->r->delete('task',$uid);return ['ok'=>true];}
   $at=Http::text($b,'at',30);try{$date=new \DateTimeImmutable($at,new \DateTimeZone('America/Caracas'));}catch(\Exception){Http::fail('Fecha inválida.');}if($date->getTimestamp()<time())Http::fail('Selecciona una fecha futura.');$d=['id'=>$uid,'titulo'=>'Seguimiento: '.($conv['contactName']??$contact),'detalle'=>Http::text($b,'nota',2000,false),'vence'=>$date->format('Y-m-d'),'hora'=>$date->format('H:i'),'estado'=>'pendiente','responsable'=>$this->a->user['name'],'origen'=>'recordatorio','contactId'=>$contact,'conversationId'=>$cid,'contacto'=>$conv['contactName']??'','at'=>$date->format(\DateTimeInterface::ATOM)];$this->r->save('task',$uid,$d,$d['titulo'],'pendiente',$date->format('Y-m'));return ['at'=>$d['at']];
  }
  if($p==='workspace/activity/contact'&&$m==='POST'){$_GET['contactId']=$contact;return (new Workspace($this->db,$this->a))->contacts('POST',$b);}
  if($p==='workspace/activity/note'&&$m==='POST'){$id=bin2hex(random_bytes(12));$d=['id'=>$id,'body'=>Http::text(['body'=>$b['body']??$b['note']??''],'body',5000),'dateAdded'=>gmdate('c'),'userId'=>$this->a->user['id']];$this->r->save('note',$id,$d,'Nota',$cid);return ['ok'=>true,'note'=>$d];}
  Http::fail('Acción no disponible en esta integración.',501);
 }
}
