<?php
declare(strict_types=1);

/**
 * Las redes sociales por las que ha escrito un contacto. La cuenta guarda una sola conversación por contacto y su
 * «último canal» cambia con cada mensaje: si después de un comentario o mensaje de Instagram se le escribió por
 * WhatsApp, el último canal ya no es Instagram, pero el contacto sigue pudiendo recibir mensajes directos ahí.
 * Aquí se recuerda cada red desde la que el contacto escribió, y sólo se añaden, nunca se quitan.
 */
final class ConversationNetworks {
    public const MAP=['TYPE_INSTAGRAM'=>'IG','TYPE_INSTAGRAM_COMMENT'=>'IG','TYPE_FACEBOOK'=>'FB','TYPE_FACEBOOK_COMMENT'=>'FB'];

    /** @param list<mixed> $messages mensajes del historial (sólo cuentan los entrantes). */
    public static function remember(int $connection,string $conversation,array $messages):void {
        $found=[];
        foreach($messages as $m)
            if(is_array($m)&&($m['direction']??null)==='inbound'&&is_string($m['messageType']??null)&&isset(self::MAP[$m['messageType']]))$found[self::MAP[$m['messageType']]]=true;
        self::save($connection,$conversation,array_keys($found));
    }

    /** Anota la red del último mensaje de una conversación de la lista, si lo escribió el contacto. */
    public static function fromListing(int $connection,array $conversation):void {
        if(!is_string($conversation['id']??null)||($conversation['lastMessageDirection']??null)!=='inbound')return;
        $channel=self::MAP[$conversation['lastMessageType']??'']??null;
        if($channel!==null)self::save($connection,$conversation['id'],[$channel]);
    }

    private static function save(int $connection,string $conversation,array $channels):void {
        foreach($channels as $channel) {
            try {query('INSERT IGNORE INTO conversation_networks(connection_id,conversation_id,channel) VALUES(?,?,?)',[$connection,$conversation,$channel]);}
            catch(PDOException $e) {}
        }
    }

    /** @return list<string> */
    public static function seen(int $connection,string $conversation):array {
        try {return array_column(query('SELECT channel FROM conversation_networks WHERE connection_id=? AND conversation_id=?',[$connection,$conversation])->fetchAll(),'channel');}
        catch(PDOException $e) {return [];}
    }
}
