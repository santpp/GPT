<?php
declare(strict_types=1);

/**
 * What is worth telling someone about, and who is to be told.
 *
 * A notice is prepared by the shared pass that discovered the message, keyed by
 * the message itself, so however many passes see it the notice exists once. Who
 * receives it is resolved at delivery, from the team of that connection and the
 * same rule the screens use: a member limited to their own data is only told
 * about conversations assigned to them.
 */
final class PushQueue {
    private const BODY=300;

    /** Queues one notice per conversation whose unread count really grew. */
    public static function queue(array $connection,array $items,array $before,array $assignedBefore=[]):void {
        // Con más de una subcuenta, el aviso dice de cuál es.
        $row=query('SELECT c.name,(SELECT COUNT(*) FROM ghl_connections x WHERE x.tenant_id=c.tenant_id) AS n FROM ghl_connections c WHERE c.id=?',[(int)$connection['id']])->fetch();
        $account=$row&&(int)$row['n']>1?trim((string)$row['name']):'';
        foreach($items as $conversation) {
            if(!is_array($conversation)||!is_string($conversation['id']??null))continue;
            // «Te asignaron una conversación»: una conversación ya conocida que pasa a otra persona.
            $now=is_string($conversation['assignedTo']??null)?substr($conversation['assignedTo'],0,80):null;
            if($now!==null&&array_key_exists($conversation['id'],$assignedBefore)&&$assignedBefore[$conversation['id']]!==$now) {
                [$who,$text]=self::describe($conversation,1);
                $title=mb_substr(($account!==''?mb_substr($account,0,40).' · ':'').'Te asignaron: '.$who,0,190);
                query("INSERT IGNORE INTO push_queue(connection_id,conversation_id,fingerprint,title,body,assigned_to,kind,target) VALUES(?,?,?,?,?,?,'assigned',?)",
                    [(int)$connection['id'],$conversation['id'],md5(json_encode(['assigned',$conversation['id'],$assignedBefore[$conversation['id']],$now,intdiv(time(),300)])),$title,$text,$now,$now]);
            }
            $unread=max(0,(int)($conversation['unreadCount']??0));
            $was=$before[$conversation['id']]??null;
            // A conversation already known and not more unread than before is not news.
            if($unread<1||($was!==null&&$unread<=$was))continue;
            $fingerprint=md5(json_encode([$conversation['id'],$conversation['lastMessageDate']??null,$unread],JSON_THROW_ON_ERROR));
            [$name,$body]=self::describe($conversation,$unread);
            if($account!=='')$name=mb_substr(mb_substr($account,0,40).' · '.$name,0,190);
            $assigned=is_string($conversation['assignedTo']??null)?substr($conversation['assignedTo'],0,80):null;
            query('INSERT IGNORE INTO push_queue(connection_id,conversation_id,fingerprint,title,body,assigned_to) VALUES(?,?,?,?,?,?)',
                [(int)$connection['id'],$conversation['id'],$fingerprint,$name,$body,$assigned]);
        }
    }

