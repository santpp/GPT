<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
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

    /** La subcuenta comprobada en la que esta persona está activa como integrante, si hay una. */
    public static function connectionFor(int $tenant,string $email):?array {
        $row=query("SELECT c.* FROM external_users eu JOIN ghl_connections c ON c.id=eu.connection_id WHERE c.tenant_id=? AND eu.active=TRUE AND eu.email=? AND c.status='connected' ORDER BY c.id LIMIT 1",[$tenant,strtolower(trim($email))])->fetch();
        return $row?:null;
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

    /**
     * Agora New App 2.13.0 · Correo general del ERP (código de acceso, cambio de clave y lo que venga):
     * siempre sale por la subcuenta que el dueño eligió en Agencia → Correo de Ágora, nunca por otra.
     */
    public function sendMail(int $tenant,string $email,string $subject,string $title,string $intro,?string $code,string $foot,string $text,?string $logo=null):void {
        $this->deliver($tenant,$email,$subject,self::template($title,$intro,$code,$foot,$logo),$text,true);
    }

    private function deliver(int $tenant,string $email,string $subject,string $html,string $text,bool $onlyChannel=false):void {
        $email=self::email($email);
        // El código sale desde la subcuenta a la que pertenece la persona; la cuenta elegida como canal queda de respaldo.
        // Agora New App 2.13.0: con $onlyChannel sale siempre por la cuenta elegida como canal.
        $connection=$onlyChannel?self::connection($tenant):(self::connectionFor($tenant,$email)??self::connection($tenant));
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

    /**
     * Ágora-branded Spanish template. It never names the underlying provider.
     * Agora New App 2.18.14 · Con el logo (PNG en una dirección pública: los programas de correo no muestran SVG ni imágenes incrustadas)
     * y el código escrito como lo reconocen Gmail y los avisos del teléfono para ofrecer «Copiar código»: en el texto oculto de vista previa,
     * en la primera frase y solo, en un bloque grande que se selecciona entero con un toque. Un correo no puede llevar un botón propio de copiar
     * (los programas de correo no ejecutan nada), así que se deja todo listo para el botón del propio programa.
     */
    private static function template(string $title,string $intro,?string $code,string $note,?string $logo=null):string {
        $esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $f='font-family:Helvetica,Arial,system-ui,-apple-system,Segoe UI,sans-serif;';
        $pre=$code===null?$intro:'Tu código de acceso es '.$code.'. '.$intro;
        $marca=$logo!==null&&preg_match('#^https://[^\s"<>]+$#',$logo)
            ?'<img src="'.$esc($logo).'" width="132" height="55" alt="Ágora" style="display:block;width:132px;height:auto;border:0;outline:none">'
            :'<span style="'.$f.'font-size:22px;font-weight:700;color:#ad103c;letter-spacing:.5px">Ágora</span>';
        $block=$code===null?'':
            '<p style="margin:0 0 6px;'.$f.'font-size:13px;line-height:1.4;color:#5b6170">Tu código de acceso es</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 10px"><tr><td align="center" style="padding:20px 12px;background:#f4f2f6;border:1px solid #e5e4e9;border-radius:14px">'
            .'<span style="'.$f.'font-size:38px;line-height:1;font-weight:700;letter-spacing:10px;color:#282e49;-webkit-user-select:all;user-select:all;white-space:nowrap">'.$esc($code).'</span>'
            .'</td></tr></table>'
            .'<p style="margin:0 0 24px;'.$f.'font-size:12px;line-height:1.5;color:#7d8590;text-align:center">Mantén pulsado el número para copiarlo.</p>';
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>'.$esc($title).'</title></head>'
            .'<body style="margin:0;padding:0;background:#f7f7fa">'
            .'<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">'.$esc($pre).'</div>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7fa"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e5e4e9;border-radius:16px">'
            .'<tr><td style="padding:28px 28px 0">'.$marca.'</td></tr>'
            .'<tr><td style="padding:22px 28px 8px">'
            .'<h1 style="margin:0 0 10px;'.$f.'font-size:22px;line-height:1.3;font-weight:700;color:#282e49">'.$esc($title).'</h1>'
            .'<p style="margin:0 0 22px;'.$f.'font-size:15px;line-height:1.6;color:#5b6170">'.$esc($intro).'</p>'
            .$block
            .'<p style="margin:0 0 24px;'.$f.'font-size:13px;line-height:1.6;color:#7d8590">'.$esc($note).'</p>'
            .'</td></tr>'
            .'<tr><td style="padding:0 28px 24px"><p style="margin:0;padding-top:16px;border-top:1px solid #e5e4e9;'.$f.'font-size:11px;line-height:1.6;color:#a1a6b2">Mensaje automático de Ágora Digital. No respondas a este correo.</p></td></tr>'
            .'</table></td></tr></table>'
            .'</body></html>';
    }
}
