<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/AccessChannel.php';
require __DIR__.'/../src/ExternalAuth.php';
require __DIR__.'/../src/Actor.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function accessCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

/** Simulated API client: records every call and replays scripted responses. */
final class FakeApi {
    public array $calls=[];
    public array $contacts=[];
    public array $fail=[];
    public int $sent=0;
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=[$method,$path,$version,$payload];
            foreach($this->fail as $match=>$status)if(str_starts_with($path,$match))throw new GhlApiException($status);
            if(str_starts_with($path,'/contacts/search/duplicate')) {
                parse_str((string)parse_url($path,PHP_URL_QUERY),$q);
                $found=$this->contacts[$q['email']??'']??null;
                return ['status'=>200,'body'=>json_encode($found?['contact'=>['id'=>$found,'locationId'=>$this->location,'email'=>$q['email']]]:['contact'=>null])];
            }
            if($path==='/contacts/upsert') {
                $email=(string)($payload['email']??'');
                $new=!isset($this->contacts[$email]);
                $this->contacts[$email]??='contact-'.count($this->contacts);
                return ['status'=>200,'body'=>json_encode(['new'=>$new,'contact'=>['id'=>$this->contacts[$email],'locationId'=>$this->location,'email'=>$email]])];
            }
            if(preg_match('~^/contacts/([^/]+)/tags$~',$path,$m))return ['status'=>200,'body'=>json_encode(['tags'=>$payload['tags']??[]])];
            if($path==='/conversations/messages'){$this->sent++;return ['status'=>200,'body'=>json_encode(['messageId'=>'message-'.$this->sent,'conversationId'=>'conv-1'])];}
            throw new GhlApiException(404);
        }));
    }
    public function sentPayloads():array {return array_values(array_filter($this->calls,fn($c)=>$c[1]==='/conversations/messages'));}
    public function reset():void {$this->calls=[];}
}

