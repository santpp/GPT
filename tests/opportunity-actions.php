<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/OpportunityActions.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function actionCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function key16():string{return bin2hex(random_bytes(12));}

/** Simulated API client for the documented opportunity writes. */
final class FakeWriteApi {
    public array $calls=[];
    public array $pipelines=[];
    public array $records=[];
    public array $fail=[];
    public ?Closure $onWrite=null;
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=['method'=>$method,'path'=>$path,'version'=>$version,'payload'=>$payload];
            foreach($this->fail as $match=>$status)if(str_contains($path,$match))throw new GhlApiException($status);
            if($this->onWrite&&in_array($method,['POST','PUT'],true))($this->onWrite)($path,$method,$payload);
            if(str_starts_with($path,'/opportunities/pipelines'))return ['status'=>200,'body'=>json_encode(['pipelines'=>$this->pipelines])];
            if($method==='POST'&&$path==='/opportunities/')return ['status'=>200,'body'=>json_encode(['opportunity'=>['id'=>'new-opp']])];
            if($method==='PUT'&&str_ends_with($path,'/status'))return ['status'=>200,'body'=>json_encode(['succeded'=>true])];
            if($method==='PUT')return ['status'=>200,'body'=>json_encode(['opportunity'=>['id'=>substr($path,strrpos($path,'/')+1)]])];
            if($method==='GET'&&preg_match('~^/opportunities/([A-Za-z0-9_-]+)$~',$path,$m))
                return isset($this->records[$m[1]])?['status'=>200,'body'=>json_encode(['opportunity'=>$this->records[$m[1]]])]:throw new GhlApiException(404);
            throw new GhlApiException(404);
        }));
    }
    public function reset():void {$this->calls=[];}
    public function writes():array {return array_values(array_filter($this->calls,fn($c)=>$c['method']!=='GET'));}
    public function paths():array {return array_map(fn($c)=>explode('?',$c['path'])[0],$this->calls);}
}
function openBudget(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$connection]);}
function problem(Closure $run):?ActionProblem {
    try{$run();}catch(ActionProblem $e){return $e;}
    return null;
}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Opportunity write test','oppw-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 foreach([['seller-1','Ana Responsable',1],['seller-2','Luis Comercial',1]] as [$external,$name,$active])
  query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only,active) VALUES(?,?,?,?,?,?,?,?)',
   [$connectionId,$external,$external.'-'.$suffix.'@example.test',$name,'{}','[]',0,$active]);

 $api=new FakeWriteApi($location);
 $api->pipelines=[['id'=>'pipe-1','name'=>'Pipeline comercial','locationId'=>$location,'stages'=>[['id'=>'stage-1','name'=>'Nuevo lead'],['id'=>'stage-2','name'=>'Propuesta enviada']]]];
 $api->records=[
  'opp-1'=>['id'=>'opp-1','name'=>'Proyecto Andreina','locationId'=>$location,'contactId'=>'c1','pipelineId'=>'pipe-1','pipelineStageId'=>'stage-1','monetaryValue'=>1000,'assignedTo'=>'seller-1','status'=>'open'],
  'opp-foreign'=>['id'=>'opp-foreign','name'=>'De otra cuenta','locationId'=>'foreign','contactId'=>'c9','pipelineId'=>'pipe-1','pipelineStageId'=>'stage-1','status'=>'open'],
 ];
 $editor=['external_user_id'=>null,'capabilities'=>['viewOpportunities'=>true,'editOpportunities'=>true,'assignedDataOnly'=>false]];
 $reader=['external_user_id'=>null,'capabilities'=>['viewOpportunities'=>true,'editOpportunities'=>false,'assignedDataOnly'=>false]];
 $mine=['external_user_id'=>'seller-1','capabilities'=>['viewOpportunities'=>true,'editOpportunities'=>true,'assignedDataOnly'=>true]];
 $other=['external_user_id'=>'seller-2','capabilities'=>['viewOpportunities'=>true,'editOpportunities'=>true,'assignedDataOnly'=>true]];
 $actions=fn():OpportunityActions=>new OpportunityActions($api->gateway(),new PipelineDirectory($api->gateway()));

 // ---- creating ---------------------------------------------------------------
 openBudget($connectionId);query('DELETE FROM pipeline_cache WHERE connection_id=?',[$connectionId]);
 $result=$actions()->create($connection,'c1',$editor,['name'=>'  Proyecto nuevo  ','pipelineId'=>'pipe-1','monetaryValue'=>'2400,50','assignedTo'=>'seller-2','requestId'=>key16()]);
 $write=$api->writes()[0];
 actionCheck($write['method']==='POST'&&$write['path']==='/opportunities/'&&$write['version']==='2021-07-28','an opportunity is created through the documented endpoint and version');
 actionCheck($write['payload']===['locationId'=>$location,'contactId'=>'c1','pipelineId'=>'pipe-1','pipelineStageId'=>'stage-1','name'=>'Proyecto nuevo','status'=>'open','monetaryValue'=>2400.5,'assignedTo'=>'seller-2'],'every required field travels, the first stage is the default and the value is read as a number: '.json_encode($write['payload']));
 actionCheck($result['state']==='accepted'&&$result['id']==='new-opp','the new opportunity is reported with the identifier the account gave');

 $api->reset();openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'X','pipelineId'=>'pipe-nope','requestId'=>key16()]))?->reason==='unknown_pipeline','a pipeline the account does not have is refused');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'X','pipelineId'=>'pipe-1','pipelineStageId'=>'stage-nope','requestId'=>key16()]))?->reason==='unknown_stage','a stage outside that pipeline is refused');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'X','pipelineId'=>'pipe-1','assignedTo'=>'outsider','requestId'=>key16()]))?->reason==='unknown_assignee','a responsible outside the imported team is refused');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'  ','pipelineId'=>'pipe-1','requestId'=>key16()]))?->reason==='invalid_opportunity_name','an empty name is refused');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'X','pipelineId'=>'pipe-1','monetaryValue'=>'muchísimo','requestId'=>key16()]))?->reason==='invalid_opportunity_value','a value that is not a number is refused');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$reader,['name'=>'X','pipelineId'=>'pipe-1','requestId'=>key16()]))?->httpStatus===403,'a user without write permission cannot open an opportunity');
 actionCheck($api->writes()===[],'none of those refusals reached the account');

 $api->reset();openBudget($connectionId);
 $own=$actions()->create($connection,'c1',$mine,['name'=>'Mía','pipelineId'=>'pipe-1','requestId'=>key16()]);
 actionCheck(($api->writes()[0]['payload']['assignedTo']??null)==='seller-1','what a member limited to their own data opens is assigned to them, so they keep seeing it');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$mine,['name'=>'Mía','pipelineId'=>'pipe-1','assignedTo'=>'seller-2','requestId'=>key16()]))?->reason==='assignee_forbidden','and they cannot hand it to someone else');

 // ---- one attempt per key ----------------------------------------------------
 $api->reset();openBudget($connectionId);
 $repeat=key16();$payload=['name'=>'Única','pipelineId'=>'pipe-1','requestId'=>$repeat];
 $first=$actions()->create($connection,'c1',$editor,$payload);
 openBudget($connectionId);
 $second=$actions()->create($connection,'c1',$editor,$payload);
 actionCheck(count($api->writes())===1&&$second===$first,'the same key does not open a second opportunity: the first answer is returned');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'Otra','pipelineId'=>'pipe-1','requestId'=>$repeat]))?->reason==='request_conflict','and that key cannot be reused for different content');
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'Única','pipelineId'=>'pipe-1','requestId'=>'short']))?->reason==='invalid_request_key','a write without a usable key is refused');

 // ---- editing ----------------------------------------------------------------
 $api->reset();openBudget($connectionId);
 query('INSERT INTO opportunity_cache(connection_id,contact_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)',[$connectionId,'c1','[]']);
 query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)',[$connectionId,'board:abc','[]']);
 $actions()->update($connection,'opp-1',$editor,['name'=>'Proyecto Andreina','pipelineStageId'=>'stage-2','monetaryValue'=>1000,'assignedTo'=>'seller-2','requestId'=>key16()]);
 $edit=$api->writes()[0];
 actionCheck($api->paths()[0]==='/opportunities/opp-1'&&$api->calls[0]['method']==='GET','the opportunity is read before it is written, so another account cannot be touched');
 actionCheck($edit['method']==='PUT'&&$edit['path']==='/opportunities/opp-1'&&$edit['payload']===['pipelineStageId'=>'stage-2','assignedTo'=>'seller-2'],'only what changed is sent: unchanged name and value stay out: '.json_encode($edit['payload']));
 actionCheck((int)query('SELECT COUNT(*) FROM opportunity_cache WHERE connection_id=? AND contact_id=?',[$connectionId,'c1'])->fetchColumn()===0
   &&(int)query('SELECT COUNT(*) FROM directory_cache WHERE connection_id=? AND cache_key LIKE ?',[$connectionId,'board:%'])->fetchColumn()===0,'what was stored for that contact and for the board is dropped, so both screens show the new state');

 $api->reset();openBudget($connectionId);
 openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->update($connection,'opp-1',$editor,['name'=>'Proyecto Andreina','requestId'=>key16()]))?->reason==='nothing_to_change','an edit that changes nothing is refused instead of being sent');
 openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->update($connection,'opp-foreign',$editor,['name'=>'Robada','requestId'=>key16()]))?->httpStatus===404,'an opportunity of another account does not exist for this connection');
 openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->update($connection,'opp-missing',$editor,['name'=>'X','requestId'=>key16()]))?->reason==='unknown_opportunity','an unknown identifier is refused');
 openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->update($connection,'opp-1',$other,['name'=>'Ajena','requestId'=>key16()]))?->reason==='opportunity_not_assigned','a member limited to their own data cannot edit an opportunity that is not theirs');
 actionCheck($api->writes()===[],'none of those refusals reached the account');

 // ---- status ------------------------------------------------------------------
 $api->reset();openBudget($connectionId);
 $actions()->status($connection,'opp-1',$editor,['status'=>'won','requestId'=>key16()]);
 $status=$api->writes()[0];
 actionCheck($status['method']==='PUT'&&$status['path']==='/opportunities/opp-1/status'&&$status['payload']===['status'=>'won'],'the status travels through its own published endpoint');
 openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->status($connection,'opp-1',$editor,['status'=>'open','requestId'=>key16()]))?->reason==='nothing_to_change','setting the status it already has is refused');
 openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->status($connection,'opp-1',$editor,['status'=>'archivada','requestId'=>key16()]))?->reason==='invalid_status','a status outside the published list is refused');

 // ---- upstream refusals --------------------------------------------------------
 $api->reset();openBudget($connectionId);
 $api->fail=['/opportunities/'=>422];
 $rejected=key16();
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'Rechazada','pipelineId'=>'pipe-1','requestId'=>$rejected]))?->reason==='action_rejected','a refusal from the account is reported as such');
 actionCheck(query('SELECT state FROM opportunity_action_attempts WHERE connection_id=? AND request_id=?',[$connectionId,$rejected])->fetchColumn()==='rejected','and the attempt is recorded as rejected, not as done');
 openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'Rechazada','pipelineId'=>'pipe-1','requestId'=>$rejected]))?->reason==='action_rejected','repeating a rejected key is refused until the data changes');
 $api->fail=[];

 $api->reset();openBudget($connectionId);
 $api->fail=['/opportunities/'=>500];
 $uncertain=key16();
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'Incierta','pipelineId'=>'pipe-1','requestId'=>$uncertain]))?->reason==='action_unconfirmed','an uncertain result is never reported as done');
 actionCheck(query('SELECT state FROM opportunity_action_attempts WHERE connection_id=? AND request_id=?',[$connectionId,$uncertain])->fetchColumn()==='unknown','and it stays recorded as unconfirmed');
 $api->fail=[];openBudget($connectionId);
 actionCheck(problem(fn()=>$actions()->create($connection,'c1',$editor,['name'=>'Incierta','pipelineId'=>'pipe-1','requestId'=>key16()]))?->reason==='action_unconfirmed','identical content under a new key is blocked while the first result is unknown');

 echo 'PASS '.$checks.' opportunity action checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
