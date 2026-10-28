<?php
declare(strict_types=1);
$appRoot=is_dir(__DIR__.'/../agora-private/src') ? dirname(__DIR__).'/agora-private' : dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!str_starts_with($path, '/api/')) {
    $file = realpath(__DIR__.$path);
    if ($file && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') return false;
    if ($path === '/' || preg_match('~^/conversations/[A-Za-z0-9_-]+$~', $path)) { readfile(__DIR__.'/index.html'); return true; }
    http_response_code(404); exit;
}
require $appRoot.'/src/bootstrap.php';
header('X-Content-Type-Options: nosniff');
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
    session_name('workspace_session');
    session_set_cookie_params(['httponly'=>true,'secure'=>getenv('APP_ENV') !== 'development','samesite'=>'Strict','path'=>'/']);
    session_start();
    if ($method !== 'GET') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== getenv('APP_ORIGIN')) respond(['error'=>'Origin rejected'],403);
        if ($path !== '/api/login' && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '!')) respond(['error'=>'CSRF rejected'],403);
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
        $_SESSION = ['uid'=>$u['id'],'csrf'=>bin2hex(random_bytes(32)),'expires'=>time()+28800];
        respond(['csrf'=>$_SESSION['csrf']]);
    }
    if (!isset($_SESSION['uid']) || ($_SESSION['expires'] ?? 0) < time()) respond(['error'=>'Authentication required'],401);
    $u = query('SELECT id,tenant_id,email,role,ghl_user_id FROM users WHERE id=?',[$_SESSION['uid']])->fetch();
    if (!$u) respond(['error'=>'Authentication required'],401);
    if (str_starts_with($path, '/api/admin/integrations')) {
        if (getenv('APP_ENV') !== 'development' && parse_url(getenv('APP_ORIGIN') ?: '', PHP_URL_SCHEME) !== 'https') respond(['error'=>'HTTPS required'],503);
        require $appRoot.'/src/integrations.php';
    }
    if ($path === '/api/me' && $method === 'GET') respond(['user'=>$u,'csrf'=>$_SESSION['csrf']]);
    if ($path === '/api/logout' && $method === 'POST') { $_SESSION=[]; session_destroy(); respond(['ok'=>true]); }
    if ($path === '/api/conversations' && $method === 'GET') {
        $sql = 'SELECT ghl_id,payload,updated_at FROM resources WHERE tenant_id=? AND kind=?';
        $args = [$u['tenant_id'],'conversation'];
        if ($u['role'] === 'seller') {
            if (!$u['ghl_user_id']) respond(['items'=>[]]);
            $sql .= ' AND assigned_to=?'; $args[]=$u['ghl_user_id'];
        }
        $rows=query($sql.' ORDER BY updated_at DESC LIMIT 100',$args)->fetchAll();
        respond(['items'=>array_map(fn($r)=>['id'=>$r['ghl_id'],'data'=>json_decode($r['payload'],true),'updatedAt'=>$r['updated_at']],$rows)]);
    }
    respond(['error'=>'Not implemented'],404);
} catch (InvalidArgumentException|JsonException $e) { respond(['error'=>'Invalid request'],400);
} catch (Throwable $e) { error_log('API failure: '.get_class($e)); respond(['error'=>'Service unavailable'],503); }
