<?php
declare(strict_types=1);
require_once __DIR__.'/Actor.php';

final class PasskeyProblem extends RuntimeException {
    public function __construct(public readonly string $reason) {parent::__construct('passkey_'.$reason);}
}

/**
 * Lector CBOR (RFC 8949) mínimo: sólo lo que traen las llaves de acceso. Lee
 * enteros, bytes, texto, listas, mapas y true/false/null; cualquier otra cosa
 * se rechaza en vez de adivinarse.
 */
final class Cbor {
    public static function decode(string $data,int &$offset=0,int $depth=0):mixed {
        if($depth>16)throw new PasskeyProblem('cbor_too_deep');
        $initial=self::byte($data,$offset);
        $major=$initial>>5;$info=$initial&31;
        if($major===7) {
            return match($info){20=>false,21=>true,22=>null,default=>throw new PasskeyProblem('cbor_unsupported')};
        }
        $length=self::length($data,$offset,$info);
        switch($major) {
            case 0: return $length;
            case 1: return -1-$length;
            case 2: case 3:
                if($length>strlen($data)-$offset)throw new PasskeyProblem('cbor_truncated');
                $value=substr($data,$offset,$length);$offset+=$length;return $value;
            case 4:
                if($length>1024)throw new PasskeyProblem('cbor_too_large');
                $list=[];for($i=0;$i<$length;$i++)$list[]=self::decode($data,$offset,$depth+1);return $list;
            case 5:
                if($length>256)throw new PasskeyProblem('cbor_too_large');
                $map=[];
                for($i=0;$i<$length;$i++){$key=self::decode($data,$offset,$depth+1);if(!is_int($key)&&!is_string($key))throw new PasskeyProblem('cbor_key');$map[$key]=self::decode($data,$offset,$depth+1);}
                return $map;
        }
        throw new PasskeyProblem('cbor_unsupported');
    }
    private static function byte(string $data,int &$offset):int {
        if($offset>=strlen($data))throw new PasskeyProblem('cbor_truncated');
        return ord($data[$offset++]);
    }
    private static function length(string $data,int &$offset,int $info):int {
        if($info<24)return $info;
        $size=match($info){24=>1,25=>2,26=>4,27=>8,default=>throw new PasskeyProblem('cbor_unsupported')};
        if($offset+$size>strlen($data))throw new PasskeyProblem('cbor_truncated');
        $value=0;for($i=0;$i<$size;$i++)$value=($value<<8)|ord($data[$offset++]);
        if($value<0)throw new PasskeyProblem('cbor_too_large');
        return $value;
    }
}

/**
 * Llaves de acceso (WebAuthn nivel 2): entrar con huella, cara o el PIN del
 * teléfono. El teléfono firma un reto con una llave que nunca sale de él; aquí
 * sólo se guarda la parte pública y se comprueba la firma.
 *
 * Se exige siempre verificación del usuario (huella, cara o PIN), y que el
 * origen y el dominio sean los de Ágora: una web falsa no obtiene una firma
 * válida para este dominio.
 */
final class Passkeys {
    private const TTL=300;
    private const ES256=-7;
    private const RS256=-257;

    public static function origin():string {return rtrim((string)getenv('APP_ORIGIN'),'/');}
    public static function rpId():string {return (string)parse_url(self::origin(),PHP_URL_HOST);}

    /** Lo que el navegador necesita para crear una llave para quien ya entró. */
    public static function registrationOptions(array $actor,array &$session):array {
        $challenge=random_bytes(32);
        $session['webauthn']=['purpose'=>'register','challenge'=>self::b64($challenge),'at'=>time()];
        $existing=query('SELECT credential_id FROM passkeys WHERE tenant_id=? AND identity_type=? AND actor_id=?',[$actor['tenant_id'],$actor['identity_type'],$actor['id']])->fetchAll(PDO::FETCH_COLUMN);
        return [
            'challenge'=>self::b64($challenge),
            'rp'=>['name'=>'Ágora','id'=>self::rpId()],
            // Un identificador estable que no revela el correo ni el id interno.
            'user'=>['id'=>self::b64(hash_hmac('sha256',$actor['identity_type'].':'.$actor['id'],self::rpId().':'.$actor['tenant_id'],true)),'name'=>(string)$actor['email'],'displayName'=>(string)$actor['email']],
            'pubKeyCredParams'=>[['type'=>'public-key','alg'=>self::ES256],['type'=>'public-key','alg'=>self::RS256]],
            'authenticatorSelection'=>['residentKey'=>'required','requireResidentKey'=>true,'userVerification'=>'required'],
            'attestation'=>'none',
            'timeout'=>120000,
            'excludeCredentials'=>array_map(fn($id)=>['type'=>'public-key','id'=>$id],$existing),
        ];
    }