function readyConnection(int $tenant,string $suffix,string $label):array {
    query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,$label,'loc-'.$suffix,'not-used']);
    return query('SELECT * FROM ghl_connections WHERE id=?',[(int)db()->lastInsertId()])->fetch();
}
function clearPause(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0 WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Access test','access-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Access other','other-'.$suffix]);$tenants[]=$other=(int)db()->lastInsertId();
 $connection=readyConnection($tenant,'a-'.$suffix,'Canal principal');
 $foreign=readyConnection($other,'b-'.$suffix,'Canal ajeno');
 $api=new FakeApi($connection['location_id']);

 // ---- channel selection and tenant isolation -------------------------------
 accessCheck(!AccessChannel::configured($tenant),'no access channel is configured by default');
 $unconfigured=false;
 try{(new AccessChannel($api->gateway()))->sendAccessCode($tenant,'team@example.test','123456');}
 catch(AccessDeliveryProblem $e){$unconfigured=$e->reason==='access_channel_unconfigured';}
 accessCheck($unconfigured&&$api->calls===[],'delivery without a selected channel fails before any request');

 $isolated=false;
 try{AccessChannel::select($tenant,(int)$foreign['id']);}catch(InvalidArgumentException $e){$isolated=true;}
 accessCheck($isolated,'a connection of another account cannot become this access channel');

 AccessChannel::select($tenant,(int)$connection['id']);
 accessCheck(AccessChannel::configured($tenant)&&(int)AccessChannel::connection($tenant)['id']===(int)$connection['id'],'administrator selects a connected account as access channel');

 query("UPDATE ghl_connections SET status='untested' WHERE id=?",[$connection['id']]);
 $unverified=false;
 try{(new AccessChannel($api->gateway()))->sendAccessCode($tenant,'team@example.test','123456');}
 catch(AccessDeliveryProblem $e){$unverified=$e->reason==='access_channel_unverified';}
 accessCheck($unverified,'an unverified channel is refused with its own reason');
 query("UPDATE ghl_connections SET status='connected' WHERE id=?",[$connection['id']]);

 // ---- contact creation, tag and documented contract ------------------------
 $email='member-'.$suffix.'@example.test';
 (new AccessChannel($api->gateway()))->sendAccessCode($tenant,$email,'123456');
 $paths=array_map(fn($c)=>$c[0].' '.explode('?',$c[1])[0],$api->calls);
 accessCheck($paths===['GET /contacts/search/duplicate','POST /contacts/upsert','POST /contacts/'.$api->contacts[$email].'/tags','POST /conversations/messages'],'missing contact is searched, created, tagged and then messaged');
 accessCheck($api->calls[0][2]==='2021-07-28'&&$api->calls[1][2]==='2021-07-28'&&$api->calls[3][2]==='2021-04-15','documented API versions per endpoint');
 accessCheck($api->calls[2][3]['tags']===[AccessChannel::INTERNAL_TAG],'internal access tag applied to the resolved contact');
 $send=$api->sentPayloads()[0][3];
 accessCheck($send['type']==='Email'&&$send['contactId']===$api->contacts[$email]&&!isset($send['emailTo']),'email addressed to the resolved contact, never to a raw recipient');
 accessCheck(str_contains($send['subject'],'Ágora')&&str_contains($send['html'],'123456')&&str_contains($send['html'],'ÁGORA DIGITAL'),'Spanish Ágora subject and template carry the code');
 $leak=strtolower($send['html'].$send['subject'].$send['message']);
 accessCheck(!str_contains($leak,'highlevel')&&!str_contains($leak,'ghl')&&!str_contains($leak,'leadconnector'),'the message never names the underlying provider');
 $stored=query('SELECT * FROM access_contacts WHERE connection_id=? AND email=?',[$connection['id'],$email])->fetch();
 accessCheck($stored['contact_id']===$api->contacts[$email]&&(int)$stored['created_by_agora']===1&&(int)$stored['tagged']===1&&$stored['last_delivery_at']!==null,'relation between member, connection and contact persisted');

 // ---- reuse: no duplicate contact on the next delivery ---------------------
 clearPause((int)$connection['id']);$api->reset();
 (new AccessChannel($api->gateway()))->sendAccessCode($tenant,strtoupper($email),'654321');
 accessCheck(array_map(fn($c)=>$c[1],$api->calls)===['/conversations/messages'],'a known member reuses the stored contact without searching or creating again');
 accessCheck(count($api->contacts)===1&&(int)query('SELECT COUNT(*) FROM access_contacts WHERE connection_id=?',[$connection['id']])->fetchColumn()===1,'repeated delivery creates no duplicate contact');

 // ---- an address already present in the account is reused, not recreated ---
 clearPause((int)$connection['id']);$api->reset();
 $existing='already-'.$suffix.'@example.test';$api->contacts[$existing]='crm-existing';
 (new AccessChannel($api->gateway()))->sendAccessCode($tenant,$existing,'111111');
 accessCheck(array_map(fn($c)=>explode('?',$c[1])[0],$api->calls)===['/contacts/search/duplicate','/contacts/crm-existing/tags','/conversations/messages'],'an existing CRM contact is reused instead of created');
 accessCheck(query('SELECT contact_id FROM access_contacts WHERE connection_id=? AND email=?',[$connection['id'],$existing])->fetchColumn()==='crm-existing','reused contact recorded for the member');

 // ---- shared address protection -------------------------------------------
 clearPause((int)$connection['id']);$api->reset();
 $shared='shared-'.$suffix.'@example.test';$api->contacts[$shared]='crm-existing';
 $sharedRefused=false;
 try{(new AccessChannel($api->gateway()))->sendAccessCode($tenant,$shared,'222222');}
 catch(AccessDeliveryProblem $e){$sharedRefused=$e->reason==='shared_access_contact';}
 accessCheck($sharedRefused&&$api->sentPayloads()===[],'a contact already owned by another member refuses delivery');

 // ---- permissions and channel failures ------------------------------------
 clearPause((int)$connection['id']);$api->reset();$api->fail=['/contacts/search/duplicate'=>403];
 query('UPDATE ghl_sync_state SET enabled=TRUE WHERE connection_id=?',[$connection['id']]);
 $forbidden=null;
 try{(new AccessChannel($api->gateway()))->sendAccessCode($tenant,'denied-'.$suffix.'@example.test','333333');}
 catch(AccessDeliveryProblem $e){$forbidden=$e->reason;}
 accessCheck($forbidden==='insufficient_permissions','a rejected credential reports insufficient permissions');
 accessCheck((int)query('SELECT enabled FROM ghl_sync_state WHERE connection_id=?',[$connection['id']])->fetchColumn()===1,'a forbidden access probe does not disable scheduled inbox queries');
 accessCheck(query('SELECT status FROM ghl_connections WHERE id=?',[$connection['id']])->fetchColumn()==='connected','the connection stays usable for conversations');

 clearPause((int)$connection['id']);$api->reset();$api->fail=['/conversations/messages'=>422];
 $rejected=null;
 try{(new AccessChannel($api->gateway()))->sendAccessCode($tenant,$email,'444444');}
 catch(AccessDeliveryProblem $e){$rejected=$e->reason;}
 accessCheck($rejected==='email_channel_unavailable','a refused email send reports an unavailable email channel');

 clearPause((int)$connection['id']);$api->reset();$api->fail=['/conversations/messages'=>404];
 try{(new AccessChannel($api->gateway()))->sendAccessCode($tenant,$email,'555555');}catch(AccessDeliveryProblem $e){}
 accessCheck((int)query('SELECT COUNT(*) FROM access_contacts WHERE connection_id=? AND email=?',[$connection['id'],$email])->fetchColumn()===0,'a contact missing upstream clears the stored relation for a fresh attempt');
 $api->fail=[];

 // ---- an address without a mailbox ----------------------------------------
 $noEmail=null;
 try{(new AccessChannel($api->gateway()))->sendAccessCode($tenant,'   ','666666');}
 catch(AccessDeliveryProblem $e){$noEmail=$e->reason;}
 accessCheck($noEmail==='member_without_email','a member without a synchronized address reports its own reason');

 // ---- end-to-end login through the channel --------------------------------
 clearPause((int)$connection['id']);$api->reset();
 $member='login-'.$suffix.'@example.test';
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',[$connection['id'],'user-'.$suffix,$member,'Integrante',json_encode(['conversationsEnabled'=>true]),json_encode(['conversations.readonly']),0]);
 $channel=new AccessChannel($api->gateway());
 $auth=new ExternalAuth(fn(int $t,string $to,string $code)=>$channel->sendAccessCode($t,$to,$code));
 $auth->request($member,'127.0.0.9');
 $delivered=$api->sentPayloads();
 accessCheck(count($delivered)===1,'team login request delivers exactly one code through the channel');
 preg_match('/\b(\d{6})\b/',$delivered[0][3]['html'],$code);
 accessCheck(ExternalAuth::configured($tenant),'the team sees the access channel as available');
 $identity=$auth->verify($member,$code[1],'127.0.0.9');
 accessCheck($identity>0&&Actor::fromSession(['external_identity_id'=>$identity])['identity_type']==='external','the delivered code grants the synchronized identity');
 $reused=false;
 try{$auth->verify($member,$code[1],'127.0.0.9');}catch(InvalidArgumentException $e){$reused=true;}
 accessCheck($reused,'a used code cannot be replayed');

 // ---- expired code ---------------------------------------------------------
 clearPause((int)$connection['id']);
 $auth->request($member,'127.0.0.9');
 query('UPDATE external_login_challenges SET expires_at=UNIX_TIMESTAMP()-1 WHERE identity_id=(SELECT id FROM external_identities WHERE tenant_id=? AND email=?) AND consumed_at IS NULL',[$tenant,$member]);
 preg_match('/\b(\d{6})\b/',$api->sentPayloads()[1][3]['html'],$stale);
 $expired=false;
 try{$auth->verify($member,$stale[1],'127.0.0.9');}catch(InvalidArgumentException $e){$expired=true;}
 accessCheck($expired,'an expired code is rejected');

 // ---- an undelivered code never stays valid -------------------------------
 query('DELETE FROM login_attempts WHERE key_hash IN (?,?,?)',[ExternalAuth::key('127.0.0.9|external-request|'.$member),ExternalAuth::key('email|external-request|'.$member),ExternalAuth::key('origin|external-request|127.0.0.9')]);
 clearPause((int)$connection['id']);$api->fail=['/conversations/messages'=>422];
 $failed=false;
 try{$auth->request($member,'127.0.0.9');}catch(AccessDeliveryProblem $e){$failed=true;}
 accessCheck($failed&&(int)query('SELECT COUNT(*) FROM external_login_challenges ch JOIN external_identities i ON i.id=ch.identity_id WHERE i.tenant_id=? AND i.email=? AND ch.consumed_at IS NULL',[$tenant,$member])->fetchColumn()===0,'an undelivered code is discarded instead of left usable');
 $api->fail=[];

 // ---- rate limits by address and by origin --------------------------------
 $limited=false;
 for($i=0;$i<8&&!$limited;$i++){clearPause((int)$connection['id']);try{$auth->request($member,'127.0.0.9');}catch(OverflowException $e){$limited=true;}catch(Throwable $e){}}
 accessCheck($limited,'repeated requests for one address hit the delivery limit');
 $byAddress=false;
 for($i=0;$i<30&&!$byAddress;$i++){clearPause((int)$connection['id']);try{$auth->request('rotating-'.$i.'-'.$suffix.'@example.test','127.0.0.9');}catch(OverflowException $e){$byAddress=true;}catch(Throwable $e){}}
 accessCheck($byAddress,'rotating addresses from one origin hit the origin limit');

 echo 'PASS '.$checks.' access channel checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM external_identities WHERE tenant_id=?',[$id]);query('DELETE FROM access_channels WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
 foreach(['request','verify'] as $action)
  foreach(['127.0.0.9|external-'.$action.'|','email|external-'.$action.'|'] as $prefix)
   foreach(array_merge([$member??'',$email??''],array_map(fn($i)=>'rotating-'.$i.'-'.$suffix.'@example.test',range(0,29))) as $address)
    query('DELETE FROM login_attempts WHERE key_hash=?',[ExternalAuth::key($prefix.$address)]);
 foreach(['request','verify'] as $action)query('DELETE FROM login_attempts WHERE key_hash=?',[ExternalAuth::key('origin|external-'.$action.'|127.0.0.9')]);
}
