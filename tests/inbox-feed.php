<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/InboxFeed.php';
require __DIR__.'/../src/Actor.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function feedCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

final class FakeInboxApi {
    public array $calls=[];
    public array $conversations=[];
    public int $total=0;
    public array $fail=[];
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=[$method,$path,$version,$payload];
            foreach($this->fail as $m=>$status)if(str_contains($path,$m))throw new GhlApiException($status);
            if(str_starts_with($path,'/conversations/search')) {
                parse_str((string)parse_url($path,PHP_URL_QUERY),$q);
                $items=$this->conversations;
                if(isset($q['status'])&&$q['status']==='unread')$items=array_values(array_filter($items,fn($c)=>($c['unreadCount']??0)>0));
                if(isset($q['lastMessageType']))$items=array_values(array_filter($items,fn($c)=>($c['lastMessageType']??null)===$q['lastMessageType']));
                if(isset($q['assignedTo']))$items=array_values(array_filter($items,fn($c)=>($c['assignedTo']??null)===$q['assignedTo']));
                if(isset($q['query']))$items=array_values(array_filter($items,fn($c)=>str_contains(mb_strtolower($c['contactName']??''),mb_strtolower($q['query']))));
                // The account continues after the position of the last conversation shown.
                if(isset($q['startAfterDate']))$items=array_values(array_filter($items,fn($c)=>(int)($c['lastMessageDate']??0)<(int)$q['startAfterDate']));
                $items=array_slice($items,0,(int)($q['limit']??20));
                return ['status'=>200,'body'=>json_encode(['conversations'=>$items,'total'=>$this->total?:count($items)])];
            }
            if($method==='PUT'&&preg_match('~^/conversations/[^/]+$~',$path))return ['status'=>200,'body'=>json_encode(['conversation'=>['id'=>'c1']])];
            throw new GhlApiException(404);
        }));
    }
    public function reset():void {$this->calls=[];}
    public function query():array {parse_str((string)parse_url($this->calls[0][1],PHP_URL_QUERY),$q);return $q;}
}
function freeFeed(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Inbox feed','feed-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,?)',
  [$connectionId,'seller-1','ana-'.$suffix.'@example.test','Ana',json_encode(['conversationsEnabled'=>true]),json_encode(['conversations.readonly']),0]);

 $api=new FakeInboxApi($location);
 $api->conversations=[
  ['id'=>'c1','locationId'=>$location,'contactName'=>'Richard Perez','lastMessageType'=>'TYPE_INSTAGRAM','unreadCount'=>2,'assignedTo'=>'seller-1','contactId'=>'ct1','lastMessageDate'=>'1730000300000'],
  ['id'=>'c2','locationId'=>$location,'contactName'=>'Raissa Moreno','lastMessageType'=>'TYPE_WHATSAPP','unreadCount'=>0,'assignedTo'=>'seller-2','contactId'=>'ct2','lastMessageDate'=>'1730000200000'],
  ['id'=>'c3','locationId'=>$location,'contactName'=>'Luis Robles','lastMessageType'=>'TYPE_WHATSAPP','unreadCount'=>5,'assignedTo'=>'seller-2','contactId'=>'ct3','lastMessageDate'=>'1730000100000'],
 ];
 $api->total=12;
 $open=['external_user_id'=>null,'capabilities'=>['viewConversations'=>true,'assignedDataOnly'=>false]];
 $mine=['external_user_id'=>'seller-1','capabilities'=>['viewConversations'=>true,'assignedDataOnly'=>false]];
 $feed=new InboxFeed($api->gateway());

 // ---- every load asks the account -------------------------------------------
 $first=$feed->load($connection,20,[],$open);
 feedCheck(count($api->calls)===1&&str_starts_with($api->calls[0][1],'/conversations/search'),'the inbox is read from the account, not from a stored copy');
 feedCheck($first['stale']===false&&$first['syncedAt']!==null,'a fresh list is reported as current');
 feedCheck(array_column($first['items'],'id')===['c1','c2','c3']&&$first['total']===12&&$first['partial']===true,'the whole window is returned with the account total');
 feedCheck($first['unread']===7,'the unread count adds every conversation of the list');

 $api->reset();freeFeed($connectionId);
 $feed->load($connection,20,[],$open);
 feedCheck(count($api->calls)===1,'a second load asks again instead of serving what it already had');

 // ---- filters travel to the account -----------------------------------------
 $api->reset();freeFeed($connectionId);
 ['filters'=>$unread]=InboxFeed::readFilters(['status'=>'unread'],$open);
 $result=$feed->load($connection,20,$unread,$open);
 feedCheck($api->query()['status']==='unread','the unread filter is applied by the account');
 feedCheck(array_column($result['items'],'id')===['c1','c3'],'only unread conversations come back');

 $api->reset();freeFeed($connectionId);
 ['filters'=>$channel]=InboxFeed::readFilters(['channel'=>'TYPE_WHATSAPP'],$open);
 $byChannel=$feed->load($connection,20,$channel,$open);
 feedCheck($api->query()['lastMessageType']==='TYPE_WHATSAPP'&&array_column($byChannel['items'],'id')===['c2','c3'],'a channel filter reaches the account');

 $api->reset();freeFeed($connectionId);
 ['filters'=>$onlyMine]=InboxFeed::readFilters(['mine'=>'1'],$mine);
 $assigned=$feed->load($connection,20,$onlyMine,$mine);
 feedCheck($api->query()['assignedTo']==='seller-1'&&array_column($assigned['items'],'id')===['c1'],'mine asks the account for the member own conversations');
 ['filters'=>$noIdentity]=InboxFeed::readFilters(['mine'=>'1'],$open);
 feedCheck(!isset($noIdentity['assignedTo']),'an administrator without an imported identity cannot filter by mine');

 $api->reset();freeFeed($connectionId);
 ['filters'=>$dirty]=InboxFeed::readFilters(['query'=>'  Raissa <script>  '],$open);
 feedCheck($dirty['query']==='Raissa script','the search term is stripped of anything that is not a plain word');
 ['filters'=>$search]=InboxFeed::readFilters(['query'=>'  Raissa  '],$open);
 $found=$feed->load($connection,20,$search,$open);
 feedCheck($api->query()['query']==='Raissa'&&array_column($found['items'],'id')===['c2'],'the search is answered by the account, not by the loaded window');
 ['filters'=>$bogus]=InboxFeed::readFilters(['status'=>'drop table','channel'=>'TYPE_FAKE','direction'=>'sideways'],$open);
 feedCheck($bogus===[],'an invented filter never reaches the account');

 // ---- an open conversation stays reachable whatever the filter --------------
 feedCheck(InboxFeed::known($connectionId,'c3')['contactId']==='ct3','every conversation seen is remembered for authorization');
 $api->reset();freeFeed($connectionId);
 $feed->load($connection,20,['status'=>'unread','lastMessageType'=>'TYPE_INSTAGRAM'],$open);
 feedCheck(InboxFeed::known($connectionId,'c2')!==null,'a conversation outside the current filter is still authorized');
 feedCheck(InboxFeed::known($connectionId,'de-otra-cuenta')===null,'an unknown conversation is not authorized');

 // ---- the stored copy is a fallback, and says so ---------------------------
 $api->reset();freeFeed($connectionId);$api->fail=['/conversations/search'=>500];
 $stale=$feed->load($connection,20,[],$open);
 feedCheck($stale['stale']===true&&$stale['reason']==='stale'&&count($stale['items'])===3,'a failed read falls back to the last list and marks it as not current');
 feedCheck($stale['syncedAt']!==null&&$stale['stale']===true,'the fallback says when its list was taken instead of passing for current');
 $api->fail=[];

 query('UPDATE ghl_sync_state SET rate_window_start=UNIX_TIMESTAMP(),rate_window_count=1000 WHERE connection_id=?',[$connectionId]);
 $paused=$feed->load($connection,20,[],$open);
 feedCheck($paused['stale']===true&&$paused['reason']==='paused'&&$paused['retryAfter']>0,'a paused budget falls back and says how long to wait');
 freeFeed($connectionId);

 // ---- a filtered read must not replace the fallback list -------------------
 $api->reset();freeFeed($connectionId);
 $feed->load($connection,20,['status'=>'unread'],$open);
 $kept=json_decode(query('SELECT payload FROM ghl_inbox_snapshots WHERE connection_id=?',[$connectionId])->fetchColumn(),true);
 feedCheck(count($kept)===3,'a filtered read leaves the unfiltered fallback untouched');

 // ---- marking as read ------------------------------------------------------
 $api->reset();freeFeed($connectionId);
 $feed->markRead($connection,['id'=>'c1']);
 feedCheck($api->calls[0][0]==='PUT'&&$api->calls[0][1]==='/conversations/c1'&&$api->calls[0][2]==='2021-04-15','documented endpoint, method and version for marking read');
 feedCheck($api->calls[0][3]===['locationId'=>$location,'unreadCount'=>0],'the account is told the location and a zero unread count');
 feedCheck((int)(InboxFeed::known($connectionId,'c1')['unreadCount']??9)===0,'the remembered conversation reflects the new state');

 // ---- a bad response cannot pass for an inbox ------------------------------
 $api->reset();freeFeed($connectionId);
 $api->conversations=[['id'=>'intruso','locationId'=>'otra-cuenta']];
 $foreign=$feed->load($connection,20,[],$open);
 feedCheck($foreign['stale']===true,'a list naming another account is refused and the fallback is used');
 feedCheck(InboxFeed::known($connectionId,'intruso')===null,'a conversation of another account is never remembered');

 // ---- beyond the window ----------------------------------------------------
 $api->reset();freeFeed($connectionId);
 $api->conversations=[
  ['id'=>'p1','locationId'=>$location,'contactName'=>'Primera','lastMessageType'=>'TYPE_WHATSAPP','unreadCount'=>0,'contactId'=>'ct-p1','lastMessageDate'=>'1730000300000'],
  ['id'=>'p2','locationId'=>$location,'contactName'=>'Segunda','lastMessageType'=>'TYPE_WHATSAPP','unreadCount'=>0,'contactId'=>'ct-p2','lastMessageDate'=>'1730000200000'],
  ['id'=>'p3','locationId'=>$location,'contactName'=>'Tercera','lastMessageType'=>'TYPE_WHATSAPP','unreadCount'=>0,'contactId'=>'ct-p3','lastMessageDate'=>'1730000100000'],
 ];
 $api->total=3;
 $page=$feed->load($connection,20,[],$open);
 feedCheck($page['nextCursor']==='1730000100000'&&$page['hasMore']===false,'a page that fits the window hands back a position but offers no continuation');

 $api->reset();freeFeed($connectionId);
 $kept=json_decode(query('SELECT payload FROM ghl_inbox_snapshots WHERE connection_id=?',[$connectionId])->fetchColumn(),true);
 $continued=$feed->load($connection,20,[],$open,'1730000200000');
 feedCheck(str_contains($api->calls[0][1],'startAfterDate=1730000200000'),'a continuation asks the account to begin after the position it gave');
 feedCheck(array_column($continued['items'],'id')===['p3'],'and answers only what comes after it');
 feedCheck(json_decode(query('SELECT payload FROM ghl_inbox_snapshots WHERE connection_id=?',[$connectionId])->fetchColumn(),true)===$kept,'a continuation leaves the fallback list as the first page left it');
 feedCheck(InboxFeed::known($connectionId,'p3')!==null,'conversations from a later page can be opened like any other');

 $api->reset();freeFeed($connectionId);
 $stale=$feed->load($connection,20,[],$open,'1730000100000');
 feedCheck($stale['items']===[]&&$stale['hasMore']===false&&$stale['nextCursor']===null,'the end of the list offers nothing more to load');

 feedCheck(InboxFeed::sortValue('1730000100000')==='1730000100000'&&InboxFeed::sortValue(1730000100000)==='1730000100000','a position is accepted as the account gives it, number or text');
 feedCheck(InboxFeed::sortValue('2026-10-29T18:05:00Z')==='1793297100000','a date is turned into the same unit the account sorts by');
 feedCheck(InboxFeed::sortValue(null)===null&&InboxFeed::sortValue('mañana')===null&&InboxFeed::sortValue(0)===null,'anything unusable gives no position, so no continuation is offered');

 echo 'PASS '.$checks.' inbox feed checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
