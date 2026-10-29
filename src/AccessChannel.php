<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

final class AccessDeliveryProblem extends RuntimeException {
    public const MESSAGES=[
        'access_channel_unconfigured'=>'Todavía no hay un canal de acceso seleccionado. El administrador debe elegir una cuenta conectada para entregar los códigos.',
        'access_channel_unverified'=>'La cuenta elegida como canal de acceso no está comprobada. Compruébala antes de usarla para entregar códigos.',
        'member_without_email'=>'Este integrante no tiene un correo sincronizado, así que no puede recibir un código de acceso.',
        'contact_unresolvable'=>'No se ha podido preparar la ficha interna de acceso para este correo en la cuenta elegida.',
        'shared_access_contact'=>'Ese correo comparte ficha con otro integrante del equipo. Usa una dirección propia para cada persona.',
        'insufficient_permissions'=>'La clave de la cuenta elegida no tiene permisos suficientes para preparar contactos ni enviar correo.',
        'email_channel_unavailable'=>'La cuenta elegida no tiene el correo disponible para enviar mensajes en este momento.',
        'delivery_rejected'=>'El envío del código fue rechazado. Revisa la configuración de correo de la cuenta elegida.',
        'delivery_paused'=>'El envío está pausado temporalmente por el límite de peticiones. Espera unos segundos.',
    ];
    public function __construct(public readonly string $reason,public readonly int $retryAfter=0) {
        parent::__construct(self::MESSAGES[$reason] ?? 'No se ha podido entregar el código de acceso.');
    }
}

/**
 * Delivers the team access codes through the email service of a connected
 * account instead of a local SMTP server. The conversations email endpoint only
 * writes to a contact of that account, so every address is first resolved to an
 * internal access contact and that relation is persisted to avoid duplicates.
 */
final class AccessChannel {
    public const INTERNAL_TAG='agora-acceso-interno';
    private const SOURCE='Ágora · acceso interno';

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public static function connection(int $tenant):?array {
        $row=query('SELECT c.* FROM access_channels a JOIN ghl_connections c ON c.id=a.connection_id WHERE a.tenant_id=?',[$tenant])->fetch();
        return $row ?: null;
    }

    public static function configured(int $tenant):bool {
        $connection=self::connection($tenant);
        return $connection!==null && $connection['status']==='connected';
    }

