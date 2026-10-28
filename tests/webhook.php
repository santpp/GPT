<?php
declare(strict_types=1);
require __DIR__.'/../src/Webhook.php';
$key=openssl_pkey_new(['private_key_bits'=>2048]);
$public=openssl_pkey_get_details($key)['key'];
$now=time();
$raw=json_encode(['timestamp'=>gmdate('c',$now),'webhookId'=>'test-1','locationId'=>'loc-1','type'=>'InboundMessage']);
openssl_sign($raw,$signature,$key,OPENSSL_ALGO_SHA256);
if (Webhook::verify($raw,base64_encode($signature),$public,$now)['webhookId']!=='test-1') throw new Exception('Valid signature rejected');
$rejected=0;
foreach ([[$raw.' ',base64_encode($signature),$now],[$raw,'invalid',$now],[$raw,base64_encode($signature),$now+301]] as [$body,$sig,$clock]) {
    try {Webhook::verify($body,$sig,$public,$clock);} catch (InvalidArgumentException $e) {$rejected++;}
}
if ($rejected!==3) throw new Exception('Invalid signatures or stale event accepted');
echo "PASS 4 webhook security cases\n";