    /** Guarda la llave recién creada, tras comprobar que responde a nuestro reto. */
    public static function register(array $actor,array &$session,array $credential,string $label):int {
        $challenge=self::take($session,'register');
        $response=is_array($credential['response']??null)?$credential['response']:[];
        $clientJson=self::unb64($response['clientDataJSON']??null);
        self::client($clientJson,'webauthn.create',$challenge);
        $attestation=Cbor::decode(self::unb64($response['attestationObject']??null));
        if(!is_array($attestation)||!is_string($attestation['authData']??null))throw new PasskeyProblem('attestation');
        $auth=self::authData($attestation['authData'],true);
        $id=self::b64($auth['credentialId']);
        if(isset($credential['id'])&&$credential['id']!==$id)throw new PasskeyProblem('credential_mismatch');
        if(strlen($id)>512)throw new PasskeyProblem('credential_too_long');
        [$pem,$alg]=self::publicKey($auth['coseKey']);
        try {
            query('INSERT INTO passkeys(tenant_id,identity_type,actor_id,credential_id,public_key,algorithm,sign_count,label) VALUES(?,?,?,?,?,?,?,?)',
                [$actor['tenant_id'],$actor['identity_type'],$actor['id'],$id,$pem,$alg,$auth['signCount'],mb_substr($label!==''?$label:'Llave de acceso',0,120)]);
        } catch(PDOException $e) {throw new PasskeyProblem('already_registered');}
        return (int)db()->lastInsertId();
    }

    /** El reto para entrar o desbloquear. Sin lista de llaves: el teléfono ofrece las suyas. */
    public static function assertionOptions(array &$session,string $purpose,?array $actor=null):array {
        $challenge=random_bytes(32);
        $session['webauthn']=['purpose'=>$purpose,'challenge'=>self::b64($challenge),'at'=>time()];
        $options=['challenge'=>self::b64($challenge),'rpId'=>self::rpId(),'userVerification'=>'required','timeout'=>120000];
        if($actor) {
            $ids=query('SELECT credential_id FROM passkeys WHERE tenant_id=? AND identity_type=? AND actor_id=?',[$actor['tenant_id'],$actor['identity_type'],$actor['id']])->fetchAll(PDO::FETCH_COLUMN);
            $options['allowCredentials']=array_map(fn($id)=>['type'=>'public-key','id'=>$id],$ids);
        }
        return $options;
    }