    /** Selects, replaces or clears the account that delivers access codes. */
    public static function select(int $tenant,?int $connectionId):?array {
        if($connectionId===null) {query('DELETE FROM access_channels WHERE tenant_id=?',[$tenant]);return null;}
        $connection=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$connectionId,$tenant])->fetch();
        if(!$connection)throw new InvalidArgumentException('connection_not_found');
        query('INSERT INTO access_channels(tenant_id,connection_id) VALUES(?,?) ON DUPLICATE KEY UPDATE connection_id=VALUES(connection_id)',[$tenant,$connectionId]);
        return $connection;
    }

    public function sendAccessCode(int $tenant,string $email,string $code):void {
        if(!preg_match('/^\d{6}$/D',$code))throw new InvalidArgumentException('invalid_login_code');
        $this->deliver($tenant,$email,'Tu código de acceso a Ágora',
            self::template('Tu código de acceso','Introduce este código en Ágora para entrar con tu correo del equipo.',$code,'Caduca en 10 minutos y sólo puede utilizarse una vez. Si no has pedido este código, puedes ignorar este mensaje.'),
            "Tu código de acceso a Ágora es: $code\n\nCaduca en 10 minutos y sólo puede utilizarse una vez.\n");
    }

    public function sendTest(int $tenant,string $email):void {
        $this->deliver($tenant,$email,'Prueba del canal de acceso de Ágora',
            self::template('Canal de acceso comprobado','El canal de acceso de Ágora ha entregado este mensaje de prueba.',null,'Ya puedes pedir un código de acceso desde la pantalla de entrada del equipo.'),
            "El canal de acceso de Ágora ha entregado este mensaje de prueba.\n\nYa puedes probar el acceso del equipo.\n");
    }

    private function deliver(int $tenant,string $email,string $subject,string $html,string $text):void {
        $email=self::email($email);
        $connection=self::connection($tenant);
        if(!$connection)throw new AccessDeliveryProblem('access_channel_unconfigured');
        if($connection['status']!=='connected')throw new AccessDeliveryProblem('access_channel_unverified');
        $id=(int)$connection['id'];
        $known=query('SELECT contact_id,tagged FROM access_contacts WHERE connection_id=? AND email=?',[$id,$email])->fetch();
        try {
            // One gateway slot covers the whole delivery: the resolution calls and
            // the send share the connection lock and the per-second pause.
            $this->gateway->execute($connection,function(GhlClient $client)use($id,$email,$known,$subject,$html,$text,$connection):array {
                $contact=$known?$known['contact_id']:null;
                $tagged=$known?(bool)$known['tagged']:false;
                if($contact===null) {
                    [$contact,$created]=$this->resolve($client,$connection,$email);
                    self::remember($id,$email,$contact,$created);
                    $tagged=false;
                }
                if(!$tagged) {
                    try {$client->addContactTags($contact,[self::INTERNAL_TAG]);query('UPDATE access_contacts SET tagged=TRUE WHERE connection_id=? AND email=?',[$id,$email]);}
                    catch(GhlApiException $e){if(in_array($e->status,[401,403],true))throw new AccessDeliveryProblem('insufficient_permissions');}
                }
                $this->post($client,$id,$email,$contact,$subject,$html,$text);
                return [];
            },false);
        } catch(GhlCooldown $e) {throw new AccessDeliveryProblem('delivery_paused',$e->retryAfter);}
        catch(AccessDeliveryProblem $e) {throw $e;}
        catch(GhlApiException $e) {throw new AccessDeliveryProblem(in_array($e->status,[401,403],true)?'insufficient_permissions':'delivery_rejected',$e->status===429?max(30,$e->retryAfter):0);}
        catch(Throwable $e) {throw new AccessDeliveryProblem('delivery_rejected');}
    }

    /** Finds the existing contact for the address or creates the internal one. */
    private function resolve(GhlClient $client,array $connection,string $email):array {
        try {
            $found=$client->duplicateContact($connection['location_id'],$email);
            $contact=self::contactId($found['contact']??null,$connection);
            if($contact!==null)return [$contact,false];
        } catch(GhlApiException $e) {
            if(in_array($e->status,[401,403],true))throw new AccessDeliveryProblem('insufficient_permissions');
            if($e->status!==404 && $e->status!==400)throw $e;
        }
        try {
            $created=$client->upsertContact(['locationId'=>$connection['location_id'],'email'=>$email,'name'=>'Acceso Ágora','source'=>self::SOURCE]);
        } catch(GhlApiException $e) {
            if(in_array($e->status,[401,403],true))throw new AccessDeliveryProblem('insufficient_permissions');
            throw new AccessDeliveryProblem('contact_unresolvable');
        }
        $contact=self::contactId($created['contact']??null,$connection);
        if($contact===null)throw new AccessDeliveryProblem('contact_unresolvable');
        return [$contact,($created['new']??false)===true];
    }

    private function post(GhlClient $client,int $id,string $email,string $contact,string $subject,string $html,string $text):void {
        try {
            // The recipient is the resolved contact of this account, never a raw address.
            $response=$client->sendMessage(['type'=>'Email','contactId'=>$contact,'subject'=>$subject,'html'=>$html,'message'=>$text]);
        } catch(GhlApiException $e) {
            if(in_array($e->status,[401,403],true))throw new AccessDeliveryProblem('insufficient_permissions');
            if($e->status===404) {
                // The stored contact no longer exists upstream; the next attempt resolves again.
                query('DELETE FROM access_contacts WHERE connection_id=? AND email=?',[$id,$email]);
                throw new AccessDeliveryProblem('contact_unresolvable');
            }
            if(in_array($e->status,[400,422],true))throw new AccessDeliveryProblem('email_channel_unavailable');
            throw new AccessDeliveryProblem('delivery_rejected',$e->status===429?max(30,$e->retryAfter):0);
        }
        if(!is_string($response['messageId']??null)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$response['messageId']))throw new AccessDeliveryProblem('delivery_rejected');
        query('UPDATE access_contacts SET last_delivery_at=CURRENT_TIMESTAMP WHERE connection_id=? AND email=?',[$id,$email]);
    }

    private static function remember(int $id,string $email,string $contact,bool $created):void {
        try {
            query('INSERT INTO access_contacts(connection_id,email,contact_id,created_by_agora) VALUES(?,?,?,?)',[$id,$email,$contact,(int)$created]);
        } catch(PDOException $e) {
            if($e->getCode()!=='23000')throw $e;
            // Another address of this account already owns the resolved contact.
            $owner=query('SELECT email FROM access_contacts WHERE connection_id=? AND contact_id=?',[$id,$contact])->fetchColumn();
            if(is_string($owner)&&$owner!==$email)throw new AccessDeliveryProblem('shared_access_contact');
            $stored=query('SELECT contact_id FROM access_contacts WHERE connection_id=? AND email=?',[$id,$email])->fetchColumn();
            if($stored!==$contact)throw new AccessDeliveryProblem('contact_unresolvable');
        }
    }

    private static function contactId(mixed $contact,array $connection):?string {
        if(!is_array($contact))return null;
        $id=$contact['id']??null;
        if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))return null;
        $location=$contact['locationId']??null;
        if(is_string($location)&&$location!==$connection['location_id'])return null;
        return $id;
    }

    private static function email(string $email):string {
        $email=mb_strtolower(trim($email));
        if($email===''||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new AccessDeliveryProblem('member_without_email');
        return $email;
    }

    /** Ágora-branded Spanish template. It never names the underlying provider. */
    private static function template(string $title,string $intro,?string $code,string $note):string {
        $esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $block=$code===null?'':'<p style="margin:0 0 24px;padding:18px 22px;background:#efefef;border-radius:8px;border:1px solid #e5e4e9;font:600 30px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:8px;color:#282e49;text-align:center">'.$esc($code).'</p>';
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>'.$esc($title).'</title></head>'
            .'<body style="margin:0;padding:24px;background:#f7f7fa;font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#282e49">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e5e4e9;border-radius:12px"><tr><td style="padding:32px">'
            .'<p style="margin:0 0 4px;font:600 11px/1 system-ui;letter-spacing:2px;color:#ad103c">ÁGORA DIGITAL</p>'
            .'<h1 style="margin:0 0 16px;font:600 22px/1.3 system-ui;color:#282e49">'.$esc($title).'</h1>'
            .'<p style="margin:0 0 24px;font:14px/1.7 system-ui;color:#5b6170">'.$esc($intro).'</p>'
            .$block
            .'<p style="margin:0;font:12px/1.7 system-ui;color:#7d8580">'.$esc($note).'</p>'
            .'</td></tr><tr><td style="padding:0 32px 28px"><p style="margin:0;font:11px/1.6 system-ui;color:#a1a6b2;border-top:1px solid #e5e4e9;padding-top:16px">Mensaje automático de Ágora. No respondas a este correo.</p></td></tr></table>'
            .'</body></html>';
    }
}
