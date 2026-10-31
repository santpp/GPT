<?php
declare(strict_types=1);
require_once __DIR__.'/Actor.php';

/**
 * La sesión que dura en el dispositivo. La sesión normal del servidor caduca
 * pronto; esta cookie la rehace sin pedir otra vez la contraseña o el código.
 *
 * La cookie lleva un selector público y un secreto. En la base de datos sólo
 * queda la huella SHA-256 del secreto, así que una copia de la base no sirve
 * para entrar. El secreto cambia una vez al día; el anterior se acepta dos
 * minutos más para que dos pestañas que piden a la vez no cierren la sesión.
 */
final class DeviceSession {
    public const COOKIE='agora_device';
    public const DAYS=60;
    private const ROTATE_AFTER=86400;
    private const GRACE=120;
    public const LOCK_CHOICES=[5,15,30,60];

    /** Abre una sesión de dispositivo nueva tras entrar, y devuelve su id. */
    public static function start(array $session,string $csrf,string $userAgent):?int {
        [$type,$actor,$tenant]=self::identity($session);
        if($type===null)return null;
        $selector=self::encode(random_bytes(18));$secret=self::encode(random_bytes(32));
        try {
            query('INSERT INTO device_sessions(tenant_id,identity_type,actor_id,selector,verifier_hash,rotated_at,csrf,label,last_used_at,expires_at) VALUES(?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?)',
                [$tenant,$type,$actor,$selector,hash('sha256',$secret),time(),$csrf,self::label($userAgent),time()+self::DAYS*86400]);
        } catch(PDOException $e) {return null;}
        self::cookie($selector.'.'.$secret);
        return (int)db()->lastInsertId();
    }

    /**
     * Rehace la sesión del servidor desde la cookie. Devuelve el contenido de
     * $_SESSION, o null si la cookie no vale (y entonces la borra).
     */
    public static function resume(?string $cookie):?array {
        if($cookie===null||$cookie==='')return null;
        if(!preg_match('/^([A-Za-z0-9_-]{24})\.([A-Za-z0-9_-]{43})$/D',$cookie,$m)){self::forget();return null;}
        try {$row=query('SELECT * FROM device_sessions WHERE selector=?',[$m[1]])->fetch();}
        catch(PDOException $e) {return null;}
        $now=time();$given=hash('sha256',$m[2]);
        $current=$row&&hash_equals($row['verifier_hash'],$given);
        $previous=$row&&!$current&&$row['previous_hash']!==null&&hash_equals($row['previous_hash'],$given)&&(int)$row['rotated_at']>$now-self::GRACE;
        if(!$row||(!$current&&!$previous)||$row['revoked_at']!==null||(int)$row['expires_at']<$now){self::forget();return null;}
        $session=self::sessionFor($row);
        // Quien fue dado de baja en la cuenta deja de entrar aunque tenga la cookie.
        if(!Actor::fromSession($session)){query('UPDATE device_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE id=?',[$row['id']]);self::forget();return null;}
        if($current&&(int)$row['rotated_at']<$now-self::ROTATE_AFTER) {
            $secret=self::encode(random_bytes(32));
            query('UPDATE device_sessions SET previous_hash=verifier_hash,verifier_hash=?,rotated_at=?,expires_at=?,last_used_at=CURRENT_TIMESTAMP WHERE id=?',[hash('sha256',$secret),$now,$now+self::DAYS*86400,$row['id']]);
            self::cookie($row['selector'].'.'.$secret);
        } else query('UPDATE device_sessions SET last_used_at=CURRENT_TIMESTAMP WHERE id=?',[$row['id']]);
        return $session;
    }

    /** La sesión del servidor para una fila: el mismo csrf que ya tiene la página. */
    private static function sessionFor(array $row):array {
        $session=$row['identity_type']==='native'?['uid'=>(int)$row['actor_id']]:['external_identity_id'=>(int)$row['actor_id']];
        // Rehecha desde la cookie, con bloqueo activo, se pide la huella: no se
        // sabe cuánto tiempo estuvo la app sin usarse.
        return $session+['csrf'=>$row['csrf'],'expires'=>time()+28800,'device_id'=>(int)$row['id'],'seen'=>time(),'locked'=>$row['lock_minutes']!==null];
    }

    /**
     * Sigue viva la sesión de este dispositivo? Una baja hecha desde otro sitio
     * la corta aquí. De paso trae el bloqueo elegido, con una sola consulta.
     */
    public static function refresh(array &$session):bool {
        if(empty($session['device_id'])){$session['lock_minutes']=null;return true;}
        try {$row=query('SELECT revoked_at,expires_at,lock_minutes FROM device_sessions WHERE id=?',[(int)$session['device_id']])->fetch();}
        catch(PDOException $e) {return true;}
        if(!$row||$row['revoked_at']!==null||(int)$row['expires_at']<time())return false;
        $session['lock_minutes']=$row['lock_minutes']===null?null:(int)$row['lock_minutes'];
        return true;
    }

    /**
     * Aplica el bloqueo por inactividad. Devuelve true si la app debe pedir la
     * huella o la cara antes de seguir. Lo decide el servidor, no la página.
     */
    public static function locked(array &$session):bool {
        $minutes=$session['lock_minutes']??null;
        if($minutes===null){unset($session['locked']);$session['seen']=time();return false;}
        if(!empty($session['locked']))return true;
        if(time()-(int)($session['seen']??time())>$minutes*60){$session['locked']=true;return true;}
        $session['seen']=time();
        return false;
    }

