<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/OpportunityContext.php';
require __DIR__.'/../src/Actor.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function opportunityCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

/** Simulated API client for the documented opportunity and pipeline reads. */
final class FakeOpportunityApi {
    public array $calls=[];
    public array $opportunities=[];
    public array $pipelines=[];
    public array $fail=[];
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=[$method,$path,$version];
            foreach($this->fail as $match=>$status)if(str_starts_with($path,$match))throw new GhlApiException($status);
            if(str_starts_with($path,'/opportunities/search'))return ['status'=>200,'body'=>json_encode(['opportunities'=>$this->opportunities])];
            if(str_starts_with($path,'/opportunities/pipelines'))return ['status'=>200,'body'=>json_encode(['pipelines'=>$this->pipelines])];
            throw new GhlApiException(404);
        }));
    }
    public function reset():void {$this->calls=[];}
    public function paths():array {return array_map(fn($c)=>explode('?',$c[1])[0],$this->calls);}
}
function resetPause(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0 WHERE connection_id=?',[$connection]);}
function freshPipelines(int $connection):void{query('DELETE FROM pipeline_cache WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Opportunity test','opp-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta','loc-'.$suffix,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',
  [$connectionId,'seller-1','ana-'.$suffix.'@example.test','Ana Responsable',json_encode(['conversationsEnabled'=>true,'opportunitiesEnabled'=>true,'assignedDataOnly'=>true]),json_encode(['conversations.readonly','opportunities.readonly']),1]);
 $assignedRow=query('SELECT * FROM external_users WHERE id=?',[(int)db()->lastInsertId()])->fetch();

 $api=new FakeOpportunityApi($location);
 $api->pipelines=[
  ['id'=>'pipe-1','name'=>'Pipeline comercial','locationId'=>$location,'stages'=>[['id'=>'stage-1','name'=>'Nuevo lead'],['id'=>'stage-2','name'=>'Propuesta enviada'],['id'=>'stage-bad','name'=>'   ']]],
  ['id'=>'pipe-other','name'=>'Pipeline de otra cuenta','locationId'=>'foreign','stages'=>[]],
  ['id'=>'pipe-unnamed','name'=>'','locationId'=>$location,'stages'=>[]],
 ];
 $api->opportunities=[
  ['id'=>'opp-open','name'=>'Proyecto de Lucía','monetaryValue'=>2400,'pipelineId'=>'pipe-1','pipelineStageId'=>'stage-2','assignedTo'=>'seller-1','status'=>'open','contactId'=>'contact-1','locationId'=>$location,'updatedAt'=>'2026-10-20T10:00:00Z','source'=>'Campaña'],
  ['id'=>'opp-won','name'=>'Proyecto anterior','monetaryValue'=>'900.50','pipelineId'=>'pipe-1','pipelineStageId'=>'stage-1','assignedTo'=>'seller-2','status'=>'won','contactId'=>'contact-1','locationId'=>$location,'updatedAt'=>'2026-10-28T10:00:00Z'],
  ['id'=>'opp-foreign','name'=>'De otra cuenta','pipelineId'=>'pipe-1','status'=>'open','contactId'=>'contact-1','locationId'=>'foreign','updatedAt'=>'2026-10-29T10:00:00Z'],
  ['id'=>'opp-other-contact','name'=>'De otro contacto','status'=>'open','contactId'=>'contact-2','locationId'=>$location,'updatedAt'=>'2026-10-29T11:00:00Z'],
  ['../bad'=>'not an object'],
 ];
 $admin=['external_user_id'=>null,'capabilities'=>['viewOpportunities'=>true,'assignedDataOnly'=>false],'assigned_data_only'=>false];

 // ---- documented contract --------------------------------------------------
 $context=new OpportunityContext($api->gateway(),new PipelineDirectory($api->gateway()));
 $result=$context->get($connection,'contact-1',$admin);
 opportunityCheck($api->paths()===['/opportunities/search','/opportunities/pipelines'],'documented opportunity and pipeline endpoints queried');
 opportunityCheck($api->calls[0][0]==='GET'&&$api->calls[0][2]==='2021-07-28'&&$api->calls[1][2]==='2021-07-28','documented method and API version');
 opportunityCheck(str_contains($api->calls[0][1],'location_id='.$location)&&str_contains($api->calls[0][1],'contact_id=contact-1')&&str_contains($api->calls[0][1],'status=all'),'the account and contact scope the search, never free input');

 // ---- isolation ------------------------------------------------------------
 opportunityCheck(array_column($result['items'],'id')===['opp-open','opp-won'],'records of another account, another contact or an invalid shape are discarded');

 // ---- real names, status, value and assignee ------------------------------
 $byId=array_column($result['items'],null,'id');
 opportunityCheck($byId['opp-open']['pipelineName']==='Pipeline comercial'&&$byId['opp-open']['stageName']==='Propuesta enviada','real pipeline and stage names resolved');
 opportunityCheck($byId['opp-open']['statusLabel']==='Abierta'&&$byId['opp-won']['statusLabel']==='Ganada','status presented in Spanish');
 opportunityCheck($byId['opp-open']['monetaryValue']===2400.0&&$byId['opp-won']['monetaryValue']===900.5,'monetary value normalized from number and numeric text');
 opportunityCheck($byId['opp-open']['assignedName']==='Ana Responsable'&&$byId['opp-won']['assignedName']===null,'responsible resolved from the imported team, unknown assignee left empty');
 $directory=json_decode(query('SELECT payload FROM pipeline_cache WHERE connection_id=?',[$connectionId])->fetchColumn(),true);
 opportunityCheck($byId['opp-won']['stageName']==='Nuevo lead'&&array_keys($directory['pipe-1']['stages'])===['stage-1','stage-2'],'a stage without a usable name is left out instead of invented');
 opportunityCheck(array_keys($directory)===['pipe-1'],'pipelines of another account or without a name are discarded');

 // ---- active opportunity ---------------------------------------------------
 opportunityCheck($result['active']['id']==='opp-open','the open opportunity is the active one even when another changed later');
 $api->reset();resetPause($connectionId);
 query('DELETE FROM opportunity_cache WHERE connection_id=?',[$connectionId]);
 $closedOnly=$api->opportunities;$closedOnly[0]['status']='lost';$closedOnly[0]['updatedAt']='2026-10-01T10:00:00Z';
 $previous=$api->opportunities;$api->opportunities=$closedOnly;
 $closed=$context->get($connection,'contact-1',$admin);
 opportunityCheck($closed['active']['id']==='opp-won','with no open opportunity the most recent record becomes active');
 $api->opportunities=$previous;

 // ---- caches ---------------------------------------------------------------
 $api->reset();resetPause($connectionId);
 query('DELETE FROM opportunity_cache WHERE connection_id=?',[$connectionId]);
 $context->get($connection,'contact-1',$admin);
 opportunityCheck($api->paths()===['/opportunities/search'],'pipeline names served from the one-hour directory cache');
 $api->reset();
 $context->get($connection,'contact-1',$admin);
 opportunityCheck($api->calls===[],'a fresh opportunity result is served from the shared cache');
 $api->reset();resetPause($connectionId);
 $context->get($connection,'contact-1',$admin,true);
 opportunityCheck($api->paths()===['/opportunities/search'],'explicit refresh re-reads opportunities without re-reading pipelines');

 // ---- assignedDataOnly -----------------------------------------------------
 $membership=$assignedRow;$membership['capabilities']=PermissionPolicy::capabilities($assignedRow+['identity_type'=>'external']);
 opportunityCheck($membership['capabilities']['viewOpportunities']&&$membership['capabilities']['assignedDataOnly'],'imported permissions grant opportunities with the assigned-only restriction');
 $api->reset();resetPause($connectionId);
 $restricted=$context->get($connection,'contact-1',$membership,true);
 opportunityCheck(array_column($restricted['items'],'id')===['opp-open']&&$restricted['restricted']===true&&$restricted['total']===1,'an assigned-only member sees only their own opportunities');
 opportunityCheck($restricted['active']['id']==='opp-open','the active opportunity respects the assigned-only restriction');

 $denied=$assignedRow;$denied['permissions']=json_encode(['conversationsEnabled'=>true,'opportunitiesEnabled'=>false]);
 opportunityCheck(PermissionPolicy::capabilities($denied+['identity_type'=>'external'])['viewOpportunities']===false,'a member without the opportunities module cannot consult them');
 $noScope=$assignedRow;$noScope['scopes']=json_encode(['conversations.readonly']);
 opportunityCheck(PermissionPolicy::capabilities($noScope+['identity_type'=>'external'])['viewOpportunities']===false,'a credential without the opportunities scope cannot consult them');

 // ---- empty state ----------------------------------------------------------
 $api->reset();resetPause($connectionId);$api->opportunities=[];
 $empty=$context->get($connection,'contact-1',$admin,true);
 opportunityCheck($empty['active']===null&&$empty['items']===[]&&$empty['total']===0&&$empty['partial']===false,'a contact without opportunities returns an explicit empty state');

 // ---- failures leak nothing and keep the cached result --------------------
 $api->opportunities=$previous;
 $api->reset();resetPause($connectionId);$context->get($connection,'contact-1',$admin,true);
 $api->reset();resetPause($connectionId);$api->fail=['/opportunities/search'=>403];
 $failed=null;
 try{$context->get($connection,'contact-1',$admin,true);}catch(GhlApiException $e){$failed=$e->status;}
 opportunityCheck($failed===403,'a rejected opportunity read surfaces as an upstream status, not as data');
 $cached=json_decode(query('SELECT payload FROM opportunity_cache WHERE connection_id=? AND contact_id=?',[$connectionId,'contact-1'])->fetchColumn(),true);
 opportunityCheck(count($cached)===2&&$cached[0]['id']==='opp-open','a failed read cannot replace the stored result');
 opportunityCheck(query('SELECT status FROM ghl_connections WHERE id=?',[$connectionId])->fetchColumn()==='connected','a forbidden opportunity read does not disable the connection');

 // ---- missing pipeline directory degrades without failing ----------------
 $api->fail=['/opportunities/pipelines'=>403];
 freshPipelines($connectionId);resetPause($connectionId);
 query('DELETE FROM opportunity_cache WHERE connection_id=?',[$connectionId]);
 $api->reset();
 $degraded=$context->get($connection,'contact-1',$admin);
 opportunityCheck($degraded['total']===2&&$degraded['pipelineNamesAvailable']===false&&$degraded['active']['pipelineName']===null&&$degraded['active']['stageName']===null,'without the pipeline directory no identifier is shown as a name');
 $api->fail=[];

 // ---- an unexpected payload cannot corrupt the panel ---------------------
 freshPipelines($connectionId);resetPause($connectionId);
 query('DELETE FROM opportunity_cache WHERE connection_id=?',[$connectionId]);
 $api->opportunities=['not-a-list'=>true];
 $invalid=false;
 try{$context->get($connection,'contact-1',$admin,true);}catch(RuntimeException $e){$invalid=true;}
 opportunityCheck($invalid&&(int)query('SELECT COUNT(*) FROM opportunity_cache WHERE connection_id=?',[$connectionId])->fetchColumn()===0,'an unexpected opportunity payload is rejected before reaching the panel');

 echo 'PASS '.$checks.' opportunity context checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
