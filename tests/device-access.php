<?php
declare(strict_types=1);
// La sesión que dura en el dispositivo y la entrada con huella o cara, de punta a
// punta contra el servidor de pruebas (APP_ORIGIN http://localhost:8080) y una
// MariaDB real. Las llaves de acceso se firman aquí con una clave P-256 generada
// para la prueba, igual que lo haría el teléfono.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Passkeys.php';
$suffix=bin2hex(random_bytes(6));$tenant=null;$checks=0;
function check(bool $ok,string $label):void{global $checks;if(!$ok)throw new Exception($label);$checks++;echo "PASS $label\n";}

/** Un navegador: guarda sus cookies y deja quitar una para simular que caducó. */
final class Browser {
    public array $jar=[];public array $set=[];
    public function call(string $path,string $method='GET',?array $body=null,string $csrf='',string $origin='http://localhost:8080'):array {
        $ch=curl_init('http://localhost:8080/api/'.$path);$this->set=[];
        $cookies=implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($this->jar),$this->jar));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,
            CURLOPT_HTTPHEADER=>array_filter(['Content-Type: application/json','Origin: '.$origin,'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',$cookies?'Cookie: '.$cookies:null,$csrf?'X-CSRF-Token: '.$csrf:null]),
            CURLOPT_HEADERFUNCTION=>function($ch,string $line){
                if(stripos($line,'Set-Cookie:')===0){$raw=trim(substr($line,11));$this->set[]=$raw;[$pair]=explode(';',$raw,2);[$k,$v]=array_pad(explode('=',$pair,2),2,'');
                    if($v===''||$v==='deleted'||stripos($raw,'expires=Thu, 01 Jan 1970')!==false||preg_match('/Max-Age=0\b/i',$raw)||preg_match('/expires=[^;]*19[0-9]{2}/i',$raw))unset($this->jar[$k]);else $this->jar[$k]=$v;}
                return strlen($line);}]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
        $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        return [$code,json_decode($raw?:'{}',true)??[]];
    }
    public function setCookie(string $name):?string {foreach($this->set as $raw)if(str_starts_with($raw,$name.'='))return $raw;return null;}
}

/** Lo justo de CBOR para fabricar lo que manda un autenticador. */
function cbor(mixed $v):string {
    $head=function(int $major,int $n):string{if($n<24)return chr($major<<5|$n);if($n<256)return chr($major<<5|24).chr($n);if($n<65536)return chr($major<<5|25).pack('n',$n);return chr($major<<5|26).pack('N',$n);};
    if(is_int($v))return $v>=0?$head(0,$v):$head(1,-1-$v);
    if($v instanceof Bytes)return $head(2,strlen($v->b)).$v->b;
    if(is_string($v))return $head(3,strlen($v)).$v;
    if(is_array($v)&&array_is_list($v)&&$v!==[])return $head(4,count($v)).implode('',array_map('cbor',$v));
    if(is_array($v)){$out=$head(5,count($v));foreach($v as $k=>$x)$out.=cbor($k).cbor($x);return $out;}
    throw new Exception('cbor');
}
final class Bytes {public function __construct(public string $b){}}
$b64=fn(string $v)=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');

