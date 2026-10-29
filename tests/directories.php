<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ContactDirectory.php';
require __DIR__.'/../src/OpportunityBoard.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function dirCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

final class FakeListApi {
    public array $calls=[];
    public array $contacts=[];
    public array $opportunities=[];
    public array $pipelines=[];
    public array $fail=[];
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=[$method,$path,$version];
            foreach($this->fail as $m=>$status)if(str_starts_with($path,$m))throw new GhlApiException($status);
            if(str_starts_with($path,'/contacts/?'))return ['status'=>200,'body'=>json_encode(['contacts'=>$this->contacts,'count'=>count($this->contacts)])];
            if(str_starts_with($path,'/opportunities/pipelines'))return ['status'=>200,'body'=>json_encode(['pipelines'=>$this->pipelines])];
            if(str_starts_with($path,'/opportunities/search'))return ['status'=>200,'body'=>json_encode(['opportunities'=>$this->opportunities])];
            throw new GhlApiException(404);
        }));
    }
    public function reset():void {$this->calls=[];}
    public function paths():array {return array_map(fn($c)=>explode('?',$c[1])[0],$this->calls);}
}
function freeBudget(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Directory test','dir-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',
  [$connectionId,'seller-1','ana-'.$suffix.'@example.test','Ana Responsable','{}','[]',1]);

 $api=new FakeListApi($location);
 $api->contacts=[
  ['id'=>'c1','contactName'=>'Andreina Del Nogal','email'=>'a@example.test','phone'=>'+584142736522','assignedTo'=>'seller-1','locationId'=>$location,'tags'=>['Interesada']],
  ['id'=>'c2','firstName'=>'Carlos','lastName'=>'Rodríguez','email'=>'c@example.test','assignedTo'=>'seller-2','locationId'=>$location],
  ['id'=>'c3','locationId'=>'foreign','contactName'=>'De otra cuenta'],
  ['no-id'=>true],
 ];
 $api->pipelines=[
  ['id'=>'pipe-1','name'=>'Pipeline comercial','locationId'=>$location,'stages'=>[['id'=>'s1','name'=>'Nuevo lead'],['id'=>'s2','name'=>'Propuesta enviada']]],
  ['id'=>'pipe-2','name'=>'Postventa','locationId'=>$location,'stages'=>[['id'=>'s9','name'=>'Seguimiento']]],
 ];
 $api->opportunities=[
  ['id'=>'o1','name'=>'Proyecto Andreina','monetaryValue'=>2400,'pipelineId'=>'pipe-1','pipelineStageId'=>'s2','assignedTo'=>'seller-1','locationId'=>$location,'contactId'=>'c1'],
  ['id'=>'o2','name'=>'Proyecto Carlos','monetaryValue'=>'1100.5','pipelineId'=>'pipe-1','pipelineStageId'=>'s1','assignedTo'=>'seller-2','locationId'=>$location],
  ['id'=>'o3','name'=>'Sin etapa','pipelineId'=>'pipe-1','pipelineStageId'=>'desconocida','assignedTo'=>'seller-1','locationId'=>$location],
  ['id'=>'o4','name'=>'De otro pipeline','pipelineId'=>'pipe-2','pipelineStageId'=>'s9','locationId'=>$location],
  ['id'=>'o5','name'=>'De otra cuenta','pipelineId'=>'pipe-1','locationId'=>'foreign'],
 ];
 $admin=['external_user_id'=>null,'capabilities'=>['viewContacts'=>true,'viewOpportunities'=>true,'assignedDataOnly'=>false]];
 $restricted=['external_user_id'=>'seller-1','capabilities'=>['viewContacts'=>true,'viewOpportunities'=>true,'assignedDataOnly'=>true]];

 // ---- contacts --------------------------------------------------------------
 $directory=new ContactDirectory($api->gateway());
 $list=$directory->get($connection,null,$admin);
 dirCheck($api->paths()===['/contacts/']&&$api->calls[0][0]==='GET'&&$api->calls[0][2]==='2021-07-28','documented contact list endpoint and version');
 dirCheck(str_contains($api->calls[0][1],'locationId='.$location)&&str_contains($api->calls[0][1],'limit=50'),'the account scopes the list and the window is bounded');
 dirCheck(array_column($list['items'],'id')===['c1','c2'],'contacts of another account or without id are discarded');
 dirCheck($list['items'][0]['name']==='Andreina Del Nogal'&&$list['items'][1]['name']==='Carlos Rodríguez','names taken from the record or composed from its parts');
 dirCheck($list['items'][0]['assignedName']==='Ana Responsable'&&$list['items'][1]['assignedName']===null,'responsible resolved from the imported team');

 $api->reset();freeBudget($connectionId);
 $searched=$directory->get($connection,'  Andre<script>  ',$admin,true);
 dirCheck(str_contains($api->calls[0][1],'query=Andrescript')&&$searched['query']==='Andrescript','only a plain search term reaches the account');
 $api->reset();
 $directory->get($connection,'  Andre<script>  ',$admin);
 dirCheck($api->calls===[],'a repeated search is served from the short cache');

 $api->reset();freeBudget($connectionId);
 $mine=$directory->get($connection,null,$restricted,true);
 dirCheck(array_column($mine['items'],'id')===['c1']&&$mine['restricted']===true,'an assigned-only member sees only their contacts, and the screen says so');

 // ---- opportunity board ------------------------------------------------------
 $api->reset();freeBudget($connectionId);
 $board=new OpportunityBoard($api->gateway(),new PipelineDirectory($api->gateway()));
 $view=$board->get($connection,null,$admin);
 dirCheck($api->paths()===['/opportunities/pipelines','/opportunities/search'],'pipelines and opportunities read in one slot');
 dirCheck((int)query('SELECT requests_today FROM ghl_sync_state WHERE connection_id=?',[$connectionId])->fetchColumn()===1,'the board costs a single budget slot');
 dirCheck(str_contains($api->calls[1][1],'status=open')&&str_contains($api->calls[1][1],'limit=100'),'the board asks for the open opportunities of the account');
 dirCheck(array_column($view['pipelines'],'id')===['pipe-1','pipe-2']&&$view['pipelineId']==='pipe-1','every pipeline is offered and the first is selected by default');
 dirCheck(array_column($view['stages'],'name')===['Nuevo lead','Propuesta enviada','Sin etapa'],'real stage names, with an explicit column for an unknown stage');
 dirCheck(array_column($view['stages'][0]['items'],'id')===['o2']&&array_column($view['stages'][1]['items'],'id')===['o1'],'cards land in their real stage');
 dirCheck($view['stages'][1]['value']===2400.0&&$view['stages'][0]['value']===1100.5,'stage totals add the monetary value, including numeric text');
 dirCheck($view['total']===3,'opportunities of another pipeline or another account stay out');

 $api->reset();freeBudget($connectionId);
 $other=$board->get($connection,'pipe-2',$admin,true);
 dirCheck($other['pipelineId']==='pipe-2'&&array_column($other['stages'][0]['items'],'id')===['o4'],'another pipeline can be selected');
 $api->reset();freeBudget($connectionId);
 $unknown=$board->get($connection,'no-existe',$admin,true);
 dirCheck($unknown['pipelineId']==='pipe-1','an unknown pipeline falls back to the first instead of failing');

 $api->reset();freeBudget($connectionId);
 $mineBoard=$board->get($connection,'pipe-1',$restricted,true);
 dirCheck(array_column($mineBoard['stages'][1]['items'],'id')===['o1']&&$mineBoard['stages'][0]['items']===[]&&$mineBoard['restricted']===true,'an assigned-only member sees only their own cards');

 // ---- failures ---------------------------------------------------------------
 $api->reset();freeBudget($connectionId);
 query('DELETE FROM directory_cache WHERE connection_id=?',[$connectionId]);
 query('DELETE FROM pipeline_cache WHERE connection_id=?',[$connectionId]);
 $api->fail=['/opportunities/pipelines'=>403];
 $degraded=$board->get($connection,null,$admin,true);
 dirCheck($degraded['unavailable']==='pipelines'&&$degraded['stages']===[],'without the pipeline directory the board reports it instead of inventing columns');
 $api->fail=[];

 $api->reset();freeBudget($connectionId);
 query('DELETE FROM directory_cache WHERE connection_id=?',[$connectionId]);
 $api->contacts=['not-a-list'=>true];
 $invalid=false;
 try{$directory->get($connection,null,$admin,true);}catch(RuntimeException $e){$invalid=true;}
 dirCheck($invalid&&(int)query('SELECT COUNT(*) FROM directory_cache WHERE connection_id=? AND cache_key LIKE ?',[$connectionId,'contacts:%'])->fetchColumn()===0,'an unexpected contact payload is rejected before reaching the screen');
 dirCheck(query('SELECT status FROM ghl_connections WHERE id=?',[$connectionId])->fetchColumn()==='connected','a failed list read does not disable the connection');

 echo 'PASS '.$checks.' directory checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
