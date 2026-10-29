<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ContactActivity.php';
require __DIR__.'/../src/ContactActions.php';
require __DIR__.'/../src/Actor.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function activityCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function key16():string{return str_replace('-','',(string)bin2hex(random_bytes(12)));}

/** Simulated API client for the documented task, note and appointment calls. */
final class FakeActivityApi {
    public array $calls=[];
    public array $tasks=[];
    public array $notes=[];
    public array $events=[];
    public array $fail=[];
    public int $created=0;
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=[$method,$path,$version,$payload];
            foreach($this->fail as $match=>$status)if(str_contains($path,$match))throw new GhlApiException($status);
            if($method==='GET'&&str_ends_with($path,'/tasks'))return ['status'=>200,'body'=>json_encode(['tasks'=>$this->tasks])];
            if($method==='GET'&&str_ends_with($path,'/notes'))return ['status'=>200,'body'=>json_encode(['notes'=>$this->notes])];
            if($method==='GET'&&str_ends_with($path,'/appointments'))return ['status'=>200,'body'=>json_encode(['events'=>$this->events])];
            if($method==='POST'&&str_ends_with($path,'/notes')){$this->created++;return ['status'=>201,'body'=>json_encode(['note'=>['id'=>'note-'.$this->created]])];}
            if($method==='POST'&&str_ends_with($path,'/tasks')){$this->created++;return ['status'=>201,'body'=>json_encode(['task'=>['id'=>'task-'.$this->created]])];}
            if($method==='PUT'&&str_ends_with($path,'/completed'))return ['status'=>200,'body'=>json_encode(['task'=>['id'=>'task-1']])];
            throw new GhlApiException(404);
        }));
    }
    public function reset():void {$this->calls=[];}
    public function paths():array {return array_map(fn($c)=>$c[0].' '.$c[1],$this->calls);}
}
function clearBudget(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0 WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Activity test','act-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',
  [$connectionId,'seller-1','ana-'.$suffix.'@example.test','Ana Responsable','{}','[]',0]);

 $api=new FakeActivityApi($location);
 $api->tasks=[
  ['id'=>'task-done','title'=>'Tarea hecha','completed'=>true,'dueDate'=>'2026-10-01T10:00:00Z','contactId'=>'contact-1','assignedTo'=>'seller-1'],
  ['id'=>'task-late','title'=>'Confirmar disponibilidad','completed'=>false,'dueDate'=>'2026-11-05T10:00:00Z','body'=>'Llamar por la tarde','contactId'=>'contact-1','assignedTo'=>'seller-1'],
  ['id'=>'task-soon','title'=>'Enviar propuesta','completed'=>false,'dueDate'=>'2026-10-30T10:00:00Z','contactId'=>'contact-1','assignedTo'=>'seller-unknown'],
  ['id'=>'task-foreign','title'=>'De otro contacto','completed'=>false,'contactId'=>'contact-2'],
  ['no-id'=>true],
 ];
 $api->notes=[
  ['id'=>'note-old','body'=>'Primera conversación','dateAdded'=>'2026-10-01T10:00:00Z','userId'=>'seller-1','contactId'=>'contact-1'],
  ['id'=>'note-new','body'=>'Pidió presupuesto','dateAdded'=>'2026-10-28T10:00:00Z','contactId'=>'contact-1'],
  ['id'=>'note-empty','body'=>'   ','contactId'=>'contact-1'],
  ['id'=>'note-foreign','body'=>'De otro contacto','contactId'=>'contact-2'],
 ];
 $api->events=[
  ['id'=>'appt-1','title'=>'Demo de proyecto','startTime'=>'2026-11-02T10:00:00Z','endTime'=>'2026-11-02T11:00:00Z','appointmentStatus'=>'confirmed','address'=>'Videollamada','assignedUserId'=>'seller-1','contactId'=>'contact-1'],
  ['id'=>'appt-old','title'=>'Primera cita','startTime'=>'2026-09-02T10:00:00Z','appointmentStatus'=>'showed','contactId'=>'contact-1'],
  ['id'=>'appt-foreign','title'=>'De otro contacto','contactId'=>'contact-2'],
 ];
 $full=['external_user_id'=>null,'capabilities'=>['viewContacts'=>true,'editContacts'=>true,'viewCalendars'=>true,'assignedDataOnly'=>false]];
 $readOnly=['external_user_id'=>'seller-1','capabilities'=>['viewContacts'=>true,'editContacts'=>false,'viewCalendars'=>false,'assignedDataOnly'=>true]];

 // ---- documented contract --------------------------------------------------
 $activity=new ContactActivity($api->gateway());
 $result=$activity->get($connection,'contact-1',$full);
 activityCheck($api->paths()===['GET /contacts/contact-1/tasks','GET /contacts/contact-1/notes','GET /contacts/contact-1/appointments'],'documented task, note and appointment endpoints queried');
 activityCheck(array_unique(array_column($api->calls,2))===['2021-07-28'],'documented API version for every activity read');
 activityCheck(count($api->calls)===3&&(int)query('SELECT requests_today FROM ghl_sync_state WHERE connection_id=?',[$connectionId])->fetchColumn()===1,'the three reads share a single budget slot so the block loads at once');

 // ---- isolation and normalization -----------------------------------------
 activityCheck(array_column($result['tasks'],'id')===['task-soon','task-late','task-done'],'tasks of this contact only, pending first and by due date');
 activityCheck(array_column($result['notes'],'id')===['note-new','note-old'],'notes of this contact only, newest first, empty body discarded');
 activityCheck(array_column($result['appointments'],'id')===['appt-1','appt-old'],'appointments of this contact only, newest first');
 activityCheck($result['tasks'][1]['assignedName']==='Ana Responsable'&&$result['tasks'][0]['assignedName']===null,'responsible resolved from the imported team, unknown assignee left empty');
 activityCheck($result['notes'][1]['authorName']==='Ana Responsable'&&$result['notes'][0]['authorName']===null,'note author resolved the same way');
 activityCheck($result['appointments'][0]['statusLabel']==='Confirmada'&&$result['appointments'][1]['statusLabel']==='Asistió','appointment status presented in Spanish');
 activityCheck($result['tasks'][1]['body']==='Llamar por la tarde'&&$result['tasks'][2]['completed']===true,'task detail and completion preserved');

 // ---- permissions -----------------------------------------------------------
 $api->reset();clearBudget($connectionId);
 $limited=$activity->get($connection,'contact-1',$readOnly,true);
 activityCheck($limited['appointments']===[]&&$limited['appointmentsVisible']===false,'a member without the calendar module sees no appointments');
 activityCheck($limited['canEdit']===false&&$result['canEdit']===true,'the panel reports whether quick actions are allowed');

 // ---- caching ---------------------------------------------------------------
 $api->reset();
 $activity->get($connection,'contact-1',$full);
 activityCheck($api->calls===[],'a fresh activity block is served from the shared cache');

 // ---- quick actions: note ----------------------------------------------------
 $actions=new ContactActions($api->gateway());
 $api->reset();clearBudget($connectionId);
 $noteKey=key16();
 $created=$actions->note($connection,'contact-1',$full,['body'=>'Nota desde Ágora','requestId'=>$noteKey]);
 activityCheck($created['state']==='accepted'&&$created['id']==='note-1','a note is created and its identifier returned');
 activityCheck($api->paths()===['POST /contacts/contact-1/notes']&&$api->calls[0][3]===['body'=>'Nota desde Ágora'],'documented note endpoint and payload');
 activityCheck((int)query('SELECT COUNT(*) FROM contact_activity_cache WHERE connection_id=? AND contact_id=?',[$connectionId,'contact-1'])->fetchColumn()===0,'a successful action invalidates the cached block');

 $api->reset();clearBudget($connectionId);
 $repeat=$actions->note($connection,'contact-1',$full,['body'=>'Nota desde Ágora','requestId'=>$noteKey]);
 activityCheck($repeat['id']==='note-1'&&$api->calls===[],'repeating the same request key returns the first result without a second note');

 $conflict=false;
 try{$actions->note($connection,'contact-1',$full,['body'=>'Otro texto','requestId'=>$noteKey]);}
 catch(ActionProblem $e){$conflict=$e->reason==='request_conflict';}
 activityCheck($conflict,'reusing a key for different content is refused');

 // ---- quick actions: task ----------------------------------------------------
 $api->reset();clearBudget($connectionId);
 $task=$actions->task($connection,'contact-1',$full,['title'=>'Llamar mañana','dueDate'=>'2026-11-03','body'=>'Repasar propuesta','requestId'=>key16()]);
 $payload=$api->calls[0][3];
 activityCheck($task['state']==='accepted'&&$payload['title']==='Llamar mañana'&&$payload['completed']===false&&$payload['body']==='Repasar propuesta','a task is created with the documented required fields');
 activityCheck($payload['dueDate']==='2026-11-03T12:00:00Z','a calendar date is normalized to an explicit UTC timestamp');

 foreach([['', 'invalid_task'],['   ','invalid_task']] as [$title,$reason]) {
  $bad=null;try{$actions->task($connection,'contact-1',$full,['title'=>$title,'dueDate'=>'2026-11-03','requestId'=>key16()]);}catch(ActionProblem $e){$bad=$e->reason;}
  activityCheck($bad===$reason,'a task without a title is refused before any request');
 }
 foreach(['ayer','2026-13-01','1990-01-01','2026-11-03T99:99'] as $due) {
  $bad=null;try{$actions->task($connection,'contact-1',$full,['title'=>'X','dueDate'=>$due,'requestId'=>key16()]);}catch(ActionProblem $e){$bad=$e->reason;}
  activityCheck($bad==='invalid_due_date','an unusable due date is refused before any request: '.$due);
 }

 // ---- quick actions: complete and reopen ------------------------------------
 $api->reset();clearBudget($connectionId);
 $done=$actions->completeTask($connection,'contact-1',$full,['taskId'=>'task-late','completed'=>true,'requestId'=>key16()]);
 activityCheck($done['state']==='accepted'&&$api->paths()===['PUT /contacts/contact-1/tasks/task-late/completed']&&$api->calls[0][3]===['completed'=>true],'documented completion endpoint, method and payload');
 $api->reset();clearBudget($connectionId);
 $actions->completeTask($connection,'contact-1',$full,['taskId'=>'task-late','completed'=>false,'requestId'=>key16()]);
 activityCheck($api->calls[0][3]===['completed'=>false],'a task can be reopened through the same endpoint');
 $badState=null;
 try{$actions->completeTask($connection,'contact-1',$full,['taskId'=>'../escape','completed'=>true,'requestId'=>key16()]);}
 catch(ActionProblem $e){$badState=$e->reason;}
 activityCheck($badState==='invalid_task_id','an unsafe task identifier never reaches the path');

 // ---- permission and key guards ---------------------------------------------
 $denied=null;
 try{$actions->note($connection,'contact-1',$readOnly,['body'=>'No permitido','requestId'=>key16()]);}
 catch(ActionProblem $e){$denied=[$e->reason,$e->httpStatus];}
 activityCheck($denied===['action_forbidden',403],'a read-only member cannot run quick actions');
 $noKey=null;
 try{$actions->note($connection,'contact-1',$full,['body'=>'Sin clave']);}
 catch(ActionProblem $e){$noKey=$e->reason;}
 activityCheck($noKey==='invalid_request_key','an action without a request key is refused');

 // ---- upstream failures -------------------------------------------------------
 $api->reset();clearBudget($connectionId);$api->fail=['/notes'=>403];
 $rejected=null;$rejectedKey=key16();
 try{$actions->note($connection,'contact-1',$full,['body'=>'Rechazada','requestId'=>$rejectedKey]);}
 catch(ActionProblem $e){$rejected=$e->reason;}
 activityCheck($rejected==='action_rejected','a rejected action reports a safe reason');
 activityCheck(query('SELECT state FROM contact_action_attempts WHERE connection_id=? AND request_id=?',[$connectionId,$rejectedKey])->fetchColumn()==='rejected','the rejected attempt is recorded');
 $again=null;
 try{$actions->note($connection,'contact-1',$full,['body'=>'Rechazada','requestId'=>$rejectedKey]);}
 catch(ActionProblem $e){$again=$e->reason;}
 activityCheck($again==='action_rejected','a rejected key is not silently retried');
 activityCheck(query('SELECT status FROM ghl_connections WHERE id=?',[$connectionId])->fetchColumn()==='connected','a forbidden quick action does not disable the connection');

 $api->reset();clearBudget($connectionId);$api->fail=['/notes'=>500];
 $unknownKey=key16();$unknown=null;
 try{$actions->note($connection,'contact-1',$full,['body'=>'Incierta','requestId'=>$unknownKey]);}
 catch(ActionProblem $e){$unknown=$e->reason;}
 activityCheck($unknown==='action_unconfirmed','an unconfirmed action says so instead of claiming success');
 clearBudget($connectionId);$blocked=null;
 try{$actions->note($connection,'contact-1',$full,['body'=>'Incierta','requestId'=>key16()]);}
 catch(ActionProblem $e){$blocked=$e->reason;}
 activityCheck($blocked==='action_unconfirmed','identical content with a new key is blocked while the result is uncertain');
 $api->fail=[];

 // ---- a malformed read cannot corrupt the panel ------------------------------
 $api->reset();clearBudget($connectionId);
 ContactActivity::forget($connectionId,'contact-1');
 $api->tasks=['not-a-list'=>true];
 $invalid=false;
 try{$activity->get($connection,'contact-1',$full,true);}catch(RuntimeException $e){$invalid=true;}
 activityCheck($invalid&&(int)query('SELECT COUNT(*) FROM contact_activity_cache WHERE connection_id=?',[$connectionId])->fetchColumn()===0,'an unexpected activity payload is rejected before reaching the panel');

 echo 'PASS '.$checks.' contact activity checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