    /**
     * Comprueba la firma y devuelve la sesión de quien es la llave. Con $actor,
     * la llave tiene que ser de esa persona (desbloqueo).
     */
    public static function verify(array &$session,string $purpose,array $credential,?array $actor=null):array {
        $challenge=self::take($session,$purpose);
        $id=is_string($credential['id']??null)?$credential['id']:'';
        if(!preg_match('/^[A-Za-z0-9_-]{16,512}$/D',$id))throw new PasskeyProblem('unknown_credential');
        $row=query('SELECT * FROM passkeys WHERE credential_id=?',[$id])->fetch();
        if(!$row)throw new PasskeyProblem('unknown_credential');
        if($actor&&((int)$row['tenant_id']!==(int)$actor['tenant_id']||$row['identity_type']!==$actor['identity_type']||(int)$row['actor_id']!==(int)$actor['id']))throw new PasskeyProblem('not_yours');
        $response=is_array($credential['response']??null)?$credential['response']:[];
        $clientJson=self::unb64($response['clientDataJSON']??null);
        self::client($clientJson,'webauthn.get',$challenge);
        $rawAuth=self::unb64($response['authenticatorData']??null);
        $auth=self::authData($rawAuth,false);
        $signature=self::unb64($response['signature']??null);
        $ok=openssl_verify($rawAuth.hash('sha256',$clientJson,true),$signature,$row['public_key'],OPENSSL_ALGO_SHA256);
        if($ok!==1)throw new PasskeyProblem('bad_signature');
        // Un contador que no avanza delata una llave copiada. Muchas llaves
        // sincronizadas (iCloud, Google) mandan siempre 0, y eso vale.
        if($auth['signCount']>0||(int)$row['sign_count']>0)
            if($auth['signCount']<=(int)$row['sign_count'])throw new PasskeyProblem('cloned');
        query('UPDATE passkeys SET sign_count=?,last_used_at=CURRENT_TIMESTAMP WHERE id=?',[$auth['signCount'],$row['id']]);
        $who=$row['identity_type']==='native'?['uid'=>(int)$row['actor_id']]:['external_identity_id'=>(int)$row['actor_id']];
        $person=Actor::fromSession($who);
        if(!$person||(int)$person['tenant_id']!==(int)$row['tenant_id'])throw new PasskeyProblem('inactive');
        return $who;
    }

    public static function list(array $actor,bool $everyone):array {
        $sql="SELECT p.id,p.identity_type,p.actor_id,p.label,UNIX_TIMESTAMP(p.created_at) AS created_at,UNIX_TIMESTAMP(p.last_used_at) AS last_used_at,COALESCE(u.email,i.email) AS email FROM passkeys p
              LEFT JOIN users u ON p.identity_type='native' AND u.id=p.actor_id LEFT JOIN external_identities i ON p.identity_type='external' AND i.id=p.actor_id WHERE p.tenant_id=?";
        $args=[$actor['tenant_id']];
        if(!$everyone){$sql.=' AND p.identity_type=? AND p.actor_id=?';$args[]=$actor['identity_type'];$args[]=$actor['id'];}
        return array_map(fn($r)=>['id'=>(int)$r['id'],'label'=>$r['label'],'email'=>$r['email'],'createdAt'=>$r['created_at']?gmdate('c',(int)$r['created_at']):null,'lastUsedAt'=>$r['last_used_at']?gmdate('c',(int)$r['last_used_at']):null,
            'mine'=>$r['identity_type']===$actor['identity_type']&&(int)$r['actor_id']===(int)$actor['id']],query($sql.' ORDER BY p.id DESC LIMIT 200',$args)->fetchAll());
    }

    public static function remove(array $actor,bool $everyone,int $id):bool {
        $sql='DELETE FROM passkeys WHERE id=? AND tenant_id=?';$args=[$id,$actor['tenant_id']];
        if(!$everyone){$sql.=' AND identity_type=? AND actor_id=?';$args[]=$actor['identity_type'];$args[]=$actor['id'];}
        return query($sql,$args)->rowCount()>0;
    }

    public static function count(array $actor):int {
        // Recién desplegado, antes de que la revisión aplique la migración, la
        // tabla puede no existir todavía: eso no puede tumbar la entrada.
        try {return (int)query('SELECT COUNT(*) FROM passkeys WHERE tenant_id=? AND identity_type=? AND actor_id=?',[$actor['tenant_id'],$actor['identity_type'],$actor['id']])->fetchColumn();}
        catch(PDOException $e) {return 0;}
    }

    /** Un reto sirve una vez, para lo que se pidió y durante cinco minutos. */
    private static function take(array &$session,string $purpose):string {
        $pending=$session['webauthn']??null;unset($session['webauthn']);
        if(!is_array($pending)||($pending['purpose']??'')!==$purpose||(int)($pending['at']??0)<time()-self::TTL)throw new PasskeyProblem('challenge_expired');
        return (string)$pending['challenge'];
    }

