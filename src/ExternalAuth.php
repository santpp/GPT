<?php
declare(strict_types=1);
require_once __DIR__.'/AccessChannel.php';

final class ExternalAuth {
    // Request limits per window: combined address and origin, address alone, origin alone.
    private const LIMITS=['request'=>[5,5,20],'verify'=>[10,10,40]];
    private Closure $deliver;
    public function __construct(?Closure $deliver=null) {
        $this->deliver=$deliver??static function(int $tenant,string $email,string $code):void {
            (new AccessChannel())->sendAccessCode($tenant,$email,$code);
        };
    }

    public static function configured(int $tenant):bool {
        return AccessChannel::configured($tenant);
    }

    public function request(string $email,string $remoteAddress):void {
        $email=$this->email($email);
        $this->throttle($email,$remoteAddress,'request');
        $rows=query('SELECT DISTINCT c.tenant_id FROM external_users eu JOIN ghl_connections c ON c.id=eu.connection_id WHERE eu.active=TRUE AND eu.email=?',[$email])->fetchAll();
        if(count($rows)!==1){password_verify('000000','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');return;}
        $tenant=(int)$rows[0]['tenant_id'];
        query('INSERT INTO external_identities(tenant_id,email) VALUES(?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',[$tenant,$email]);
        $identity=(int)db()->lastInsertId();
        $this->syncMemberships($identity,$tenant,$email);
        query('UPDATE external_login_challenges SET consumed_at=CURRENT_TIMESTAMP WHERE identity_id=? AND consumed_at IS NULL',[$identity]);
        $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        query('INSERT INTO external_login_challenges(identity_id,code_hash,expires_at) VALUES(?,?,?)',[$identity,password_hash($code,PASSWORD_DEFAULT),time()+600]);
        $challenge=(int)db()->lastInsertId();
        try {($this->deliver)($tenant,$email,$code);}
        catch(Throwable $error) {
            // An undelivered code must not stay valid.
            query('DELETE FROM external_login_challenges WHERE id=?',[$challenge]);
            throw $error;
        }
    }

    public function verify(string $email,string $code,string $remoteAddress):int {
        $email=$this->email($email);
        if(!preg_match('/^\d{6}$/D',$code))throw new InvalidArgumentException('invalid_login_code');
        $this->throttle($email,$remoteAddress,'verify');
        db()->beginTransaction();
        try {
            $challenge=query('SELECT ch.*,i.tenant_id,i.email FROM external_login_challenges ch JOIN external_identities i ON i.id=ch.identity_id WHERE i.email=? AND ch.consumed_at IS NULL ORDER BY ch.id DESC LIMIT 1 FOR UPDATE',[$email])->fetch();
            $valid=$challenge&&(int)$challenge['expires_at']>=time()&&(int)$challenge['attempts']<5&&password_verify($code,$challenge['code_hash']);
            if(!$valid){if($challenge)query('UPDATE external_login_challenges SET attempts=attempts+1 WHERE id=?',[$challenge['id']]);db()->commit();throw new InvalidArgumentException('invalid_login_code');}
            $identity=(int)$challenge['identity_id'];
            $this->syncMemberships($identity,(int)$challenge['tenant_id'],$email);
            if(!(bool)query('SELECT 1 FROM external_identity_memberships m JOIN external_users eu ON eu.id=m.external_user_row_id WHERE m.identity_id=? AND eu.active=TRUE LIMIT 1',[$identity])->fetchColumn()){
                query('UPDATE external_login_challenges SET consumed_at=CURRENT_TIMESTAMP WHERE id=?',[$challenge['id']]);db()->commit();throw new InvalidArgumentException('inactive_external_user');
            }
            query('UPDATE external_login_challenges SET consumed_at=CURRENT_TIMESTAMP WHERE id=?',[$challenge['id']]);
            query('UPDATE external_identities SET verified_at=COALESCE(verified_at,CURRENT_TIMESTAMP),last_login_at=CURRENT_TIMESTAMP WHERE id=?',[$identity]);
            db()->commit();return $identity;
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }

    private function syncMemberships(int $identity,int $tenant,string $email):void {
        query('DELETE m FROM external_identity_memberships m JOIN external_users eu ON eu.id=m.external_user_row_id JOIN ghl_connections c ON c.id=eu.connection_id WHERE m.identity_id=? AND (c.tenant_id<>? OR eu.email<>?)',[$identity,$tenant,$email]);
        query('INSERT IGNORE INTO external_identity_memberships(identity_id,external_user_row_id) SELECT ?,eu.id FROM external_users eu JOIN ghl_connections c ON c.id=eu.connection_id WHERE c.tenant_id=? AND eu.email=?',[$identity,$tenant,$email]);
    }

    private function email(string $email):string {
        $email=mb_strtolower(trim($email));
        if(strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('invalid_email');
        return $email;
    }

    /** Independent windows stop both a single address and a single origin from flooding deliveries. */
    private function throttle(string $email,string $remoteAddress,string $action):void {
        [$pair,$byEmail,$byAddress]=self::LIMITS[$action];
        foreach([self::key($remoteAddress.'|external-'.$action.'|'.$email)=>$pair,
                 self::key('email|external-'.$action.'|'.$email)=>$byEmail,
                 self::key('origin|external-'.$action.'|'.$remoteAddress)=>$byAddress] as $key=>$limit) {
            query('INSERT INTO login_attempts(key_hash,attempts,window_start) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start < UNIX_TIMESTAMP()-900,1,attempts+1),window_start=IF(window_start < UNIX_TIMESTAMP()-900,UNIX_TIMESTAMP(),window_start)',[$key,time()]);
            if((int)query('SELECT attempts FROM login_attempts WHERE key_hash=?',[$key])->fetchColumn()>$limit)throw new OverflowException('login_rate_limited');
        }
    }

    public static function key(string $value):string {return hash('sha256',$value);}
}
