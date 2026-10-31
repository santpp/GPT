<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/GhlClient.php';
$client=new GhlClient('tok',fn()=>['status'=>400,'headers'=>[],'body'=>json_encode(['message'=>['attachments must be supported by GoHighLevel'],'statusCode'=>400])]);
try{$client->sendMessage(['type'=>'WhatsApp']);$detail='none';}catch(GhlApiException $e){$detail=$e->detail;}
if($detail!=='attachments must be supported by la cuenta'){fwrite(STDERR,"FAIL reason: $detail\n");exit(1);}
echo "PASS the account's reason for a rejection is kept, without brand names\n";
