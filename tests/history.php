<?php
declare(strict_types=1);
require __DIR__.'/../src/MessageHistory.php';
require __DIR__.'/../src/GhlClient.php';
function check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejected(Closure $test):bool {try{$test();return false;}catch(RuntimeException|InvalidArgumentException $e){return true;}}
$m=['id'=>'m2','conversationId'=>'conv','locationId'=>'loc','body'=>'<script>untrusted</script>'];
$d=['messages'=>[$m],'nextPage'=>true,'lastMessageId'=>'m2'];
check(MessageHistory::page($d,'conv','loc','m3')['lastMessageId']==='m2','published cursor retained');
check(rejected(fn()=>MessageHistory::page($d,'foreign','loc',null)),'foreign conversation rejected');
check(rejected(fn()=>MessageHistory::page($d,'conv','foreign',null)),'foreign location rejected');
check(rejected(fn()=>MessageHistory::page($d,'conv','loc','m2')),'repeated cursor rejected');
check(rejected(fn()=>MessageHistory::page(array_replace($d,['lastMessageId'=>'unknown']),'conv','loc',null)),'cursor must belong to returned page');
check(rejected(fn()=>MessageHistory::cursor(['unexpected'])),'array cursor rejected');
check(rejected(fn()=>MessageHistory::cursor('../escape')),'path cursor rejected');
check(MessageHistory::page(['messages'=>[],'nextPage'=>false],'conv','loc',null)['hasMore']===false,'empty last page accepted');
check(MessageHistory::page(['messages'=>[['id'=>'activity-without-envelope']],'nextPage'=>false],'conv','loc',null)['items'][0]['id']==='activity-without-envelope','real channel record without optional envelope accepted');
$client=new GhlClient('test',function($path,$version){check($path==='/conversations/conv/messages?limit=20&lastMessageId=m2'&&$version==='2021-04-15','documented endpoint and pagination query');return ['status'=>200,'body'=>'{}'];});
$client->conversationMessages('conv','m2');
