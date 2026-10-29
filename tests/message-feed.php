<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/MessageFeed.php';
$tenant=null;$checks=0;$calls=0;$suffix=bin2hex(random_bytes(6));
function feedCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Feed test','feed-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;$vault=TokenVault::fromEnvironment();
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Feed fixture',$location,$vault->seal('fake',$tenant,$location)]);
 $id=(int)db()->lastInsertId();$connection=query('SELECT * FROM ghl_connections WHERE id=?',[$id])->fetch();
 $response=['messages'=>[['id'=>'m1','body'=>'Real channel record may omit envelope fields','direction'=>'inbound','dateAdded'=>'2026-10-29T10:00:00Z']],'nextPage'=>false,'lastMessageId'=>'m1'];
 $gateway=new GhlGateway(function($row)use(&$response,&$calls){return new GhlClient('fake',function($path,$version)use(&$response,&$calls){$calls++;return ['status'=>200,'body'=>json_encode($response)];});});
 $feed=new MessageFeed($gateway);
 $first=$feed->recent($connection,'conv');feedCheck(!$first['cached']&&$calls===1,'first reader fetches and caches recent messages');
 $second=$feed->recent($connection,'conv');feedCheck($second['cached']&&$calls===1&&$second['items'][0]['id']==='m1','second reader shares fresh server cache');
 query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$id]);
 $feed->recent($connection,'conv',true);feedCheck($calls===2,'explicit refresh bypasses freshness without changing scope');
 query('UPDATE conversation_message_cache SET fetched_at=DATE_SUB(NOW(),INTERVAL 20 SECOND) WHERE connection_id=? AND conversation_id=?',[$id,'conv']);
 query('UPDATE ghl_sync_state SET request_not_before=0 WHERE connection_id=?',[$id]);
 $response=['messages'=>[['id'=>'m2','conversationId'=>'foreign','locationId'=>$location]],'nextPage'=>false,'lastMessageId'=>'m2'];
 $rejected=false;try{$feed->recent($connection,'conv');}catch(RuntimeException $e){$rejected=true;}
 feedCheck($rejected&&query('SELECT JSON_UNQUOTE(JSON_EXTRACT(payload,\'$[0].id\')) FROM conversation_message_cache WHERE connection_id=? AND conversation_id=?',[$id,'conv'])->fetchColumn()==='m1','mismatched upstream page cannot replace cache');
 echo 'PASS '.$checks.' message feed checks'.PHP_EOL;
}finally{if($tenant){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}}