    /**
     * Recordatorio de lo que lleva rato sin leerse: pasados los minutos que cada subcuenta fija, un mensaje entrante
     * sin leer avisa otra vez a quien tiene asignada la conversación (o a los administradores si no tiene a nadie).
     * Una sola vez por mensaje, y sólo mientras sea reciente, para que una pausa larga no dispare una avalancha.
     */
    public static function reminders(?int $now=null):int {
        $now??=time();$made=0;
        $connections=query("SELECT c.id,c.name,c.tenant_id,c.unread_reminder_minutes,(SELECT COUNT(*) FROM ghl_connections x WHERE x.tenant_id=c.tenant_id) AS n FROM ghl_connections c WHERE c.status='connected' AND c.unread_reminder_minutes>0")->fetchAll();
        foreach($connections as $c) {
            $due=($now-(int)$c['unread_reminder_minutes']*60)*1000;
            $oldest=($now-86400)*1000;
            $account=(int)$c['n']>1?trim((string)$c['name']):'';
            foreach(query('SELECT conversation_id,payload,assigned_to FROM conversation_index WHERE connection_id=? AND unread_count>0',[(int)$c['id']])->fetchAll() as $row) {
                $conversation=json_decode((string)$row['payload'],true);
                if(!is_array($conversation)||($conversation['lastMessageDirection']??null)!=='inbound')continue;
                require_once __DIR__.'/InboxFeed.php';
                $at=(int)(InboxFeed::sortValue($conversation['lastMessageDate']??null)??0);
                if($at===0||$at>$due||$at<$oldest)continue;
                [$who,$text]=self::describe($conversation,max(1,(int)($conversation['unreadCount']??1)));
                $title=mb_substr(($account!==''?mb_substr($account,0,40).' · ':'').'Sin leer hace '.(int)$c['unread_reminder_minutes'].' min · '.$who,0,190);
                $target=is_string($row['assigned_to'])&&$row['assigned_to']!==''?$row['assigned_to']:null;
                $st=query("INSERT IGNORE INTO push_queue(connection_id,conversation_id,fingerprint,title,body,assigned_to,kind,target) VALUES(?,?,?,?,?,?,'unread',?)",
                    [(int)$c['id'],$row['conversation_id'],md5(json_encode(['unread',$row['conversation_id'],$at])),$title,$text,$target,$target]);
                $made+=$st->rowCount();
            }
        }
        return $made;
    }

    private const CHANNELS=['TYPE_WHATSAPP'=>'WhatsApp','TYPE_SMS'=>'SMS','TYPE_INSTAGRAM'=>'Instagram','TYPE_FACEBOOK'=>'Messenger',
        'TYPE_EMAIL'=>'Correo','TYPE_WEBCHAT'=>'Chat web','TYPE_CALL'=>'Llamada','TYPE_GMB'=>'Google','TYPE_LIVE_CHAT'=>'Chat web'];