/** Un teléfono con una llave de acceso P-256. */
final class Phone {
    public $key;public string $id;public int $count=0;
    public function __construct(public bool $rsa=false){$this->key=$rsa?openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]):openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);$this->id=random_bytes(32);}
    public function create(array $options,string $origin='http://localhost:8080',int $flags=0x45):array {
        global $b64;
        if($this->rsa){$d=openssl_pkey_get_details($this->key)['rsa'];$cose=cbor([1=>3,3=>-257,-1=>new Bytes($d['n']),-2=>new Bytes($d['e'])]);}
        else {$d=openssl_pkey_get_details($this->key)['ec'];$cose=cbor([1=>2,3=>-7,-1=>1,-2=>new Bytes(str_pad($d['x'],32,"\0",STR_PAD_LEFT)),-3=>new Bytes(str_pad($d['y'],32,"\0",STR_PAD_LEFT))]);}
        $auth=hash('sha256',$options['rp']['id'],true).chr($flags).pack('N',0).str_repeat("\0",16).pack('n',strlen($this->id)).$this->id.$cose;
        $client=json_encode(['type'=>'webauthn.create','challenge'=>$options['challenge'],'origin'=>$origin,'crossOrigin'=>false]);
        return ['id'=>$b64($this->id),'rawId'=>$b64($this->id),'type'=>'public-key','response'=>['clientDataJSON'=>$b64($client),'attestationObject'=>$b64(cbor(['fmt'=>'none','attStmt'=>[],'authData'=>new Bytes($auth)]))]];
    }
    public function sign(array $options,int $flags=0x05,?string $origin=null,bool $tamper=false):array {
        global $b64;$this->count++;
        $auth=hash('sha256',$options['rpId'],true).chr($flags).pack('N',$this->count);
        $client=json_encode(['type'=>'webauthn.get','challenge'=>$options['challenge'],'origin'=>$origin??'http://localhost:8080']);
        openssl_sign($auth.hash('sha256',$client,true),$signature,$this->key,OPENSSL_ALGO_SHA256);
        if($tamper)$signature[10]=chr(ord($signature[10])^1);
        return ['id'=>$b64($this->id),'rawId'=>$b64($this->id),'type'=>'public-key','response'=>['clientDataJSON'=>$b64($client),'authenticatorData'=>$b64($auth),'signature'=>$b64($signature),'userHandle'=>null]];
    }
}

