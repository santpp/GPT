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
    public static function queue(array $connection,array $items,array $before):void {
        foreach($items as $conversation) {
            if(!is_array($conversation)||!is_string($conversation['id']??null))continue;
            $unread=max(0,(int)($conversation['unreadCount']??0));
            $was=$before[$conversation['id']]??null;
            // A conversation already known and not more unread than before is not news.
            if($unread<1||($was!==null&&$unread<=$was))continue;
            $fingerprint=md5(json_encode([$conversation['id'],$conversation['lastMessageDate']??null,$unread],JSON_THROW_ON_ERROR));
            $name=self::text($conversation['contactName']??$conversation['fullName']??null,190)??'Nuevo mensaje';
            $body=self::text($conversation['lastMessageBody']??null,self::BODY)??'Tienes un mensaje nuevo.';
            $assigned=is_string($conversation['assignedTo']??null)?substr($conversation['assignedTo'],0,80):null;
            query('INSERT IGNORE INTO push_queue(connection_id,conversation_id,fingerprint,title,body,assigned_to) VALUES(?,?,?,?,?,?)',
                [(int)$connection['id'],$conversation['id'],$fingerprint,$name,$body,$assigned]);
        }
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
     * connection, and only those allowed to see that conversation.
     *
     * @return list<array{token:string,device:int}>
     */
    public static function recipients(array $notice):array {
        $rows=query('SELECT DISTINCT d.id,d.token,eu.external_user_id,eu.assigned_data_only
            FROM push_devices d
            JOIN external_identity_memberships m ON m.identity_id=d.actor_id AND d.identity_type=\'external\'
            JOIN external_users eu ON eu.id=m.external_user_row_id AND eu.active=TRUE
            JOIN ghl_connections c ON c.id=eu.connection_id AND c.tenant_id=d.tenant_id
            WHERE c.id=?',[(int)$notice['connection_id']])->fetchAll();
        // The owner's own devices see every conversation of their workspace.
        $owners=query('SELECT DISTINCT d.id,d.token FROM push_devices d
            JOIN users u ON u.id=d.actor_id AND d.identity_type=\'native\' AND u.tenant_id=d.tenant_id AND u.role=\'administrator\'
            JOIN ghl_connections c ON c.tenant_id=u.tenant_id
            WHERE c.id=?',[(int)$notice['connection_id']])->fetchAll();
        $tokens=[];
        foreach($rows as $row) {
            $restricted=((int)$row['assigned_data_only'])===1;
            if($restricted&&($notice['assigned_to']===null||$notice['assigned_to']!==$row['external_user_id']))continue;
            $tokens[$row['token']]=['token'=>$row['token'],'device'=>(int)$row['id']];
        }
        foreach($owners as $row)$tokens[$row['token']]=['token'=>$row['token'],'device'=>(int)$row['id']];
        return array_values($tokens);
    }

    private static function text(mixed $value,int $max):?string {
        if(!is_scalar($value))return null;
        $value=trim((string)$value);
        if($value==='')return null;
        return mb_substr($value,0,$max);
    }
}
