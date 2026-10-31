<?php
declare(strict_types=1);
$appRoot=is_dir(__DIR__.'/../agora-private/src') ? dirname(__DIR__).'/agora-private' : dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!str_starts_with($path, '/api/')) {
    $file = realpath(__DIR__.$path);
    if ($file && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') return false;
    if (in_array(rtrim($path,'/')?:'/',['/','/conexiones','/conexiones.html','/bandeja','/contactos','/oportunidades','/tablero'],true) || preg_match('~^/conversations/[A-Za-z0-9_-]+$~', $path)) { readfile(__DIR__.'/index.html'); return true; }
    http_response_code(404); exit;
}
require $appRoot.'/src/bootstrap.php';
header('X-Content-Type-Options: nosniff');
require_once $appRoot.'/src/Installation.php';
// Derived, never typed: a hand-written version here drifted unnoticed before.
header('X-Agora-Version: '.Installation::VERSION);
try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($path === '/api/health' && $method === 'GET') { query('SELECT 1'); respond(['status'=>'ok']); }
    if ($path === '/api/webhooks/ghl' && $method === 'POST') {
        if (getenv('GHL_WEBHOOKS_ENABLED') !== '1') respond(['error'=>'Webhooks disabled'],404);
        require $appRoot.'/src/Webhook.php';
        $raw = file_get_contents('php://input', false, null, 0, 1048577);
        if (strlen($raw) > 1048576) respond(['error'=>'Payload too large'],413);
        $event = Webhook::verify($raw, $_SERVER['HTTP_X_WH_SIGNATURE'] ?? '', file_get_contents($appRoot.'/src/ghl-webhook-public.pem'), time());
        $tenant = query('SELECT id FROM tenants WHERE location_id=?',[$event['locationId']])->fetch();
        if (!$tenant) respond(['error'=>'Unknown location'],403);
        query('INSERT INTO webhook_events(tenant_id,webhook_id,event_type,payload) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE webhook_id=VALUES(webhook_id)',[$tenant['id'],$event['webhookId'],$event['type'],$raw]);
        respond(['accepted'=>true],202);
    }
    // La revisión de cada minuto, llamada desde fuera cuando el cron del servidor
    // no corre. La dirección es el único secreto: sin ella responde como si no
    // existiera, y no devuelve nada de la cuenta, sólo si hizo la pasada.
    // Dos formas: la clave en la cabecera X-Agora-Key (o Authorization: Bearer),
    // que no queda en registros ni historiales, y la antigua, con la clave en la
    // dirección, para los servicios que no permiten cabeceras.
    $cronKey=null;
    if ($path === '/api/cron') {
        $header=(string)($_SERVER['HTTP_X_AGORA_KEY'] ?? '');
        if ($header==='' && preg_match('/^Bearer\s+([a-f0-9]{64})$/D',(string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''),$bearer)) $header=$bearer[1];
        $cronKey=$header;
    } elseif (preg_match('~^/api/cron/([a-f0-9]{64})$~', $path, $cron)) $cronKey=$cron[1];
    if ($cronKey!==null && in_array($method,['GET','POST'],true)) {
        require_once $appRoot.'/src/Sweep.php';
        if (!Sweep::secretMatches($cronKey)) respond(['error'=>'Not found'],404);
        Sweep::noteUse($path==='/api/cron'?'header':'address');
        if (Sweep::looping()) respond(['ok'=>true,'skipped'=>'already_checking']);
        @set_time_limit(70);
        ignore_user_abort(true);
        // Responde enseguida al servicio de cron y se queda revisando cada 10
        // segundos hasta su siguiente llamada. Si el servidor no sabe cerrar la
        // conexión antes, hace una sola pasada, como antes.
        if (function_exists('litespeed_finish_request') || function_exists('fastcgi_finish_request'))
            respondThen(['ok'=>true,'checking'=>true],fn()=>Sweep::loop('url',50));
        if (Sweep::tooSoon()) respond(['ok'=>true,'skipped'=>'too_soon']);
        $result=Sweep::run('url');
        respond(['ok'=>true,'swept'=>$result['swept'],'skipped'=>$result['skipped']]);
    }
    session_name('workspace_session');
    session_set_cookie_params(['httponly'=>true,'secure'=>getenv('APP_ENV') !== 'development','samesite'=>'Strict','path'=>'/']);
    session_start();
    require_once $appRoot.'/src/ExternalAuth.php';
    require_once $appRoot.'/src/Actor.php';
    require_once $appRoot.'/src/DeviceSession.php';
    require_once $appRoot.'/src/Passkeys.php';
    // La sesión del servidor caduca pronto; la del dispositivo dura 60 días y la
    // rehace aquí, antes de comprobar nada más. Una sesión cerrada desde otro
    // dispositivo se corta en la siguiente petición.
    $signedIn=(isset($_SESSION['uid'])||isset($_SESSION['external_identity_id']))&&($_SESSION['expires']??0)>=time();
    if($signedIn&&!DeviceSession::refresh($_SESSION)){$_SESSION=[];$signedIn=false;DeviceSession::forget();}
    if(!$signedIn&&($restored=DeviceSession::resume($_COOKIE[DeviceSession::COOKIE]??null))!==null){session_regenerate_id(true);$_SESSION=$restored;DeviceSession::refresh($_SESSION);}
    $agent=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,400);
    $passkeyTrouble=function(PasskeyProblem $e):never {
        respond(match($e->reason){
            'unknown_credential'=>['error'=>'Esta llave de acceso ya no está registrada en Ágora. Entra con tu correo y vuelve a activarla.','code'=>'passkey_unknown'],
            'inactive'=>['error'=>'Tu acceso a Ágora ya no está activo.','code'=>'passkey_inactive'],
            'not_yours'=>['error'=>'Esa llave de acceso es de otra persona.','code'=>'passkey_not_yours'],
            'challenge_expired'=>['error'=>'Ha pasado demasiado tiempo. Vuelve a intentarlo.','code'=>'passkey_expired'],
            'already_registered'=>['error'=>'Este dispositivo ya tiene una llave de acceso para ti.','code'=>'passkey_exists'],
            default=>['error'=>'No se pudo comprobar la llave de acceso. Vuelve a intentarlo.','code'=>'passkey_'.$e->reason],
        },in_array($e->reason,['unknown_credential','inactive','not_yours'],true)?401:400);
    };
    if ($method !== 'GET') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== getenv('APP_ORIGIN')) respond(['error'=>'El dominio de acceso no coincide con la configuración de la aplicación.'],403);
        if (!in_array($path,['/api/login','/api/team-login/request','/api/team-login/verify','/api/passkey-login/options','/api/passkey-login'],true) && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '!')) respond(['error'=>'La sesión de seguridad ha cambiado. Actualiza la página y vuelve a intentarlo.'],403);
    }
    if ($path === '/api/team-login/request' && $method === 'POST') {
        $b=body();
        try{(new ExternalAuth())->request((string)($b['email']??''),$_SERVER['REMOTE_ADDR']??'');}
        catch(OverflowException $e){respond(['error'=>'Espera unos minutos antes de solicitar otro código.'],429);}
        catch(AccessDeliveryProblem $e){if($e->retryAfter)header('Retry-After: '.$e->retryAfter);respond(['error'=>'El correo de acceso no está disponible ahora mismo. Contacta con el administrador.','code'=>$e->reason],$e->reason==='delivery_paused'?429:503);}
        catch(RuntimeException $e){respond(['error'=>'El correo de acceso no está disponible. Contacta con el administrador.','code'=>'login_delivery_unavailable'],503);}
        catch(InvalidArgumentException $e){}
        respond(['accepted'=>true,'message'=>'Si el correo pertenece a un usuario activo, recibirá un código de acceso.'],202);
    }
    if ($path === '/api/team-login/verify' && $method === 'POST') {
        $b=body();
        try{$identity=(new ExternalAuth())->verify((string)($b['email']??''),(string)($b['code']??''),$_SERVER['REMOTE_ADDR']??'');}
        catch(OverflowException $e){respond(['error'=>'Espera unos minutos antes de volver a intentarlo.'],429);}
        catch(InvalidArgumentException $e){respond(['error'=>'El código no es válido o ha caducado.'],401);}
        session_regenerate_id(true);
        $_SESSION=['external_identity_id'=>$identity,'csrf'=>bin2hex(random_bytes(32)),'expires'=>time()+28800,'seen'=>time()];
        $_SESSION['device_id']=DeviceSession::start($_SESSION,$_SESSION['csrf'],$agent);
        respond(['csrf'=>$_SESSION['csrf']]);
    }
    if ($path === '/api/login' && $method === 'POST') {
        $b = body();
        $email = strtolower(trim((string)($b['email'] ?? '')));
        $key = hash('sha256',($_SERVER['REMOTE_ADDR'] ?? '').'|'.$email);
        query('INSERT INTO login_attempts(key_hash,attempts,window_start) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start < UNIX_TIMESTAMP()-900,1,attempts+1), window_start=IF(window_start < UNIX_TIMESTAMP()-900,UNIX_TIMESTAMP(),window_start)',[$key,time()]);
        if ((int)query('SELECT attempts FROM login_attempts WHERE key_hash=?',[$key])->fetchColumn() > 10) respond(['error'=>'Intenta más tarde'],429);
        $u = query('SELECT * FROM users WHERE email=?',[$email])->fetch();
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        if (!password_verify((string)($b['password'] ?? ''), $u['password_hash'] ?? $dummy) || !$u) respond(['error'=>'Credenciales incorrectas'],401);
        session_regenerate_id(true);
        $_SESSION = ['uid'=>$u['id'],'csrf'=>bin2hex(random_bytes(32)),'expires'=>time()+28800,'seen'=>time()];
        $_SESSION['device_id']=DeviceSession::start($_SESSION,$_SESSION['csrf'],$agent);
        respond(['csrf'=>$_SESSION['csrf']]);
    }
    // Entrar con huella o cara. El teléfono ofrece las llaves que tiene para
    // este dominio; no hace falta escribir el correo.
    if ($path === '/api/passkey-login/options' && $method === 'POST') {
        $key='passkey|'.hash('sha256',$_SERVER['REMOTE_ADDR'] ?? '');
        query('INSERT INTO login_attempts(key_hash,attempts,window_start) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start < UNIX_TIMESTAMP()-900,1,attempts+1), window_start=IF(window_start < UNIX_TIMESTAMP()-900,UNIX_TIMESTAMP(),window_start)',[hash('sha256',$key),time()]);
        if ((int)query('SELECT attempts FROM login_attempts WHERE key_hash=?',[hash('sha256',$key)])->fetchColumn() > 120) respond(['error'=>'Intenta más tarde'],429);
        respond(['publicKey'=>Passkeys::assertionOptions($_SESSION,'login')]);
    }
    if ($path === '/api/passkey-login' && $method === 'POST') {
        $b=body();
        try {$who=Passkeys::verify($_SESSION,'login',is_array($b['credential']??null)?$b['credential']:[]);}
        catch(PasskeyProblem $e) {$passkeyTrouble($e);}
        session_regenerate_id(true);
        $_SESSION=$who+['csrf'=>bin2hex(random_bytes(32)),'expires'=>time()+28800,'seen'=>time()];
        $_SESSION['device_id']=DeviceSession::start($_SESSION,$_SESSION['csrf'],$agent);
        respond(['csrf'=>$_SESSION['csrf']]);
    }
    if ((!isset($_SESSION['uid'])&&!isset($_SESSION['external_identity_id'])) || ($_SESSION['expires'] ?? 0) < time()) respond(['error'=>'Inicia sesión para continuar.'],401);
    $u = Actor::fromSession($_SESSION);
    if (!$u) respond(['error'=>'Inicia sesión para continuar.'],401);
    $admin=$u['role']==='administrator';
    // Bloqueo por inactividad: mientras dure, sólo se puede desbloquear o salir.
    if (DeviceSession::locked($_SESSION) && !in_array($path,['/api/me','/api/logout','/api/passkey-unlock/options','/api/passkey-unlock'],true))
        respond(['error'=>'Ágora está bloqueada. Desbloquéala con tu huella o tu cara.','code'=>'locked'],423);
    if ($path === '/api/passkey-unlock/options' && $method === 'POST') respond(['publicKey'=>Passkeys::assertionOptions($_SESSION,'unlock',$u)]);
    if ($path === '/api/passkey-unlock' && $method === 'POST') {
        $b=body();
        try {Passkeys::verify($_SESSION,'unlock',is_array($b['credential']??null)?$b['credential']:[],$u);}
        catch(PasskeyProblem $e) {$passkeyTrouble($e);}
        unset($_SESSION['locked']);$_SESSION['seen']=time();
        respond(['unlocked'=>true]);
    }
    if ($path === '/api/security' && $method === 'GET') {
        respond(['passkeys'=>Passkeys::list($u,false),'sessions'=>DeviceSession::list($u,false,$_SESSION['device_id']??null),
            'team'=>$admin?['passkeys'=>Passkeys::list($u,true),'sessions'=>DeviceSession::list($u,true,$_SESSION['device_id']??null)]:null,
            'lockMinutes'=>$_SESSION['lock_minutes']??null,'lockChoices'=>DeviceSession::LOCK_CHOICES,'deviceRemembered'=>!empty($_SESSION['device_id'])]);
    }
    if ($path === '/api/passkeys/options' && $method === 'POST') respond(['publicKey'=>Passkeys::registrationOptions($u,$_SESSION)]);
    if ($path === '/api/passkeys' && $method === 'POST') {
        $b=body();
        try {$id=Passkeys::register($u,$_SESSION,is_array($b['credential']??null)?$b['credential']:[],DeviceSession::label($agent));}
        catch(PasskeyProblem $e) {$passkeyTrouble($e);}
        respond(['registered'=>true,'id'=>$id],201);
    }
    if (preg_match('~^/api/passkeys/(\d+)$~',$path,$m) && $method === 'DELETE') {
        if(!Passkeys::remove($u,$admin,(int)$m[1]))respond(['error'=>'Esa llave ya no existe.'],404);
        // Sin llaves, el bloqueo no se podría quitar: se apaga con ellas.
        if(Passkeys::count($u)===0)query('UPDATE device_sessions SET lock_minutes=NULL WHERE tenant_id=? AND identity_type=? AND actor_id=?',[$u['tenant_id'],$u['identity_type'],$u['id']]);
        respond(['removed'=>true]);
    }
    if ($path === '/api/security/lock' && $method === 'POST') {
        $b=body();$minutes=$b['minutes']??null;
        if($minutes!==null&&(!is_int($minutes)||!in_array($minutes,DeviceSession::LOCK_CHOICES,true)))respond(['error'=>'Elige uno de los tiempos de la lista.'],422);
        if($minutes!==null&&Passkeys::count($u)===0)respond(['error'=>'Activa primero la entrada con huella o cara en este dispositivo.','code'=>'passkey_required'],409);
        if(empty($_SESSION['device_id']))respond(['error'=>'Vuelve a entrar en Ágora para poder usar el bloqueo en este dispositivo.','code'=>'device_unknown'],409);
        DeviceSession::setLock($_SESSION,$minutes);$_SESSION['lock_minutes']=$minutes;
        respond(['lockMinutes'=>$minutes]);
    }
    if (preg_match('~^/api/sessions/(\d+)$~',$path,$m) && $method === 'DELETE') {
        if(!DeviceSession::revoke($u,$admin,(int)$m[1]))respond(['error'=>'Esa sesión ya estaba cerrada.'],404);
        respond(['revoked'=>true,'current'=>(int)$m[1]===(int)($_SESSION['device_id']??0)]);
    }
    if ($path==='/api/admin/installation/migrations' && $method==='POST') {
        if($u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
        require_once $appRoot.'/src/Installation.php';
        if(getenv('APP_ENV')!=='development' && parse_url(getenv('APP_ORIGIN')?:'',PHP_URL_SCHEME)!=='https')throw new SetupException('origin_unconfigured');
        Installation::applyMigrations();
        respond(['applied'=>true]);
    }
    if ($path==='/api/admin/installation/encryption' && $method==='POST') {
        if($u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
        require_once $appRoot.'/src/Installation.php';
        if(getenv('APP_ENV')!=='development' && parse_url(getenv('APP_ORIGIN')?:'',PHP_URL_SCHEME)!=='https')throw new SetupException('origin_unconfigured');
        Installation::prepareEncryption();
        respond(['prepared'=>true]);
    }
    if ($path==='/api/admin/diagnostics' && $method==='GET') {
        if($u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
        require_once $appRoot.'/src/Installation.php';
        $checks=Installation::checks();
        respond(['version'=>Installation::VERSION,'ready'=>!in_array(false,array_column($checks,'ready'),true),'checks'=>$checks]);
    }
    if (str_starts_with($path, '/api/workspace/') || str_starts_with($path, '/api/ghl/')) {session_write_close();require $appRoot.'/src/live-inbox.php';}
    if (str_starts_with($path, '/api/admin/integrations') || str_starts_with($path, '/api/admin/access-channel')) {
        if (getenv('APP_ENV') !== 'development' && parse_url(getenv('APP_ORIGIN') ?: '', PHP_URL_SCHEME) !== 'https') respond(['error'=>'HTTPS required'],503);
        require $appRoot.'/src/integrations.php';
    }
    if ($path === '/api/admin/cron-url' && in_array($method,['POST','DELETE'],true)) {
        if(($u['identity_type'] ?? 'native')!=='native' || $u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
        require_once $appRoot.'/src/Sweep.php';
        if($method==='DELETE'){Sweep::disableSecret();respond(['enabled'=>false]);}
        // Crear la clave pide otra vez la contraseña: una sesión abierta y olvidada
        // en otro aparato no basta para sacarla. Con límite de intentos, como entrar.
        $key='cron-confirm|'.hash('sha256',($_SERVER['REMOTE_ADDR'] ?? '').'|'.$u['id']);
        query('INSERT INTO login_attempts(key_hash,attempts,window_start) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start < UNIX_TIMESTAMP()-900,1,attempts+1), window_start=IF(window_start < UNIX_TIMESTAMP()-900,UNIX_TIMESTAMP(),window_start)',[hash('sha256',$key),time()]);
        if((int)query('SELECT attempts FROM login_attempts WHERE key_hash=?',[hash('sha256',$key)])->fetchColumn()>5)respond(['error'=>'Demasiados intentos. Espera unos minutos.'],429);
        $hash=query('SELECT password_hash FROM users WHERE id=?',[$u['id']])->fetchColumn();
        $given='';try{$given=(string)(body()['password']??'');}catch(Throwable $e){}
        if(!is_string($hash)||$given===''||!password_verify($given,$hash))respond(['error'=>'La contraseña no es correcta.','code'=>'password_mismatch'],403);
        query('DELETE FROM login_attempts WHERE key_hash=?',[hash('sha256',$key)]);
        // Se enseña una sola vez: después sólo queda su huella.
        $secret=Sweep::createSecret();
        $origin=rtrim((string)getenv('APP_ORIGIN'),'/');
        respond(['enabled'=>true,'endpoint'=>$origin.'/api/cron','header'=>'X-Agora-Key','key'=>$secret,'url'=>$origin.'/api/cron/'.$secret]);
    }
    if ($path === '/api/devices') require_once $appRoot.'/src/PushDelivery.php';
    // What a browser needs to subscribe. Public by design, and created the
    // first time it is asked for so nobody has to generate anything by hand.
    if ($path === '/api/push-key' && $method === 'GET') {
        require_once $appRoot.'/src/WebPush.php';
        // Whatever goes wrong here must name itself: a generic failure would
        // leave the person tapping a button that never explains anything.
        try {
            $configured=is_string(getenv('WEBPUSH_KEY_FILE'))&&getenv('WEBPUSH_KEY_FILE')!=='';
            if(!$configured)respond(['key'=>null,'ready'=>false,'code'=>'push_not_configured','error'=>'Falta indicar dónde guardar las claves de avisos en la configuración privada del servidor.']);
            WebPush::ensureKeys(null,'mailto:soporte@'.(parse_url((string)getenv('APP_ORIGIN'),PHP_URL_HOST)?:'agoradigital.app'));
            $push=new WebPush();
            if(!$push->configured())respond(['key'=>null,'ready'=>false,'code'=>'push_key_unwritable','error'=>'El servidor no pudo crear las claves de avisos. Revisa que la carpeta privada var sea escribible.']);
            respond(['key'=>$push->publicKey(),'ready'=>true]);
        } catch(Throwable $e) {
            respond(['key'=>null,'ready'=>false,'code'=>'push_key_failed','error'=>'No se pudieron preparar las claves de avisos en el servidor.']);
        }
    }
    if ($path === '/api/devices' && $method === 'GET') {
        if(($u['identity_type'] ?? 'native')!=='native' || $u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
        require_once $appRoot.'/src/PushDelivery.php';
        try {$rows=query('SELECT id,platform,label,created_at,last_seen_at,last_sent_at FROM push_devices WHERE tenant_id=? ORDER BY last_seen_at DESC LIMIT 100',[$u['tenant_id']])->fetchAll();}
        catch(PDOException $e) {
            // A pending database update must say so, not look like a broken server.
            respond(['items'=>[],'browser'=>false,'android'=>false,'code'=>'push_schema_pending','error'=>'Falta aplicar la última actualización de la base de datos.'],200);
        }
        // El estado de cada eslabón, para saber dónde se corta un aviso que no llega.
        $queue=['pending'=>0,'sent'=>0,'failed'=>0,'lastError'=>null];
        try {
            foreach(query('SELECT q.state,COUNT(*) AS n FROM push_queue q JOIN ghl_connections c ON c.id=q.connection_id WHERE c.tenant_id=? AND q.created_at>=NOW()-INTERVAL 7 DAY GROUP BY q.state',[$u['tenant_id']])->fetchAll() as $r)$queue[$r['state']]=(int)$r['n'];
            $last=query("SELECT q.last_error FROM push_queue q JOIN ghl_connections c ON c.id=q.connection_id WHERE c.tenant_id=? AND q.last_error IS NOT NULL ORDER BY q.id DESC LIMIT 1",[$u['tenant_id']])->fetchColumn();
            $queue['lastError']=is_string($last)?$last:null;
        } catch(PDOException $e) {}
        respond(['items'=>$rows,'browser'=>PushDelivery::browserConfigured(),'android'=>PushDelivery::firebaseConfigured(),
            'queue'=>$queue,'delivery'=>PushDelivery::recall('push_last_run'),'discovery'=>PushDelivery::recall('sweep_last_run'),
            'externalCheck'=>PushDelivery::recall('cron_secret')['created']??null,'externalCheckUse'=>PushDelivery::recall('cron_last_use')]);
    }
    // Un aviso de prueba a los dispositivos de quien lo pide, entregado en el acto.
    if ($path === '/api/devices/test' && $method === 'POST') {
        require_once $appRoot.'/src/PushDelivery.php';
        $type=($u['identity_type'] ?? 'native')==='native'?'native':'external';
        try {respond(['results'=>(new PushDelivery())->test((int)$u['tenant_id'],$type,(int)$u['id'])]);}
        catch(Throwable $e){respond(['error'=>'No se pudo enviar el aviso de prueba.','code'=>'push_test_failed'],200);}
    }
    if ($path === '/api/me' && $method === 'GET') respond(['user'=>array_intersect_key($u,array_flip(['id','email','role','identity_type','capabilities'])),'csrf'=>$_SESSION['csrf'],'locked'=>!empty($_SESSION['locked']),'passkeys'=>Passkeys::count($u),'lockMinutes'=>$_SESSION['lock_minutes']??null]);
    // A phone registers the token its own system gives it, so Ágora can notify
    // it. Nothing else about the device is stored, and the token belongs to the
    // signed-in person: signing out on that phone removes it.
    if ($path === '/api/devices' && in_array($method,['POST','DELETE'],true)) {
        $body=body();
        $token=$body['token'] ?? null;
        // A browser hands out an address, with a host and slashes, and only over
        // https; a phone hands out a plain identifier. Both are accepted, and the
        // shape decides which, so removing one never depends on how it was named.
        $browser=is_string($token)&&strlen($token)<=512&&preg_match('~^https://[A-Za-z0-9.-]+(?::\d+)?/[\x21-\x7e]{8,}$~D',$token);
        $phone=is_string($token)&&preg_match('/^[A-Za-z0-9_:.~-]{32,255}$/D',$token);
        if(!$browser&&!$phone)respond(['error'=>'Este dispositivo no ha dado una dirección de avisos válida.','code'=>'invalid_device'],422);
        $platform=$browser?'web':'android';
        if($method==='DELETE'){query('DELETE FROM push_devices WHERE tenant_id=? AND token=?',[$u['tenant_id'],$token]);respond(['registered'=>false]);}
        $type=($u['identity_type'] ?? 'native')==='native'?'native':'external';
        // A browser subscription is an address plus the two keys its own system
        // generated; without them nothing could be encrypted for it.
        $p256dh=$auth=null;
        if($platform==='web') {
            $p256dh=$body['keys']['p256dh'] ?? null;$auth=$body['keys']['auth'] ?? null;
            if(!is_string($p256dh)||!is_string($auth)||!preg_match('/^[A-Za-z0-9_-]{20,255}$/D',$p256dh)||!preg_match('/^[A-Za-z0-9_-]{10,64}$/D',$auth))
                respond(['error'=>'La suscripción de este navegador no es válida.'],422);
        }
        $label=$body['label'] ?? null;
        $label=is_string($label)&&trim($label)!==''?mb_substr(preg_replace('/[^\p{L}\p{N} .,()\/-]/u','',trim($label)),0,80):null;
        query('INSERT INTO push_devices(tenant_id,identity_type,actor_id,token,platform,p256dh,auth,label) VALUES(?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id),identity_type=VALUES(identity_type),actor_id=VALUES(actor_id),platform=VALUES(platform),p256dh=VALUES(p256dh),auth=VALUES(auth),label=VALUES(label),last_seen_at=CURRENT_TIMESTAMP',
            [$u['tenant_id'],$type,$u['id'],$token,$platform,$p256dh,$auth,$label]);
        respond(['registered'=>true,'notifications'=>PushDelivery::configured()]);
    }
    if ($path === '/api/logout' && $method === 'POST') { DeviceSession::end($_SESSION); $_SESSION=[]; session_destroy(); respond(['ok'=>true]); }
    if ($path === '/api/conversations' && $method === 'GET') {
        if(($u['identity_type']??'native')!=='native')respond(['error'=>'Utiliza la bandeja conectada para consultar tus conversaciones.'],403);
        $sql = 'SELECT ghl_id,payload,updated_at FROM resources WHERE tenant_id=? AND kind=?';
        $args = [$u['tenant_id'],'conversation'];
        if ($u['role'] === 'seller') {
            if (!$u['ghl_user_id']) respond(['items'=>[]]);
            $sql .= ' AND assigned_to=?'; $args[]=$u['ghl_user_id'];
        }
        $rows=query($sql.' ORDER BY updated_at DESC LIMIT 100',$args)->fetchAll();
        respond(['items'=>array_map(fn($r)=>['id'=>$r['ghl_id'],'data'=>json_decode($r['payload'],true),'updatedAt'=>$r['updated_at']],$rows)]);
    }
    respond(['error'=>'Esta función todavía no está disponible.'],404);
} catch (SetupException $e) { respond(['error'=>$e->getMessage(),'code'=>$e->issue],503);
} catch (InvalidArgumentException|JsonException $e) { respond(['error'=>'Revisa los datos de la solicitud.'],400);
} catch (Throwable $e) {
    $reference=bin2hex(random_bytes(6));
    $state=$e instanceof PDOException?preg_replace('/[^A-Za-z0-9]/','',(string)$e->getCode()):'none';
    error_log('API failure ref='.$reference.' type='.get_class($e).' state='.$state);
    respond(['error'=>'No se ha podido completar la operación. Referencia: '.$reference,'code'=>'internal_error','reference'=>$reference],503);
}