try {
 foreach(['127.0.0.1','::1'] as $ip)query('DELETE FROM login_attempts WHERE key_hash=?',[hash('sha256','passkey|'.hash('sha256',$ip))]);
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Device test','device-'.$suffix]);$tenant=(int)db()->lastInsertId();
 $password=bin2hex(random_bytes(12));$email='owner-'.$suffix.'@example.test';$sellerEmail='seller-'.$suffix.'@example.test';
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'administrator')",[$tenant,$email,password_hash($password,PASSWORD_DEFAULT)]);$owner=(int)db()->lastInsertId();
 query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,'seller')",[$tenant,$sellerEmail,password_hash($password,PASSWORD_DEFAULT)]);$seller=(int)db()->lastInsertId();

 // ---- la sesión del dispositivo ------------------------------------------------
 $phone=new Browser();
 [$code,$login]=$phone->call('login','POST',['email'=>$email,'password'=>$password]);
 check($code===200&&isset($phone->jar['agora_device']),'entering leaves a device cookie next to the server session');
 $raw=$phone->setCookie('agora_device');
 check(stripos($raw,'HttpOnly')!==false&&stripos($raw,'SameSite=Strict')!==false&&preg_match('/Max-Age=(\d+)/i',$raw,$age)&&(int)$age[1]>59*86400,'the cookie is out of reach of the page, never sent from other sites, and lasts 60 days');
 [$selector,$secret]=explode('.',$phone->jar['agora_device']);
 $row=query('SELECT * FROM device_sessions WHERE selector=?',[$selector])->fetch();
 check($row&&$row['verifier_hash']===hash('sha256',$secret)&&!str_contains(json_encode($row),$secret),'the database keeps only a fingerprint of the secret, never the secret');
 check($row['label']==='iPhone · Safari','the session is named after the device so the owner can recognise it');

 unset($phone->jar['workspace_session']);
 [$code,$me]=$phone->call('me');
 check($code===200&&$me['user']['email']===$email,'when the server session is gone, the device cookie brings it back without asking again');
 check($me['csrf']===$login['csrf'],'and with the same security token the open page already holds');
 [$code]=$phone->call('security/lock','POST',['minutes'=>null],$login['csrf']);
 check($code===200,'so a change sent right after keeps working');

 $thief=new Browser();$thief->jar['agora_device']=$selector.'.'.str_repeat('A',43);
 check($thief->call('me')[0]===401&&!isset($thief->jar['agora_device']),'a cookie with the wrong secret does not enter, and is wiped');

 // ---- rotación ---------------------------------------------------------------
 query('UPDATE device_sessions SET rotated_at=? WHERE selector=?',[time()-90000,$selector]);
 $old=$phone->jar['agora_device'];unset($phone->jar['workspace_session']);
 $phone->call('me');
 check($phone->jar['agora_device']!==$old&&str_starts_with($phone->jar['agora_device'],$selector.'.'),'once a day the secret is replaced');
 $twin=new Browser();$twin->jar['agora_device']=$old;
 check($twin->call('me')[0]===200,'a second tab that still sends the old one is not thrown out in the same moment');
 query('UPDATE device_sessions SET rotated_at=? WHERE selector=?',[time()-300,$selector]);
 $late=new Browser();$late->jar['agora_device']=$old;
 check($late->call('me')[0]===401,'but minutes later the old secret no longer opens anything');

 // ---- cerrar sesiones ----------------------------------------------------------
 $laptop=new Browser();[,$laptopLogin]=$laptop->call('login','POST',['email'=>$email,'password'=>$password]);
 [$code,$security]=$laptop->call('security');
 check($code===200&&count($security['sessions'])>=2&&count(array_filter($security['sessions'],fn($s)=>$s['current']))===1,'the owner sees every open device and which one is this');
 $phoneId=(int)query('SELECT id FROM device_sessions WHERE selector=?',[$selector])->fetchColumn();
 check($laptop->call('sessions/'.$phoneId,'DELETE',null,$laptopLogin['csrf'])[0]===200,'and can close the phone session from the laptop');
 check($phone->call('me')[0]===401,'the phone is out on its very next request, not when its session expires');

 $sellerPhone=new Browser();[,$sellerLogin]=$sellerPhone->call('login','POST',['email'=>$sellerEmail,'password'=>$password]);
 [,$sellerSecurity]=$sellerPhone->call('security');
 check($sellerSecurity['team']===null&&!array_filter($sellerSecurity['sessions'],fn($s)=>!$s['mine']),'a seller sees only their own devices');
 $laptopId=(int)current(array_filter($security['sessions'],fn($s)=>$s['current']))['id'];
 check($sellerPhone->call('sessions/'.$laptopId,'DELETE',null,$sellerLogin['csrf'])[0]===404,'and cannot close anyone else\'s');
 [,$teamView]=$laptop->call('security');
 check(count(array_filter($teamView['team']['sessions'],fn($s)=>$s['email']===$sellerEmail))===1,'while the owner sees the seller\'s phone in the team list');

 $cookie=$laptop->jar['agora_device'];
 $laptop->call('logout','POST',null,$laptopLogin['csrf']);
 check(!isset($laptop->jar['agora_device']),'signing out wipes the device cookie');
 $again=new Browser();$again->jar['agora_device']=$cookie;
 check($again->call('me')[0]===401,'and a copy of it no longer enters');

 // ---- llave de acceso: alta -----------------------------------------------------
 $phone=new Browser();[,$login]=$phone->call('login','POST',['email'=>$email,'password'=>$password]);
 check($phone->call('security/lock','POST',['minutes'=>15],$login['csrf'])[0]===409,'the lock cannot be turned on before there is a fingerprint or face key to open it');
 $device=new Phone();
 [$code,$options]=$phone->call('passkeys/options','POST',null,$login['csrf']);
 $pk=$options['publicKey'];
 check($code===200&&$pk['rp']['id']==='localhost'&&$pk['authenticatorSelection']['userVerification']==='required'&&$pk['attestation']==='none','the phone is asked for a key bound to this domain that needs the fingerprint, face or PIN');
 check(strlen(Passkeys::unb64($pk['user']['id']))===32&&!str_contains($pk['user']['id'],(string)$owner),'the key carries an opaque id, not the internal one');
 check($phone->call('passkeys','POST',['credential'=>$device->create($pk,'https://agora.example.evil')],$login['csrf'])[0]===400,'a key made for another website is refused');
 [,$options]=$phone->call('passkeys/options','POST',null,$login['csrf']);
 check($phone->call('passkeys','POST',['credential'=>$device->create($options['publicKey'],'http://localhost:8080',0x41)],$login['csrf'])[0]===400,'a key made without the fingerprint, face or PIN is refused');
 [,$options]=$phone->call('passkeys/options','POST',null,$login['csrf']);
 $made=$device->create($options['publicKey']);
 [$code]=$phone->call('passkeys','POST',['credential'=>$made],$login['csrf']);
 check($code===201,'the key is saved');
 check($phone->call('passkeys','POST',['credential'=>$made],$login['csrf'])[0]===400,'and the same answer cannot be replayed');
 $stored=query('SELECT * FROM passkeys WHERE tenant_id=?',[$tenant])->fetch();
 check(str_starts_with($stored['public_key'],'-----BEGIN PUBLIC KEY-----')&&$stored['algorithm']==-7,'only the public half is stored');

 // ---- llave de acceso: entrar ----------------------------------------------------
 $fresh=new Browser();
 [$code,$options]=$fresh->call('passkey-login/options','POST',[]);
 check($code===200&&!isset($options['publicKey']['allowCredentials'])&&$options['publicKey']['userVerification']==='required','signing in with a key asks for nothing but the fingerprint or face');
 check($fresh->call('passkey-login','POST',['credential'=>$device->sign($options['publicKey'],0x05,null,true)])[0]===400,'a forged signature does not enter');
 [,$options]=$fresh->call('passkey-login/options','POST',[]);
 check($fresh->call('passkey-login','POST',['credential'=>$device->sign($options['publicKey'],0x01)])[0]===400,'nor one made without verifying the person');
 [,$options]=$fresh->call('passkey-login/options','POST',[]);
 check($fresh->call('passkey-login','POST',['credential'=>$device->sign($options['publicKey'],0x05,'https://phishing.example')])[0]===400,'nor one signed for a look-alike site');
 [,$options]=$fresh->call('passkey-login/options','POST',[]);
 $stranger=new Phone();
 check($fresh->call('passkey-login','POST',['credential'=>$stranger->sign($options['publicKey'])])[0]===401,'an unknown key is told so');
 [,$options]=$fresh->call('passkey-login/options','POST',[]);
 [$code,$entered]=$fresh->call('passkey-login','POST',['credential'=>$device->sign($options['publicKey'])]);
 check($code===200&&isset($entered['csrf'])&&isset($fresh->jar['agora_device']),'the fingerprint or face enters, and the device is remembered too');
 [,$me]=$fresh->call('me');
 check($me['user']['email']===$email&&$me['passkeys']===1,'as the person who owns the key');
 check($fresh->call('passkey-login','POST',['credential'=>$device->sign($options['publicKey'])])[0]===400,'the same challenge does not open a second time');
 query('UPDATE passkeys SET sign_count=100 WHERE tenant_id=?',[$tenant]);
 [,$options]=$fresh->call('passkey-login/options','POST',[]);
 check($fresh->call('passkey-login','POST',['credential'=>$device->sign($options['publicKey'])])[0]===400,'a key whose counter goes backwards, a sign of a copy, is refused');
 query('UPDATE passkeys SET sign_count=0 WHERE tenant_id=?',[$tenant]);

 // ---- bloqueo por inactividad ---------------------------------------------------------
 check($phone->call('security/lock','POST',['minutes'=>7],$login['csrf'])[0]===422,'only the offered lock times are accepted');
 check($phone->call('security/lock','POST',['minutes'=>15],$login['csrf'])[0]===200,'with a key, the lock can be turned on for this device');
 unset($phone->jar['workspace_session']);
 [$code,$me]=$phone->call('me');
 check($code===200&&$me['locked']===true,'after the app sat unused, the device comes back locked');
 [$code,$blocked]=$phone->call('security');
 check($code===423&&$blocked['code']==='locked','and the server itself refuses everything else until it is opened');
 $other=new Phone();
 [,$options]=$phone->call('passkey-unlock/options','POST',null,$login['csrf']);
 check(count($options['publicKey']['allowCredentials'])===1,'unlocking asks only for this person\'s keys');
 check($phone->call('passkey-unlock','POST',['credential'=>$other->sign($options['publicKey'])],$login['csrf'])[0]===401,'someone else\'s key does not open it');
 [,$options]=$phone->call('passkey-unlock/options','POST',null,$login['csrf']);
 check($phone->call('passkey-unlock','POST',['credential'=>$device->sign($options['publicKey'])],$login['csrf'])[0]===200,'the owner\'s fingerprint or face opens it');
 check($phone->call('security')[0]===200,'and everything works again');

 // ---- quitar la llave ----------------------------------------------------------------
 [,$security]=$phone->call('security');
 $keyId=$security['passkeys'][0]['id'];
 check($sellerPhone->call('passkeys/'.$keyId,'DELETE',null,$sellerLogin['csrf'])[0]===404,'a seller cannot remove someone else\'s key');
 check($phone->call('passkeys/'.$keyId,'DELETE',null,$login['csrf'])[0]===200,'the owner removes the key of a lost phone');
 check(query('SELECT lock_minutes FROM device_sessions WHERE tenant_id=? AND actor_id=? AND lock_minutes IS NOT NULL',[$tenant,$owner])->fetchColumn()===false,'and without keys the lock turns itself off, so nobody is left locked out');
 [,$options]=$fresh->call('passkey-login/options','POST',[]);
 check($fresh->call('passkey-login','POST',['credential'=>$device->sign($options['publicKey'])])[0]===401,'the removed key no longer enters');

 // ---- un acceso que deja de existir -----------------------------------------------------
 $gone=new Browser();$gone->call('login','POST',['email'=>$sellerEmail,'password'=>$password]);
 query('DELETE FROM users WHERE id=?',[$seller]);
 unset($gone->jar['workspace_session']);
 check($gone->call('me')[0]===401,'someone removed from Ágora does not come back through the device cookie');

 // ---- llaves RSA (Windows Hello) -------------------------------------------------------
 $windows=new Phone(true);
 [,$options]=$phone->call('passkeys/options','POST',null,$login['csrf']);
 check($phone->call('passkeys','POST',['credential'=>$windows->create($options['publicKey'])],$login['csrf'])[0]===201,'a computer that makes RSA keys, like Windows Hello, can register one too');
 [,$options]=$fresh->call('passkey-login/options','POST',[]);
 check($fresh->call('passkey-login','POST',['credential'=>$windows->sign($options['publicKey'])])[0]===200,'and enter with it');

 // ---- CBOR ------------------------------------------------------------------------------
 $failed=false;try{$o=0;Cbor::decode("\x5a\x00\x00\x10\x00ab",$o);}catch(PasskeyProblem $e){$failed=true;}
 check($failed,'a truncated or oversized answer from the phone is refused, not guessed');

 echo "PASS $checks device access checks (real server, real MariaDB)\n";
} finally {
 if($tenant){query('DELETE FROM device_sessions WHERE tenant_id=?',[$tenant]);query('DELETE FROM passkeys WHERE tenant_id=?',[$tenant]);query('DELETE FROM users WHERE tenant_id=?',[$tenant]);query('DELETE FROM tenants WHERE id=?',[$tenant]);}
}
