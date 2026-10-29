<?php
declare(strict_types=1);
final class MessageHistory {
    public static function cursor(mixed $cursor):?string {
        if($cursor===null||$cursor==='')return null;
        if(!is_string($cursor)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$cursor))throw new InvalidArgumentException('Invalid cursor');
        return $cursor;
    }
    public static function page(array $data,string $conversation,string $location,?string $previous):array {
        $items=$data['messages']??null;
        if(!is_array($items)||!array_is_list($items)||count($items)>20)throw new RuntimeException('Invalid messages');
        foreach($items as $message) {
            if(!is_array($message)||!is_string($message['id']??null))throw new RuntimeException('Invalid message');
            // Some real channel/activity records omit these optional envelope fields.
            // When present they must still match the tenant-scoped request.
            if(isset($message['conversationId'])&&$message['conversationId']!==$conversation)throw new RuntimeException('Conversation mismatch');
            if(isset($message['locationId'])&&$message['locationId']!==$location)throw new RuntimeException('Location mismatch');
        }
        $next=self::cursor($data['lastMessageId']??null);
        $more=($data['nextPage']??false)===true;
        if($more&&(!$next||$next===$previous||!in_array($next,array_column($items,'id'),true)))throw new RuntimeException('Invalid next page');
        return ['items'=>$items,'hasMore'=>$more,'lastMessageId'=>$more?$next:null];
    }
}
