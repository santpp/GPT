<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/WebPush.php';

$checks=0;$keyFile=null;
function webCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

/**
 * Stands in for the browser: it holds the subscription's own key pair and
 * derives, from what was sent, exactly what a browser would derive. If it can
 * read the notice, a real browser can read it too.
 */
final class FakeSubscriber {
    public $key;
    public string $auth;
    public function __construct() {
        $this->key=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
        $this->auth=random_bytes(16);
    }
    public function subscription(string $endpoint):array {
        return ['token'=>$endpoint,'p256dh'=>WebPush::encode(WebPush::point($this->key)),'auth'=>WebPush::encode($this->auth)];
    }
    /** RFC 8291 from the receiving side. */
    public function read(string $body):string {
        $salt=substr($body,0,16);
        $length=ord($body[20]);
        $serverPublic=substr($body,21,$length);
        $cipher=substr($body,21+$length);
        $shared=openssl_pkey_derive(WebPush::publicKeyFromPoint($serverPublic),$this->key,32);
        $ikm=WebPush::hkdf($this->auth,$shared,"WebPush: info\x00".WebPush::point($this->key).$serverPublic,32);
        $cek=WebPush::hkdf($salt,$ikm,"Content-Encoding: aes128gcm\x00",16);
        $nonce=WebPush::hkdf($salt,$ikm,"Content-Encoding: nonce\x00",12);
        $plain=openssl_decrypt(substr($cipher,0,-16),'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,substr($cipher,-16));
        if($plain===false)throw new RuntimeException('could not read the notice');
        return rtrim($plain,"\x02");
    }
}

try {
 $keyFile=tempnam(sys_get_temp_dir(),'agora-webpush-test-');@unlink($keyFile);
 webCheck(WebPush::ensureKeys($keyFile,'mailto:pruebas@example.test')&&is_file($keyFile),'the server creates its own key pair the first time');
 webCheck(substr(sprintf('%o',fileperms($keyFile)),-4)==='0600','and keeps it readable only by the site');
 $before=file_get_contents($keyFile);
 WebPush::ensureKeys($keyFile,'mailto:otro@example.test');
 webCheck(file_get_contents($keyFile)===$before,'a second run never replaces a key already in use');

 $push=new WebPush(null,$keyFile);
 $public=$push->publicKey();
 webCheck(strlen(WebPush::decode($public))===65&&WebPush::decode($public)[0]==="\x04",'the public half is a key a browser can subscribe with');
 webCheck(!str_contains(file_get_contents($keyFile),'PUBLIC KEY')||!str_contains($public,'PRIVATE'),'and carries nothing of the private half');

 // ---- a notice a browser can actually read ----------------------------------
 $sent=[];
 $transport=function(string $url,array $headers,string $body)use(&$sent):array {
  $sent[]=['url'=>$url,'headers'=>$headers,'body'=>$body];
  return ['status'=>201,'body'=>''];
 };
 $subscriber=new FakeSubscriber();
 $endpoint='https://fcm.googleapis.com/fcm/send/simulada-'.bin2hex(random_bytes(6));
 $notice=['title'=>'Raissa Moreno','body'=>'Hola, ¿sigue disponible?','conversation_id'=>'conv-1','connection_id'=>7];
 (new WebPush($transport,$keyFile))->send($subscriber->subscription($endpoint),$notice);
 webCheck(count($sent)===1&&$sent[0]['url']===$endpoint,'the notice goes to the address the browser gave, and nowhere else');
 $read=json_decode($subscriber->read($sent[0]['body']),true);
 webCheck($read['title']==='Raissa Moreno'&&$read['body']==='Hola, ¿sigue disponible?','the browser reads exactly what was sent, accents included');
 webCheck($read['data']['path']==='/conversations/conv-1','and where tapping it should go');
 webCheck(!str_contains($sent[0]['body'],'Raissa'),'while the service that forwards it cannot read a word');

 $headers=implode("\n",$sent[0]['headers']);
 webCheck(str_contains($headers,'Content-Encoding: aes128gcm')&&str_contains($headers,'Content-Type: application/octet-stream'),'the request declares the encoding the specification names');
 webCheck(str_contains($headers,'TTL: '),'and how long the service should hold it for a phone that is off');

 // ---- the assertion that says who sent it ------------------------------------
 preg_match('/Authorization: vapid t=([^,]+), k=(\S+)/',$headers,$vapid);
 webCheck(isset($vapid[1],$vapid[2])&&$vapid[2]===$public,'the request is signed and names the key the browser already knows');
 [$head,$claim,$signature]=explode('.',$vapid[1]);
 $claims=json_decode(WebPush::decode($claim),true);
 webCheck(json_decode(WebPush::decode($head),true)['alg']==='ES256','signed the way the specification requires');
 webCheck($claims['aud']==='https://fcm.googleapis.com'&&$claims['exp']>time()&&$claims['exp']<=time()+86400,'for that service alone and for a limited time');
 $raw=WebPush::decode($signature);
 $trim=function(string $half):string{$half=ltrim($half,"\x00");return ord($half[0])>0x7f?"\x00".$half:$half;};
 $r=$trim(substr($raw,0,32));$s2=$trim(substr($raw,32));
 $der="\x30".chr(4+strlen($r)+strlen($s2))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s2)).$s2;
 webCheck(openssl_verify($head.'.'.$claim,$der,WebPush::publicKeyFromPoint(WebPush::decode($public)),OPENSSL_ALGO_SHA256)===1,'and the signature really checks out against the public key');

 // ---- subscriptions that no longer exist -------------------------------------
 $gone=fn(int $status)=>function(string $url,array $headers,string $body)use($status):array{return ['status'=>$status,'body'=>''];};
 foreach([404,410] as $status) {
  $reason=null;
  try{(new WebPush($gone($status),$keyFile))->send($subscriber->subscription($endpoint),$notice);}catch(PushProblem $e){$reason=$e->reason;}
  webCheck($reason==='unregistered','a subscription the service answers '.$status.' for is forgotten');
 }
 // A rejected request or signature says nothing about the phone: it must stay.
 foreach([400,401,403] as $status) {
  $reason=null;
  try{(new WebPush($gone($status),$keyFile))->send($subscriber->subscription($endpoint),$notice);}catch(PushProblem $e){$reason=$e->reason;}
  webCheck($reason==='rejected','a '.$status.' is a rejected send, never a reason to forget the phone');
 }
 $reason=null;
 try{(new WebPush($gone(503),$keyFile))->send($subscriber->subscription($endpoint),$notice);}catch(PushProblem $e){$reason=$e->reason;}
 webCheck($reason==='unavailable','a service that is down is retried instead, not forgotten');

 $reason=null;
 try{(new WebPush($transport,$keyFile))->send(['token'=>'http://inseguro.example/x','p256dh'=>'','auth'=>''],$notice);}catch(PushProblem $e){$reason=$e->reason;}
 webCheck($reason==='unregistered','an address that is not https is refused before anything is sent');
 $reason=null;
 try{(new WebPush($transport,$keyFile))->send(['token'=>$endpoint,'p256dh'=>'no-es-una-clave','auth'=>'x'],$notice);}catch(PushProblem $e){$reason=$e->reason;}
 webCheck($reason==='unregistered','and so are keys that are not the ones a browser gives');

 webCheck((new WebPush(null,'/no/existe/clave.json'))->configured()===false,'without its key pair the server says so instead of failing late');

 echo 'PASS '.$checks.' web push checks (real cryptography, simulated push service)'.PHP_EOL;
} finally {
 if($keyFile!==null)@unlink($keyFile);
}
