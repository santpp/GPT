<?php
declare(strict_types=1);
// Included only after authentication. All object access is tenant scoped.
if ($u['role'] !== 'administrator') respond(['error'=>'Sólo el administrador puede configurar integraciones'],403);
require_once __DIR__.'/TokenVault.php';
require_once __DIR__.'/GhlClient.php';
if ($path === '/api/admin/integrations' && $method === 'GET') {
    $rows=query('SELECT id,name,location_id,sync_interval_seconds,status,last_checked_at FROM ghl_connections WHERE tenant_id=? ORDER BY id',[$u['tenant_id']])->fetchAll();
    foreach($rows as &$row)$row['tokenConfigured']=true;
    respond(['items'=>$rows,'syncImplemented'=>false]);
}
if (preg_match('~^/api/admin/integrations/(\d+)(/test)?$~',$path,$match)) {
    $row=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$match[1],$u['tenant_id']])->fetch();
    if(!$row) respond(['error'=>'Integración no encontrada'],404);
    if(isset($match[2]) && $method==='POST') {
        $key='connection-test:'.$u['tenant_id'].':'.$row['id'];
        $lock=query('SELECT GET_LOCK(?,0)',[$key])->fetchColumn();
        if((int)$lock!==1)respond(['error'=>'Comprobación en curso'],429);
        try {
            if($row['last_checked_at'] && strtotime($row['last_checked_at']) > time()-30) respond(['error'=>'Espera 30 segundos entre comprobaciones'],429);
            $token=TokenVault::fromEnvironment()->open($row['token_ciphertext'],(int)$u['tenant_id'],$row['location_id']);
            $client=new GhlClient($token);
            try {
                $client->checkLocation($row['location_id']);
                query("UPDATE ghl_connections SET status='connected',last_checked_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$row['id'],$u['tenant_id']]);
                respond(['status'=>'connected','message'=>'La subcuenta responde. Esto no valida todos los scopes ni activa la sincronización.']);
            } catch (Throwable $e) {
                query("UPDATE ghl_connections SET status='error',last_checked_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$row['id'],$u['tenant_id']]);
                respond(['error'=>'No se pudo verificar la subcuenta. Revisa Location ID, token, scope locations.readonly y conectividad del servidor.'],502);
            }
        } finally {query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
    if(!isset($match[2]) && $method==='DELETE') {query('DELETE FROM ghl_connections WHERE id=? AND tenant_id=?',[$row['id'],$u['tenant_id']]);respond(['ok'=>true]);}
}
if (($path==='/api/admin/integrations' && $method==='POST') || (isset($row) && !isset($match[2]) && $method==='PUT')) {
    $b=body();
    $name=$b['name'] ?? null;$location=$row['location_id'] ?? ($b['locationId'] ?? null);
    $interval=$b['syncIntervalSeconds'] ?? 120;
    $token=$b['token'] ?? '';
    if(!is_string($name)||trim($name)===''||mb_strlen($name)>160||!is_string($location)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$location)||!is_int($interval)||$interval<60||$interval>3600||!is_string($token)||strlen($token)>1024||($token!==''&&preg_match('/[\x00-\x20\x7f]/',$token)))respond(['error'=>'Revisa nombre, Location ID, token e intervalo (60–3600 segundos)'],422);
    if(!isset($row) && $token==='')respond(['error'=>'El token es obligatorio para añadir una subcuenta'],422);
    $vault=TokenVault::fromEnvironment();
    $cipher=$token!==''?$vault->seal($token,(int)$u['tenant_id'],$location):$row['token_ciphertext'];
    try {
        if(isset($row)) {
            query("UPDATE ghl_connections SET name=?,token_ciphertext=?,sync_interval_seconds=?,status=IF(?,'untested',status),last_checked_at=IF(?,NULL,last_checked_at) WHERE id=? AND tenant_id=?",[trim($name),$cipher,$interval,(int)($token!==''),(int)($token!==''),$row['id'],$u['tenant_id']]);
            $id=$row['id'];
        } else {
            query('INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,sync_interval_seconds) VALUES(?,?,?,?,?)',[$u['tenant_id'],trim($name),$location,$cipher,$interval]);$id=db()->lastInsertId();
        }
    } catch(PDOException $e) {if($e->getCode()==='23000')respond(['error'=>'Esta subcuenta ya está configurada'],409);throw $e;}
    // The plaintext credential is never returned or logged.
    respond(['id'=>$id,'saved'=>true,'tokenConfigured'=>true],isset($row)?200:201);
}
respond(['error'=>'Not found'],404);
