<?php
declare(strict_types=1);

final class HistoryFormatException extends RuntimeException {
    public function __construct(public readonly string $reason) {parent::__construct('history_'.$reason);}
}

final class MessageHistory {
    // The request asks for 20; an account may answer with a different page size,
    // so only an implausible page is treated as a broken response.
    private const MAX_PAGE=100;

    public static function cursor(mixed $cursor):?string {
        if($cursor===null||$cursor==='')return null;
        if(!is_string($cursor)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$cursor))throw new InvalidArgumentException('Invalid cursor');
        return $cursor;
    }

    public static function page(array $data,string $conversation,string $location,?string $previous):array {
        $raw=$data['messages']??null;
        if(!is_array($raw))throw new HistoryFormatException('messages_missing');
        if(!array_is_list($raw))throw new HistoryFormatException('messages_not_a_list');
        if(count($raw)>self::MAX_PAGE)throw new HistoryFormatException('page_too_large');
        $items=[];
        foreach($raw as $message) {
            // Account scope stays strict: a record of another conversation or
            // another account is never shown, whatever else the page contains.
            if(is_array($message)&&isset($message['conversationId'])&&$message['conversationId']!==$conversation)throw new HistoryFormatException('conversation_mismatch');
            if(is_array($message)&&isset($message['locationId'])&&$message['locationId']!==$location)throw new HistoryFormatException('location_mismatch');
            // A record without a usable id cannot be shown or de-duplicated, but
            // it must not cost the reader the rest of the conversation.
            if(!is_array($message)||!is_string($message['id']??null)||$message['id']==='')continue;
            $items[]=$message;
        }
        $next=null;
        try {$next=self::cursor($data['lastMessageId']??null);}
        catch(InvalidArgumentException $e){$next=null;}
        $more=($data['nextPage']??false)===true;
        // An unusable cursor means this is the last page we can ask for, not that
        // the page is corrupt: the messages are still shown, without "load more".
        if($more&&($next===null||$next===$previous||!in_array($next,array_column($items,'id'),true)))$more=false;
        return ['items'=>$items,'hasMore'=>$more,'lastMessageId'=>$more?$next:null];
    }
}
