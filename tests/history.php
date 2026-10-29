<?php
declare(strict_types=1);
require __DIR__.'/../src/MessageHistory.php';
require __DIR__.'/../src/GhlClient.php';
function check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejected(Closure $test):bool {try{$test();return false;}catch(RuntimeException|InvalidArgumentException $e){return true;}}
function reason(Closure $test):?string {try{$test();return null;}catch(HistoryFormatException $e){return $e->reason;}}
$m=['id'=>'m2','conversationId'=>'conv','locationId'=>'loc','body'=>'<script>untrusted</script>'];
$d=['messages'=>[$m],'nextPage'=>true,'lastMessageId'=>'m2'];
check(MessageHistory::page($d,'conv','loc','m3')['lastMessageId']==='m2','published cursor retained');

// Account scope stays strict: these must never be shown.
check(reason(fn()=>MessageHistory::page($d,'foreign','loc',null))==='conversation_mismatch','foreign conversation rejected with its reason');
check(reason(fn()=>MessageHistory::page($d,'conv','foreign',null))==='location_mismatch','foreign location rejected with its reason');

// A cursor that cannot advance stops pagination instead of discarding the page.
$repeated=MessageHistory::page($d,'conv','loc','m2');
check($repeated['items']===[$m]&&$repeated['hasMore']===false&&$repeated['lastMessageId']===null,'a repeated cursor stops pagination without losing the messages');
$unknown=MessageHistory::page(array_replace($d,['lastMessageId'=>'unknown']),'conv','loc',null);
check($unknown['items']===[$m]&&$unknown['hasMore']===false,'a cursor outside the page stops pagination without losing the messages');
$unsafe=MessageHistory::page(array_replace($d,['lastMessageId'=>'../escape']),'conv','loc',null);
check($unsafe['items']===[$m]&&$unsafe['hasMore']===false,'an unsafe cursor is ignored instead of breaking the history');

check(rejected(fn()=>MessageHistory::cursor(['unexpected'])),'array cursor rejected');
check(rejected(fn()=>MessageHistory::cursor('../escape')),'path cursor rejected');
check(MessageHistory::page(['messages'=>[],'nextPage'=>false],'conv','loc',null)['hasMore']===false,'empty last page accepted');
check(MessageHistory::page(['messages'=>[['id'=>'activity-without-envelope']],'nextPage'=>false],'conv','loc',null)['items'][0]['id']==='activity-without-envelope','real channel record without optional envelope accepted');

// A record that cannot be identified is skipped; the conversation still loads.
$mixed=MessageHistory::page(['messages'=>[['body'=>'sin id'],$m,'not-an-object',['id'=>'']],'nextPage'=>false],'conv','loc',null);
check(array_column($mixed['items'],'id')===['m2'],'records without a usable id are skipped instead of discarding the page');

// A page larger than requested is accepted; only an implausible one is refused.
$wide=MessageHistory::page(['messages'=>array_map(fn(int $i):array=>['id'=>'m'.$i],range(1,45)),'nextPage'=>false],'conv','loc',null);
check(count($wide['items'])===45,'a page larger than the requested window is accepted');
check(reason(fn()=>MessageHistory::page(['messages'=>array_map(fn(int $i):array=>['id'=>'m'.$i],range(1,101))],'conv','loc',null))==='page_too_large','an implausible page is refused with its reason');
check(reason(fn()=>MessageHistory::page(['messages'=>'no'],'conv','loc',null))==='messages_not_a_list'||reason(fn()=>MessageHistory::page(['messages'=>'no'],'conv','loc',null))==='messages_missing','a non-list message set is refused with its reason');
check(reason(fn()=>MessageHistory::page([],'conv','loc',null))==='messages_missing','a response without messages is refused with its reason');

$client=new GhlClient('test',function($path,$version){check($path==='/conversations/conv/messages?limit=20&lastMessageId=m2'&&$version==='2021-04-15','documented endpoint and pagination query');return ['status'=>200,'body'=>'{}'];});
$client->conversationMessages('conv','m2');