    public static function setLock(array $session,?int $minutes):void {
        if(empty($session['device_id']))throw new InvalidArgumentException('no_device');
        if($minutes!==null&&!in_array($minutes,self::LOCK_CHOICES,true))throw new InvalidArgumentException('invalid_minutes');
        query('UPDATE device_sessions SET lock_minutes=? WHERE id=?',[$minutes,(int)$session['device_id']]);
    }

    /** Cierra la sesión de este dispositivo: la fila queda anulada y la cookie, borrada. */
    public static function end(array $session):void {
        if(!empty($session['device_id']))
            try{query('UPDATE device_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE id=? AND revoked_at IS NULL',[(int)$session['device_id']]);}catch(PDOException $e){}
        self::forget();
    }

    /**
     * Las sesiones abiertas. Cada persona ve las suyas; el administrador, las de
     * todo el equipo de su empresa.
     */
    public static function list(array $actor,bool $everyone,?int $current):array {
        $sql="SELECT d.id,d.identity_type,d.actor_id,d.label,d.lock_minutes,UNIX_TIMESTAMP(d.created_at) AS created_at,UNIX_TIMESTAMP(d.last_used_at) AS last_used_at,COALESCE(u.email,i.email) AS email
              FROM device_sessions d LEFT JOIN users u ON d.identity_type='native' AND u.id=d.actor_id LEFT JOIN external_identities i ON d.identity_type='external' AND i.id=d.actor_id
              WHERE d.tenant_id=? AND d.revoked_at IS NULL AND d.expires_at>=?";
        $args=[$actor['tenant_id'],time()];
        if(!$everyone){$sql.=' AND d.identity_type=? AND d.actor_id=?';$args[]=$actor['identity_type'];$args[]=$actor['id'];}
        $rows=query($sql.' ORDER BY d.last_used_at DESC,d.id DESC LIMIT 200',$args)->fetchAll();
        return array_map(fn($r)=>['id'=>(int)$r['id'],'label'=>$r['label']?:'Dispositivo','email'=>$r['email'],'locks'=>$r['lock_minutes']!==null,
            'createdAt'=>$r['created_at']?gmdate('c',(int)$r['created_at']):null,'lastUsedAt'=>$r['last_used_at']?gmdate('c',(int)$r['last_used_at']):null,'current'=>(int)$r['id']===$current,
            'mine'=>$r['identity_type']===$actor['identity_type']&&(int)$r['actor_id']===(int)$actor['id']],$rows);
    }

    /** Cierra una sesión concreta, propia o, para el administrador, de su equipo. */
    public static function revoke(array $actor,bool $everyone,int $id):bool {
        $sql='UPDATE device_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=? AND revoked_at IS NULL';
        $args=[$id,$actor['tenant_id']];
        if(!$everyone){$sql.=' AND identity_type=? AND actor_id=?';$args[]=$actor['identity_type'];$args[]=$actor['id'];}
        return query($sql,$args)->rowCount()>0;
    }

    /** Un nombre reconocible para la lista, sacado del navegador: «iPhone · Safari». */
    public static function label(string $agent):string {
        $device=match(true){
            (bool)preg_match('/iPhone/i',$agent)=>'iPhone',(bool)preg_match('/iPad/i',$agent)=>'iPad',
            (bool)preg_match('/Android/i',$agent)=>'Android',(bool)preg_match('/Macintosh|Mac OS X/i',$agent)=>'Mac',
            (bool)preg_match('/Windows/i',$agent)=>'Windows',(bool)preg_match('/Linux/i',$agent)=>'Linux',default=>'Dispositivo'};
        $browser=match(true){
            (bool)preg_match('/Edg\//',$agent)=>'Edge',(bool)preg_match('/SamsungBrowser/',$agent)=>'Samsung Internet',
            (bool)preg_match('/Firefox\/|FxiOS/',$agent)=>'Firefox',(bool)preg_match('/CriOS|Chrome\//',$agent)=>'Chrome',
            (bool)preg_match('/Safari\//',$agent)=>'Safari',default=>''};
        return $browser?$device.' · '.$browser:$device;
    }

    /** [tipo, id, empresa] de quien entra, o [null,null,null]. */
    public static function identity(array $session):array {
        $actor=Actor::fromSession($session);
        if(!$actor)return [null,null,null];
        return [$actor['identity_type'],(int)$actor['id'],(int)$actor['tenant_id']];
    }

    private static function cookie(string $value):void {
        if(headers_sent())return;
        setcookie(self::COOKIE,$value,['expires'=>time()+self::DAYS*86400,'path'=>'/','httponly'=>true,'secure'=>getenv('APP_ENV')!=='development','samesite'=>'Strict']);
    }

    public static function forget():void {
        if(headers_sent())return;
        setcookie(self::COOKIE,'',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'secure'=>getenv('APP_ENV')!=='development','samesite'=>'Strict']);
    }

    public static function encode(string $bytes):string {return rtrim(strtr(base64_encode($bytes),'+/','-_'),'=');}
}
