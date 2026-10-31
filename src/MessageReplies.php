<?php
declare(strict_types=1);

/** Citas: a qué mensaje responde cada mensaje que Ágora envió. */
final class MessageReplies {
    public const ID='/^[A-Za-z0-9_-]{1,100}$/D';

    /** Lo citado, tal como lo guarda la lectura reciente del chat: texto corto y de quién era. */
    public static function describe(int $connection,string $conversation,string $messageId):array {
        try {$payload=query('SELECT payload FROM conversation_message_cache WHERE connection_id=? AND conversation_id=?',[$connection,$conversation])->fetchColumn();}
        catch(PDOException $e) {return ['snippet'=>null,'direction'=>null];}
        $items=is_string($payload)?json_decode($payload,true):null;
        foreach(is_array($items)?$items:[] as $item) {
            if(!is_array($item)||($item['id']??null)!==$messageId)continue;
            $text=is_string($item['body']??null)?trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags((string)$item['body']),ENT_QUOTES|ENT_HTML5,'UTF-8'))):'';
            if($text==='')$text=!empty($item['attachments'])?'Archivo adjunto':'';
            return ['snippet'=>$text===''?null:mb_substr($text,0,160),'direction'=>in_array($item['direction']??null,['inbound','outbound'],true)?$item['direction']:null];
        }
        return ['snippet'=>null,'direction'=>null];
    }

    public static function remember(int $connection,string $conversation,string $messageId,string $replyTo):void {
        $quoted=self::describe($connection,$conversation,$replyTo);
        try {
            query('INSERT INTO message_replies(connection_id,message_id,conversation_id,reply_to_id,snippet,quoted_direction) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE reply_to_id=VALUES(reply_to_id)',
                [$connection,$messageId,$conversation,$replyTo,$quoted['snippet'],$quoted['direction']]);
        } catch(PDOException $e) {}
    }

    /** Añade `replyTo` a los mensajes de la página que respondían a otro. */
    public static function annotate(int $connection,string $conversation,array $page):array {
        $items=is_array($page['items']??null)?$page['items']:[];
        $ids=array_values(array_filter(array_column($items,'id'),'is_string'));
        if(!$ids)return $page;
        try {
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $rows=query("SELECT message_id,reply_to_id,snippet,quoted_direction FROM message_replies WHERE connection_id=? AND conversation_id=? AND message_id IN ($marks)",array_merge([$connection,$conversation],$ids))->fetchAll();
        } catch(PDOException $e) {return $page;}
        $by=[];foreach($rows as $r)$by[$r['message_id']]=$r;
        foreach($items as &$item) {
            $r=$by[$item['id']??'']??null;
            if($r)$item['replyTo']=['id'=>$r['reply_to_id'],'snippet'=>$r['snippet'],'direction'=>$r['quoted_direction']];
        }
        unset($item);
        $page['items']=$items;
        return $page;
    }
}