    /**
     * Quién escribe, por qué canal y qué dice. El texto genérico salía cada vez
     * que a la conversación le faltaba un dato; ahora se busca el siguiente que
     * haya, y un mensaje sin texto dice que llegó un adjunto.
     *
     * @return array{0:string,1:string}
     */
    public static function describe(array $conversation,int $unread=1):array {
        $who=null;
        foreach(['contactName','fullName','phone','email'] as $field)
            if($who===null)$who=self::text($conversation[$field]??null,120);
        $channel=self::CHANNELS[$conversation['lastMessageType']??'']??null;
        $title=($who??'Contacto sin nombre').($channel?' · '.$channel:'');
        $text=$conversation['lastMessageBody']??null;
        // Los correos llegan con etiquetas y espacios de sobra.
        $text=is_string($text)?trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags(str_ireplace(['<br>','<br/>','<br />','</p>'],' ',$text)),ENT_QUOTES|ENT_HTML5,'UTF-8'))):'';
        $body=$text!==''?mb_substr($text,0,self::BODY-30):'Envió un archivo o un mensaje sin texto';
        if($unread>1)$body.=' · '.$unread.' mensajes sin leer';
        return [mb_substr($title,0,190),$body];
    }

    /** A quién estaba asignada cada conversación antes de un pase, para detectar las que cambian de responsable. */
    public static function beforeAssigned(int $connectionId):array {
        $out=[];
        foreach(query('SELECT conversation_id,assigned_to FROM conversation_index WHERE connection_id=?',[$connectionId])->fetchAll() as $row)$out[$row['conversation_id']]=$row['assigned_to'];
        return $out;
    }

    /** The unread count each conversation had before a pass, to compare against. */
    public static function before(int $connectionId):array {
        $rows=query('SELECT conversation_id,unread_count FROM conversation_index WHERE connection_id=?',[$connectionId])->fetchAll();
        $before=[];
        foreach($rows as $row)$before[$row['conversation_id']]=(int)$row['unread_count'];
        return $before;
    }

    /**
     * The device tokens that should receive one notice. Only members of that
     * connection; administrators of the sub-account (and the owner) for every
     * conversation, everyone else only for conversations assigned to them.
     *
     * @return list<array{token:string,device:int,platform:string,p256dh:?string,auth:?string}>
     */
    public static function recipients(array $notice):array {
        $rows=query('SELECT DISTINCT d.id,d.token,d.platform,d.p256dh,d.auth,eu.external_user_id,eu.assigned_data_only,eu.role_name,eu.role_type
            FROM push_devices d
            JOIN external_identity_memberships m ON m.identity_id=d.actor_id AND d.identity_type=\'external\'
            JOIN external_users eu ON eu.id=m.external_user_row_id AND eu.active=TRUE
            JOIN ghl_connections c ON c.id=eu.connection_id AND c.tenant_id=d.tenant_id
            WHERE c.id=?',[(int)$notice['connection_id']])->fetchAll();
        // The owner's own devices see every conversation of their workspace.
        $owners=query('SELECT DISTINCT d.id,d.token,d.platform,d.p256dh,d.auth FROM push_devices d
            JOIN users u ON u.id=d.actor_id AND d.identity_type=\'native\' AND u.tenant_id=d.tenant_id AND u.role=\'administrator\'
            JOIN ghl_connections c ON c.tenant_id=u.tenant_id
            WHERE c.id=?',[(int)$notice['connection_id']])->fetchAll();
        $tokens=[];
        $kind=(string)($notice['kind']??'message');$target=$notice['target']??null;
        // «Te asignaron» y el recordatorio con responsable van sólo a esa persona; el recordatorio sin responsable, a quien administra.
        $directed=$kind==='assigned'||($kind==='unread'&&$target!==null);
        foreach($rows as $row) {
            $isAdmin=($row['role_name']??null)==='admin'||($row['role_type']??null)==='agency';
            if($directed) {
                if($row['external_user_id']===$target)$tokens[$row['token']]=self::device($row)+['unread'=>self::pending((int)$notice['connection_id'],$isAdmin?null:$row['external_user_id'])];
                continue;
            }
            if($kind==='unread') {
                if($isAdmin)$tokens[$row['token']]=self::device($row)+['unread'=>self::pending((int)$notice['connection_id'],null)];
                continue;
            }
            // Mensaje nuevo: los administradores de la subcuenta reciben todos; el resto, los de las conversaciones que tiene asignadas.
            $restricted=!$isAdmin;
            if($restricted&&($notice['assigned_to']===null||$notice['assigned_to']!==$row['external_user_id']))continue;
            $tokens[$row['token']]=self::device($row)+['unread'=>self::pending((int)$notice['connection_id'],$restricted?$row['external_user_id']:null)];
        }
        // El propietario ve todas las conversaciones; no recibe el «te asignaron» de otra persona.
        if(!$directed)foreach($owners as $row)$tokens[$row['token']]=self::device($row)+['unread'=>self::pending((int)$notice['connection_id'],null)];
        return array_values($tokens);
    }

    /** Lo que esa persona tiene sin leer, para el número del icono. */
    private static function pending(int $connectionId,?string $assignee):int {
        return (int)($assignee===null
            ? query('SELECT COALESCE(SUM(unread_count),0) FROM conversation_index WHERE connection_id=?',[$connectionId])->fetchColumn()
            : query('SELECT COALESCE(SUM(unread_count),0) FROM conversation_index WHERE connection_id=? AND assigned_to=?',[$connectionId,$assignee])->fetchColumn());
    }

    private static function device(array $row):array {
        return ['token'=>$row['token'],'device'=>(int)$row['id'],'platform'=>(string)($row['platform']??'android'),'p256dh'=>$row['p256dh']??null,'auth'=>$row['auth']??null];
    }

    private static function text(mixed $value,int $max):?string {
        if(!is_scalar($value))return null;
        $value=trim((string)$value);
        if($value==='')return null;
        return mb_substr($value,0,$max);
    }
}
