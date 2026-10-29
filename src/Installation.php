<?php
declare(strict_types=1);
require_once __DIR__.'/TokenVault.php';
final class Installation {
    public const VERSION='0.8.1';
    public static function checks():array {
        $checks=[];
        $runtime=PHP_VERSION_ID>=80300;
        foreach(['pdo_mysql','curl','openssl','mbstring'] as $module)$runtime=$runtime&&extension_loaded($module);
        $checks[]=['name'=>'runtime','ready'=>$runtime,'code'=>$runtime?null:'runtime_missing'];
        $origin=getenv('APP_ORIGIN')?:'';
        $originReady=(bool)filter_var($origin,FILTER_VALIDATE_URL) && (parse_url($origin,PHP_URL_SCHEME)==='https'||getenv('APP_ENV')==='development');
        $checks[]=['name'=>'origin','ready'=>$originReady,'code'=>$originReady?null:'origin_unconfigured'];
        try {
            $vault=TokenVault::fromEnvironment();$test=$vault->seal('installation-check',0,'installation');
            if($vault->open($test,0,'installation')!=='installation-check')throw new SetupException('encryption_key_invalid');
            $checks[]=['name'=>'encryption','ready'=>true,'code'=>null];
        }catch(SetupException $e){$checks[]=['name'=>'encryption','ready'=>false,'code'=>$e->issue];}
        try {
            query('SELECT id,tenant_id,name,location_id,token_ciphertext,sync_interval_seconds,status,last_checked_at FROM ghl_connections LIMIT 0');
            query('SELECT connection_id,enabled,next_sync_at,request_not_before,budget_day,requests_today,failures,last_success_at,last_http_status,last_error,quota,users_next_sync_at,users_last_success_at,users_last_error FROM ghl_sync_state LIMIT 0');
            query('SELECT connection_id,payload,total,synced_at FROM ghl_inbox_snapshots LIMIT 0');
            query('SELECT connection_id,inbox_limit FROM connection_preferences LIMIT 0');
            query('SELECT connection_id,request_id,conversation_id,request_hash,state,message_id,delivery_status,error_code FROM message_send_attempts LIMIT 0');
            query('SELECT connection_id,conversation_id,payload,last_message_id,has_more,fetched_at FROM conversation_message_cache LIMIT 0');
            query('SELECT connection_id,external_user_id,email,display_name,role_type,role_name,permissions,scopes,assigned_data_only,active,synced_at FROM external_users LIMIT 0');
            query('SELECT external_user_row_id,provider,provider_subject FROM external_identity_links LIMIT 0');
            query('SELECT id,tenant_id,email,verified_at,last_login_at FROM external_identities LIMIT 0');
            query('SELECT identity_id,external_user_row_id FROM external_identity_memberships LIMIT 0');
            query('SELECT identity_id,code_hash,attempts,expires_at,consumed_at FROM external_login_challenges LIMIT 0');
            query('SELECT connection_id,contact_id,payload,fetched_at FROM contact_profile_cache LIMIT 0');
            query('SELECT connection_id,payload,fetched_at FROM contact_field_cache LIMIT 0');
            query('SELECT tenant_id,connection_id FROM access_channels LIMIT 0');
            query('SELECT connection_id,email,contact_id,created_by_agora,tagged,last_delivery_at FROM access_contacts LIMIT 0');
            $checks[]=['name'=>'schema','ready'=>true,'code'=>null];
        }catch(PDOException $e){$checks[]=['name'=>'schema','ready'=>false,'code'=>in_array($e->getCode(),['42S02','42S22'],true)?'schema_pending':'database_unavailable'];}
        foreach($checks as &$check)$check['message']=$check['ready']?'Correcto':(SetupException::MESSAGES[$check['code']]??'Revisión necesaria');
        return $checks;
    }
    public static function applyMigrations():void {
        $lock='agora-install-migrations';
        if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)throw new SetupException('database_unavailable');
        try {
            // Only additive, reviewed migrations; never import/reset core data.
            foreach(['002-integrations.sql','003-api-sync.sql','004-messaging.sql','005-message-cache.sql','006-external-users.sql','007-external-auth.sql','008-contact-profile.sql','009-access-channel.sql'] as $migration) {
                $sql=file_get_contents(dirname(__DIR__).'/database/'.$migration);
                if($sql===false)throw new SetupException('schema_pending');
                db()->exec($sql);
            }
        }catch(PDOException $e){throw new SetupException('database_unavailable');}
        finally{query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public static function prepareEncryption():void {
        // Authenticated admin route only. No request parameter selects a path.
        $file=TokenVault::keyFile();TokenVault::assertPrivatePath($file);
        if(!extension_loaded('sodium'))throw new SetupException('encryption_module_missing');
        try {TokenVault::fromEnvironment();return;}
        catch(SetupException $e){if($e->issue!=='encryption_key_missing')throw $e;}
        $lock='agora-key-setup:'.substr(hash('sha256',$file),0,32);
        if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)throw new SetupException('encryption_key_unwritable');
        try {
            // Never replace the key protecting existing credentials.
            try {$stored=(int)query('SELECT COUNT(*) FROM ghl_connections')->fetchColumn();}
            catch(PDOException $e){throw new SetupException(in_array($e->getCode(),['42S02','42S22'],true)?'schema_pending':'database_unavailable');}
            if($stored>0)throw new SetupException('encryption_restore_required');
            if(file_exists($file)){TokenVault::fromEnvironment();return;}
            $directory=dirname($file);
            if(!is_dir($directory) && !@mkdir($directory,0700,true))throw new SetupException('encryption_key_unwritable');
            if(!is_writable($directory))throw new SetupException('encryption_key_unwritable');
            $temporary=@tempnam($directory,'.agora-key-');
            if($temporary===false)throw new SetupException('encryption_key_unwritable');
            try {
                if(!chmod($temporary,0600) || file_put_contents($temporary,base64_encode(random_bytes(32)),LOCK_EX)!==44)throw new SetupException('encryption_key_unwritable');
                // Atomic publish without overwriting another process's key.
                if(!@link($temporary,$file) && !is_file($file))throw new SetupException('encryption_key_unwritable');
            } finally {@unlink($temporary);}
            TokenVault::fromEnvironment();
        } finally {query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public static function assertReady():void {
        foreach(self::checks() as $check)if(!$check['ready'])throw new SetupException($check['code']);
    }
}
