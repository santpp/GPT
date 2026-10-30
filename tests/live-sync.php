<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Pulse.php';

require __DIR__.'/../src/Actor.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function pulseCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

final class FakePulseApi {
    public array $calls=[];
    public array $conversations=[];
    public int $total=0;
    public array $fail=[];
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=$path;
            foreach($this->fail as $match=>$status)if(str_contains($path,$match))throw new GhlApiException($status);
            return ['status'=>200,'body'=>json_encode(['conversations'=>$this->conversations,'total'=>$this->total])];
        }));
    }
    public function reset():void {$this->calls=[];}
}
function pulseFree(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$connection]);}
function pulseDue(int $connection):void{query('UPDATE ghl_sync_state SET pulse_next_at=0 WHERE connection_id=?',[$connection]);pulseFree($connection);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Pulse test','pulse-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status,sync_interval_seconds) VALUES(?,?,?,?,'connected',?)",[$tenant,'Cuenta',$location,'not-used',10]);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',
  [$connectionId,'seller-1','ana-'.$suffix.'@example.test','Ana Responsable','{}','[]',1]);

 $api=new FakePulseApi($location);
 $conversation=fn(string $id,int $unread,string $date,string $body,?string $assigned=null)=>array_filter([
  'id'=>$id,'locationId'=>$location,'contactId'=>'c-'.$id,'contactName'=>'Contacto '.$id,'lastMessageBody'=>$body,
  'lastMessageType'=>'TYPE_WHATSAPP','lastMessageDate'=>$date,'unreadCount'=>$unread,'assignedTo'=>$assigned,
 ],fn($value)=>$value!==null);
 $api->conversations=[$conversation('a',0,'1730000000000','Hola','seller-1'),$conversation('b',0,'1729000000000','Qué tal')];
 $api->total=2;
 $admin=['external_user_id'=>null,'capabilities'=>['viewConversations'=>true,'assignedDataOnly'=>false]];
 $mine=['external_user_id'=>'seller-1','capabilities'=>['viewConversations'=>true,'assignedDataOnly'=>true]];

 // ---- the pulse reads the account once per interval, whoever asks ------------
 pulseDue($connectionId);
 $pulse=new Pulse($api->gateway());
 $first=$pulse->read($connection,$admin);
 pulseCheck(count($api->calls)===1&&str_contains($api->calls[0],'/conversations/search'),'the first pulse reads the account once');
 pulseCheck($first['refreshed']===true&&$first['unread']===0&&$first['reason']===null,'and reports what it found');
 pulseCheck((int)query('SELECT COUNT(*) FROM conversation_index WHERE connection_id=?',[$connectionId])->fetchColumn()===2,'every conversation it showed is recorded');

 $api->reset();
 $second=$pulse->read($connection,$admin);
 $third=$pulse->read($connection,$admin);
 pulseCheck($api->calls===[],'pulses inside the interval cost the account nothing');
 pulseCheck($second['token']===$first['token']&&$third['token']===$first['token'],'and answer the same token, so no screen reloads for nothing');
 pulseCheck($second['refreshed']===false,'and say they did not read the account');
 pulseCheck((int)query('SELECT requests_today FROM ghl_sync_state WHERE connection_id=?',[$connectionId])->fetchColumn()===1,'ten people looking cost what one costs');

 // ---- a refresh that finds nothing new must not change the token -------------
 pulseDue($connectionId);$api->reset();
 $same=$pulse->read($connection,$admin);
 pulseCheck(count($api->calls)===1&&$same['refreshed']===true&&$same['token']===$first['token'],'a pass that finds nothing new keeps the token unchanged');

 // ---- a real change moves it -------------------------------------------------
 pulseDue($connectionId);$api->reset();
 $api->conversations=[$conversation('a',2,'1730000500000','Nuevo mensaje','seller-1'),$conversation('b',0,'1729000000000','Qué tal')];
 $changed=$pulse->read($connection,$admin);
 pulseCheck($changed['token']!==$first['token'],'a new message changes the token');
 pulseCheck($changed['unread']===2,'and the pending count the menu shows');
 pulseCheck((int)query('SELECT COUNT(*) FROM push_queue WHERE connection_id=?',[$connectionId])->fetchColumn()===1,'and leaves one notice ready to be delivered');
 pulseCheck(query('SELECT body FROM push_queue WHERE connection_id=?',[$connectionId])->fetchColumn()==='Nuevo mensaje','with what the account showed as the preview');

 pulseDue($connectionId);$api->reset();
 $pulse->read($connection,$admin);
 pulseCheck((int)query('SELECT COUNT(*) FROM push_queue WHERE connection_id=?',[$connectionId])->fetchColumn()===1,'the same message is never queued twice');

 // ---- marking read is news for the other browsers ---------------------------
 $token=$pulse->read($connection,$admin,false)['token'];
 query('UPDATE conversation_index SET unread_count=0,content_hash=NULL,content_changed_at=CURRENT_TIMESTAMP(3) WHERE connection_id=? AND conversation_id=?',[$connectionId,'a']);
 $after=$pulse->read($connection,$admin,false)['token'];
 pulseCheck($after!==$token,'what one person marks as read reaches the others');

 // ---- an assigned-only member counts only their own -------------------------
 pulseDue($connectionId);$api->reset();
 $api->conversations=[$conversation('a',3,'1730001000000','Para Ana','seller-1'),$conversation('b',4,'1729000500000','Para otro','seller-2')];
 $pulse->read($connection,$admin);
 $own=$pulse->read($connection,$mine,false);
 $all=$pulse->read($connection,$admin,false);
 pulseCheck($own['unread']===3&&$all['unread']===7,'each member is told what is pending for them, not for the account');
 pulseCheck($own['token']!==$all['token'],'and their token is their own');

 // ---- failures never turn into a loop of requests ---------------------------
 pulseDue($connectionId);$api->reset();
 $api->fail=['/conversations/search'=>500];
 $failed=$pulse->read($connection,$admin);
 pulseCheck($failed['reason']==='failed'&&$failed['token']===$all['token'],'a failed read is reported without losing what was known');
 $api->reset();
 $pulse->read($connection,$admin);
 pulseCheck($api->calls===[],'and the next pulse waits the interval instead of retrying at once');
 $api->fail=[];

 pulseDue($connectionId);$api->reset();
 query('UPDATE ghl_sync_state SET rate_window_start=UNIX_TIMESTAMP(),rate_window_count=1000 WHERE connection_id=?',[$connectionId]);
 $paused=$pulse->read($connection,$admin);
 pulseCheck($api->calls===[]&&$paused['reason']==='paused'&&$paused['retryAfter']>0,'a paused budget is reported, with how long to wait');
 pulseFree($connectionId);

 // ---- an unverified connection is quiet -------------------------------------
 query("UPDATE ghl_connections SET status='untested' WHERE id=?",[$connectionId]);
 $pending=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 pulseDue($connectionId);$api->reset();
 $quiet=$pulse->read($pending,$admin);
 pulseCheck($api->calls===[]&&$quiet['refreshed']===false,'a connection nobody verified is never read');
 query("UPDATE ghl_connections SET status='connected' WHERE id=?",[$connectionId]);

 echo 'PASS '.$checks.' live sync checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