    private static function client(string $json,string $type,string $challenge):void {
        $data=json_decode($json,true);
        if(!is_array($data))throw new PasskeyProblem('client_data');
        if(($data['type']??null)!==$type)throw new PasskeyProblem('client_type');
        if(!is_string($data['challenge']??null)||!hash_equals($challenge,$data['challenge']))throw new PasskeyProblem('challenge_mismatch');
        if(($data['origin']??null)!==self::origin())throw new PasskeyProblem('origin_mismatch');
        if(($data['crossOrigin']??false)===true)throw new PasskeyProblem('cross_origin');
    }

    /** Los datos del autenticador: dominio, presencia, verificación y, al crear, la llave. */
    private static function authData(string $data,bool $withCredential):array {
        if(strlen($data)<37)throw new PasskeyProblem('auth_data');
        if(!hash_equals(hash('sha256',self::rpId(),true),substr($data,0,32)))throw new PasskeyProblem('rp_mismatch');
        $flags=ord($data[32]);
        if(!($flags&0x01))throw new PasskeyProblem('user_not_present');
        if(!($flags&0x04))throw new PasskeyProblem('user_not_verified');
        $count=unpack('N',substr($data,33,4))[1];
        $out=['signCount'=>(int)$count];
        if($withCredential) {
            if(!($flags&0x40)||strlen($data)<55)throw new PasskeyProblem('no_credential');
            $length=unpack('n',substr($data,53,2))[1];
            if($length<16||$length>1023||strlen($data)<55+$length)throw new PasskeyProblem('credential_length');
            $out['credentialId']=substr($data,55,$length);
            $offset=55+$length;
            $out['coseKey']=Cbor::decode($data,$offset);
        }
        return $out;
    }

    /** Llave COSE a PEM, sólo ES256 (P-256) y RS256. */
    private static function publicKey(mixed $cose):array {
        if(!is_array($cose))throw new PasskeyProblem('cose');
        $alg=$cose[3]??null;
        if($alg===self::ES256&&($cose[1]??null)===2&&($cose[-1]??null)===1&&is_string($cose[-2]??null)&&is_string($cose[-3]??null)&&strlen($cose[-2])===32&&strlen($cose[-3])===32) {
            $der=hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').chr(4).$cose[-2].$cose[-3];
            $pem=self::pem($der);
        } elseif($alg===self::RS256&&($cose[1]??null)===3&&is_string($cose[-1]??null)&&is_string($cose[-2]??null)&&strlen($cose[-1])>=256) {
            $pem=self::pem(self::rsaSpki($cose[-1],$cose[-2]));
        } else throw new PasskeyProblem('unsupported_key');
        if(openssl_pkey_get_public($pem)===false)throw new PasskeyProblem('cose');
        return [$pem,$alg];
    }

    private static function rsaSpki(string $n,string $e):string {
        $int=fn(string $v)=>"\x02".self::derLength(strlen(ord($v[0])>127?"\0".$v:$v)).(ord($v[0])>127?"\0".$v:$v);
        $sequence=fn(string $v)=>"\x30".self::derLength(strlen($v)).$v;
        $key=$sequence($int($n).$int($e));
        $bits="\x03".self::derLength(strlen($key)+1)."\0".$key;
        return $sequence(hex2bin('300d06092a864886f70d0101010500').$bits);
    }
    private static function derLength(int $length):string {
        if($length<128)return chr($length);
        $bytes=ltrim(pack('N',$length),"\0");
        return chr(0x80|strlen($bytes)).$bytes;
    }
    private static function pem(string $der):string {return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";}

    public static function b64(string $bytes):string {return rtrim(strtr(base64_encode($bytes),'+/','-_'),'=');}
    public static function unb64(mixed $value):string {
        if(!is_string($value)||$value===''||strlen($value)>16384||!preg_match('/^[A-Za-z0-9_-]+$/D',$value))throw new PasskeyProblem('encoding');
        $out=base64_decode(strtr($value,'-_','+/').str_repeat('=',(4-strlen($value)%4)%4),true);
        if($out===false)throw new PasskeyProblem('encoding');
        return $out;
    }
}
