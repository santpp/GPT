<?php
declare(strict_types=1);
// Included only after authentication. All object access is tenant scoped.
if ($u['role'] !== 'administrator') respond(['error'=>'Sólo el administrador puede configurar integraciones'],403);
require_once __DIR__.'/TokenVault.php';
require_once __DIR__.'/InboxSync.php';
require_once __DIR__.'/Installation.php';
require_once __DIR__.'/ExternalUserDirectory.php';
require_once __DIR__.'/PermissionPolicy.php';
require_once __DIR__.'/ExternalAuth.php';
require_once __DIR__.'/AccessChannel.php';
Installation::assertReady();
if ($path === '/api/admin/integrations' && $method === 'GET') {
    $rows=query('SELECT c.id,c.name,c.location_id,c.sync_interval_seconds,c.status,c.last_checked_at,COALESCE(s.enabled,0) AS sync_enabled,s.last_success_at,s.last_error,s.requests_today,s.quota,s.users_last_success_at,s.users_last_error FROM ghl_connections c LEFT JOIN ghl_sync_state s ON s.connection_id=c.id WHERE c.tenant_id=? ORDER BY c.id',[$u['tenant_id']])->fetchAll();
    foreach($rows as &$row){$row['tokenConfigured']=true;$row['externalUsers']=(int)query('SELECT COUNT(*) FROM external_users WHERE connection_id=? AND active=TRUE',[$row['id']])->fetchColumn();}
    $channel=AccessChannel::connection((int)$u['tenant_id']);
    foreach($rows as &$row)$row['accessChannel']=$channel&&(int)$channel['id']===(int)$row['id'];
    respond(['items'=>$rows,'syncImplemented'=>true,'syncWindows'=>[20,50,100],'accessChannel'=>['connectionId'=>$channel?(int)$channel['id']:null,'ready'=>AccessChannel::configured((int)$u['tenant_id'])]]);
}
if ($path === '/api/admin/access-channel' && in_array($method,['GET','PUT'],true)) {
    if($method==='PUT') {
        $selected=body()['connectionId']??null;
        if(!is_int($selected)&&$selected!==null)respond(['error'=>'Selecciona una cuenta conectada o deja el canal sin asignar'],422);
        try{AccessChannel::select((int)$u['tenant_id'],$selected);}
        catch(InvalidArgumentException $e){respond(['error'=>'Integración no encontrada'],404);}
    }
    $channel=AccessChannel::connection((int)$u['tenant_id']);
    respond([
        'connectionId'=>$channel?(int)$channel['id']:null,
        'name'=>$channel?$channel['name']:null,
        'status'=>$channel?$channel['status']:null,
        'ready'=>AccessChannel::configured((int)$u['tenant_id']),
        'internalTag'=>AccessChannel::INTERNAL_TAG,
        'accessContacts'=>$channel?(int)query('SELECT COUNT(*) FROM access_contacts WHERE connection_id=?',[$channel['id']])->fetchColumn():0,
    ]);
}
if ($path === '/api/admin/access-channel/test' && $method === 'POST') {
    // The administrator keeps native password access, so the probe targets their own address.
    session_write_close();
    try{(new AccessChannel())->sendTest((int)$u['tenant_id'],(string)$u['email']);respond(['sent'=>true]);}
    catch(AccessDeliveryProblem $e){if($e->retryAfter)header('Retry-After: '.$e->retryAfter);respond(['error'=>$e->getMessage(),'code'=>$e->reason],$e->reason==='delivery_paused'?429:424);}
}
if(preg_match('~^/api/admin/integrations/(\d+)/users(?:/(sync))?$~',$path,$userMatch)) {
    $connection=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$userMatch[1],$u['tenant_id']])->fetch();
    if(!$connection)respond(['error'=>'Integración no encontrada'],404);
    if(($userMatch[2]??'')==='sync'&&$method==='POST'){
        session_write_close();
        try{respond(['synced'=>(new ExternalUserDirectory())->sync($connection)]);}
        catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'Sincronización de equipo pausada','retryAfter'=>$e->retryAfter],429);}
        catch(InvalidArgumentException $e){respond(['error'=>'Comprueba la conexión antes de importar el equipo'],422);}
        catch(GhlApiException $e){respond(['error'=>'No se pudo importar el equipo. Revisa el permiso users.readonly.','code'=>'users_upstream_'.$e->status],424);}
        catch(Throwable $e){respond(['error'=>'La respuesta de usuarios no tiene el formato esperado.','code'=>'users_invalid_response'],424);}
    }
    if(($userMatch[2]??'')===''&&$method==='GET'){
        $rows=query('SELECT id,external_user_id,email,display_name,role_type,role_name,permissions,scopes,assigned_data_only,active,synced_at FROM external_users WHERE connection_id=? ORDER BY active DESC,display_name',[$connection['id']])->fetchAll();
        foreach($rows as &$row){$row['identityLinked']=(bool)query('SELECT 1 FROM external_identity_memberships WHERE external_user_row_id=? LIMIT 1',[$row['id']])->fetchColumn();$row['capabilities']=PermissionPolicy::capabilities($row+['identity_type'=>'external']);unset($row['permissions'],$row['scopes']);}
        respond(['items'=>$rows,'authenticationConfigured'=>ExternalAuth::configured((int)$u['tenant_id'])]);
    }
    respond(['error'=>'Not found'],404);
}
if (preg_match('~^/api/admin/integrations/(\d+)(/test|/sync|/schedule)?$~',$path,$match)) {
    $row=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$match[1],$u['tenant_id']])->fetch();
    if(!$row) respond(['error'=>'Integración no encontrada'],404);
    if((!isset($match[2]) && in_array($method,['PUT','DELETE'],true)) || (($match[2]??'')==='/schedule' && $method==='POST')) {
        $mutationKeys=['agora-sync:'.$row['id'],'agora-api:'.$row['id']];
        foreach($mutationKeys as $mutationKey)if((int)query('SELECT GET_LOCK(?,0)',[$mutationKey])->fetchColumn()!==1)respond(['error'=>'Hay una operación en curso; vuelve a intentarlo'],409);
        $row=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$match[1],$u['tenant_id']])->fetch();
        if(!$row)respond(['error'=>'Integración no encontrada'],404);
        // Named locks are released when this non-persistent DB session closes.
    }

    if(($match[2]??'')==='/test' && $method==='POST') {
        $key='connection-test:'.$u['tenant_id'].':'.$row['id'];
        $lock=query('SELECT GET_LOCK(?,0)',[$key])->fetchColumn();
        if((int)$lock!==1)respond(['error'=>'Comprobación en curso'],429);
        try {
            if($row['last_checked_at'] && strtotime($row['last_checked_at']) > time()-30) respond(['error'=>'Espera 30 segundos entre comprobaciones'],429);
            $gateway=new GhlGateway();
            try {
                $gateway->execute($row,function(GhlClient $client)use($row):array {$client->checkLocation($row['location_id']);return [];});
                $updated=query("UPDATE ghl_connections SET status='connected',last_checked_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=? AND token_ciphertext=?",[$row['id'],$u['tenant_id'],$row['token_ciphertext']]);
                if($updated->rowCount()===0)respond(['error'=>'La credencial cambió durante la comprobación; vuelve a comprobarla'],409);
                respond(['status'=>'connected','message'=>'La cuenta responde correctamente. Ya puedes probar la sincronización.']);
            } catch (GhlCooldown $e) {header('Retry-After: '.$e->retryAfter);respond(['error'=>'Consulta pausada por presupuesto de API','retryAfter'=>$e->retryAfter],429);
            } catch (Throwable $e) {
                query("UPDATE ghl_connections SET status='error',last_checked_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=? AND token_ciphertext=?",[$row['id'],$u['tenant_id'],$row['token_ciphertext']]);
                respond(['error'=>'No se pudo verificar la cuenta. Revisa el identificador, la clave y los permisos de conexión.'],502);
            }
        } finally {query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
    if(($match[2]??'')==='/sync' && $method==='POST') {
        $limit=isset($_GET['limit'])?filter_var($_GET['limit'],FILTER_VALIDATE_INT):null;
        if($limit!==null&&!in_array($limit,[20,50,100],true))respond(['error'=>'Selecciona 20, 50 o 100 conversaciones'],422);
        session_write_close();
        try {respond(['synced'=>(new InboxSync())->sync($row,$limit)]);}
        catch(GhlCooldown $e){header('Retry-After: '.$e->retryAfter);respond(['error'=>'Sincronización pausada; espera antes de repetir','retryAfter'=>$e->retryAfter],429);}
        catch(InvalidArgumentException $e){respond(['error'=>'Comprueba la conexión antes de sincronizar'],422);}
        catch(Throwable $e){respond(['error'=>'No se pudo actualizar la bandeja. Revisa el estado de la conexión.'],502);}
    }
    if(($match[2]??'')==='/schedule' && $method==='POST') {
        $enabled=body()['enabled']??null;
        if(!is_bool($enabled))respond(['error'=>'enabled debe ser booleano'],422);
        $lastError=query('SELECT last_error FROM ghl_sync_state WHERE connection_id=?',[$row['id']])->fetchColumn();
        if($enabled && ($row['status']!=='connected' || $lastError==='authentication_or_scope'))respond(['error'=>'Comprueba la conexión primero'],422);
        query('INSERT INTO ghl_sync_state(connection_id,enabled) VALUES(?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)',[$row['id'],(int)$enabled]);
        respond(['enabled'=>$enabled,'requiresServerCron'=>true]);
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
        db()->beginTransaction();
        if(isset($row)) {
            query("UPDATE ghl_connections SET name=?,token_ciphertext=?,sync_interval_seconds=?,status=IF(?,'untested',status),last_checked_at=IF(?,NULL,last_checked_at) WHERE id=? AND tenant_id=?",[trim($name),$cipher,$interval,(int)($token!==''),(int)($token!==''),$row['id'],$u['tenant_id']]);
            $id=$row['id'];
            if($token!==''){query('DELETE FROM ghl_inbox_snapshots WHERE connection_id=?',[$id]);query('UPDATE ghl_sync_state SET enabled=FALSE,last_success_at=NULL,last_error=NULL WHERE connection_id=?',[$id]);}
        } else {
            query('INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,sync_interval_seconds) VALUES(?,?,?,?,?)',[$u['tenant_id'],trim($name),$location,$cipher,$interval]);$id=db()->lastInsertId();
        }
        db()->commit();
    } catch(PDOException $e) {if(db()->inTransaction())db()->rollBack();if($e->getCode()==='23000')respond(['error'=>'Esta subcuenta ya está configurada'],409);throw $e;}
    // The plaintext credential is never returned or logged.
    respond(['id'=>$id,'saved'=>true,'tokenConfigured'=>true],isset($row)?200:201);
}
respond(['error'=>'Not found'],404);
