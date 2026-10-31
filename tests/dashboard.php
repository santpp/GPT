<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Dashboard.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function boardCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

final class FakeBoardApi {
    public array $calls=[];
    public int $conversations=0;
    public int $unread=0;
    public int $contacts=0;
    public array $contactAnswer=[];
    public array $opportunities=[];
    public array $pipelines=[];
    public array $payloads=[];
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=$path;$this->payloads[]=$payload;
            if(str_starts_with($path,'/conversations/search'))
                return ['status'=>200,'body'=>json_encode(['conversations'=>[],'total'=>str_contains($path,'status=unread')?$this->unread:$this->conversations])];
            if(str_starts_with($path,'/contacts/search'))return ['status'=>200,'body'=>json_encode($this->contactAnswer)];
            if(str_starts_with($path,'/opportunities/pipelines'))return ['status'=>200,'body'=>json_encode(['pipelines'=>$this->pipelines])];
            if(str_starts_with($path,'/opportunities/search'))return ['status'=>200,'body'=>json_encode(['opportunities'=>$this->opportunities])];
            throw new GhlApiException(404);
        }));
    }
    public function reset():void {$this->calls=[];$this->payloads=[];}
    public function paths():array {return array_map(fn($c)=>explode('?',$c)[0],$this->calls);}
}
function boardFree(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Dashboard test','dash-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();

 $api=new FakeBoardApi($location);
 $api->conversations=47;$api->unread=5;$api->contacts=812;$api->contactAnswer=['total'=>812];
 $api->pipelines=[['id'=>'pipe-1','name'=>'Pipeline comercial','locationId'=>$location,'stages'=>[['id'=>'s1','name'=>'Nuevo lead'],['id'=>'s2','name'=>'Propuesta enviada']]]];
 $api->opportunities=[
  ['id'=>'o1','monetaryValue'=>2400,'pipelineId'=>'pipe-1','pipelineStageId'=>'s2','assignedTo'=>'seller-1','locationId'=>$location],
  ['id'=>'o2','monetaryValue'=>'1100.5','pipelineId'=>'pipe-1','pipelineStageId'=>'s1','assignedTo'=>'seller-2','locationId'=>$location],
  ['id'=>'o3','pipelineId'=>'pipe-1','pipelineStageId'=>'desconocida','assignedTo'=>'seller-1','locationId'=>$location],
  ['id'=>'o4','monetaryValue'=>900,'pipelineId'=>'pipe-1','pipelineStageId'=>'s1','locationId'=>'foreign'],
 ];
 $admin=['external_user_id'=>null,'capabilities'=>['viewContacts'=>true,'viewOpportunities'=>true,'assignedDataOnly'=>false]];
 $restricted=['external_user_id'=>'seller-1','capabilities'=>['viewContacts'=>true,'viewOpportunities'=>true,'assignedDataOnly'=>true]];

 boardFree($connectionId);
 query('DELETE FROM pipeline_cache WHERE connection_id=?',[$connectionId]);
 $board=new Dashboard($api->gateway(),new PipelineDirectory($api->gateway()));
 $view=$board->get($connection,$admin);
 boardCheck($api->paths()===['/conversations/search','/conversations/search','/contacts/search','/opportunities/search','/opportunities/pipelines','/contacts/search'],'the board reads conversations, contacts, opportunities, the pipeline names and today\'s leads');
 $todayAsk=$api->payloads[5]['filters'][0]??[];
 boardCheck(($todayAsk['field']??null)==='dateAdded'&&($todayAsk['operator']??null)==='range'&&preg_match('/T\d\d:00:00Z$/',$todayAsk['value']['gt']??'')===1,'today\'s leads are asked for by the date they came in');
 boardCheck($view['leads']['today']===812&&$view['leads']['mine']===null&&$view['leads']['linked']===false,'without a linked user there is no «assigned to me» figure rather than a wrong one');
 boardCheck((int)query('SELECT requests_today FROM ghl_sync_state WHERE connection_id=?',[$connectionId])->fetchColumn()===1,'the whole board costs a single budget slot');
 boardCheck(str_contains($api->calls[1],'status=unread')&&!str_contains($api->calls[0],'status='),'the pending figure is the account\'s unread total, not a window');
 boardCheck($view['conversations']===['total'=>47,'unread'=>5],'the account totals are reported as the account gave them');
 boardCheck($view['contacts']===812,'the contact figure comes from the total the account reports');

 // A count that merely echoes the page size is not a total, and must not pass for one.
 $api->reset();boardFree($connectionId);
 $api->contactAnswer=['contacts'=>[['id'=>'c1']],'count'=>1];
 boardCheck($board->get($connection,$admin)['contacts']===null,'a page-sized count is refused instead of being shown as the number of contacts');
 $api->reset();boardFree($connectionId);
 $api->contactAnswer=['meta'=>['total'=>1464]];
 boardCheck($board->get($connection,$admin)['contacts']===1464,'a total reported under meta is accepted too');
 $api->contactAnswer=['total'=>812];
 boardCheck($view['opportunities']['count']===3&&$view['opportunities']['value']===3500.5,'opportunities of another account stay out of the totals');
 boardCheck(array_column($view['opportunities']['stages'],'name')===['Pipeline comercial · Propuesta enviada','Pipeline comercial · Nuevo lead','Sin etapa'],'stages carry their real names, ordered by what is at stake');
 boardCheck($view['opportunities']['stages'][0]['value']===2400.0&&$view['opportunities']['stages'][2]['count']===1,'each stage adds its own value, and an unknown stage is shown apart');
 boardCheck($view['restricted']===false&&$view['opportunities']['sampled']===false,'a complete reading is not reported as a sample');

 $api->reset();boardFree($connectionId);
 $api->contactAnswer=['total'=>3];
 $linked=$board->get($connection,$admin+['self_id'=>'owner-ghl'],'Europe/Madrid');
 $mineAsk=end($api->payloads)['filters'][0]??[];
 boardCheck($linked['leads']===['today'=>3,'mine'=>3,'linked'=>true]&&$mineAsk===['field'=>'assignedTo','operator'=>'eq','value'=>'owner-ghl'],'with a linked user, the leads assigned to that person are counted too');
 $madrid=(new DateTimeImmutable('today',new DateTimeZone('Europe/Madrid')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
 $dated=array_values(array_filter($api->payloads,fn($p)=>is_array($p)&&(($p['filters'][0]['field']??null)==='dateAdded')));
 boardCheck(($dated[0]['filters'][0]['value']['gt']??null)===$madrid,'«today» starts at midnight where the person is, not at midnight UTC');
 $api->contactAnswer=['total'=>812];

 $api->reset();boardFree($connectionId);
 $mine=$board->get($connection,$restricted);
 boardCheck(str_contains($api->calls[0],'assignedTo=seller-1'),'a member restricted to their own data asks the account only for theirs');
 boardCheck($mine['opportunities']['count']===2&&$mine['restricted']===true,'their board counts only their own opportunities, and says so');
 boardCheck($mine['contacts']===null,'no contact figure is shown where the account cannot scope it to that member');
 $own=array_values(array_filter($api->payloads,fn($p)=>is_array($p)&&isset($p['filters'])));
 boardCheck(count($own)===1&&in_array(['field'=>'assignedTo','operator'=>'eq','value'=>'seller-1'],$own[0]['filters'],true),'a member who sees only their own gets their own leads of today');

 $api->reset();boardFree($connectionId);
 $reader=$board->get($connection,['external_user_id'=>null,'capabilities'=>['viewContacts'=>false,'viewOpportunities'=>false,'assignedDataOnly'=>false]]);
 boardCheck($reader['contacts']===null&&$reader['opportunities']===null&&$reader['conversations']['total']===47,'a user without those permissions sees only the conversations');
 boardCheck($api->paths()===['/conversations/search','/conversations/search'],'and nothing is read that the user may not see');

 $api->reset();boardFree($connectionId);
 $api->opportunities=['not-a-list'=>true];
 $invalid=false;
 try{$board->get($connection,$admin);}catch(RuntimeException $e){$invalid=true;}
 boardCheck($invalid,'an unexpected opportunity payload is rejected before reaching the screen');
 boardCheck(query('SELECT status FROM ghl_connections WHERE id=?',[$connectionId])->fetchColumn()==='connected','a failed board read does not disable the connection');

 echo 'PASS '.$checks.' dashboard checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
