<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/MessageHistory.php';

final class MessageFeed {
    private const FRESH_SECONDS=8;
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function recent(array $connection,string $conversation,bool $force=false):array {
        $cached=$this->cached((int)$connection['id'],$conversation);
        if(!$force && $cached && strtotime($cached['fetched_at'])>=time()-self::FRESH_SECONDS)return $this->response($cached,true);
        $lock='agora-messages:'.$connection['id'].':'.substr(hash('sha256',$conversation),0,32);
        if((int)query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1) {
            if($cached)return $this->response($cached,true);
            throw new GhlCooldown(1);
        }
        try {
            $cached=$this->cached((int)$connection['id'],$conversation);
            if(!$force && $cached && strtotime($cached['fetched_at'])>=time()-self::FRESH_SECONDS)return $this->response($cached,true);
            $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->conversationMessages($conversation));
            $page=MessageHistory::page($data,$conversation,$connection['location_id'],null);
            query('INSERT INTO conversation_message_cache(connection_id,conversation_id,payload,last_message_id,has_more) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),last_message_id=VALUES(last_message_id),has_more=VALUES(has_more),fetched_at=CURRENT_TIMESTAMP',[(int)$connection['id'],$conversation,json_encode($page['items'],JSON_THROW_ON_ERROR),$page['lastMessageId'],(int)$page['hasMore']]);
            return $page+['cached'=>false,'fetchedAt'=>gmdate('c')];
        } finally {query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    public function older(array $connection,string $conversation,string $cursor):array {
        $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->conversationMessages($conversation,$cursor));
        return MessageHistory::page($data,$conversation,$connection['location_id'],$cursor)+['cached'=>false,'fetchedAt'=>gmdate('c')];
    }

    private function cached(int $connection,string $conversation):array|false {
        return query('SELECT payload,last_message_id,has_more,fetched_at FROM conversation_message_cache WHERE connection_id=? AND conversation_id=?',[$connection,$conversation])->fetch();
    }
    private function response(array $row,bool $cached):array {
        return ['items'=>json_decode($row['payload'],true,64,JSON_THROW_ON_ERROR),'hasMore'=>(bool)$row['has_more'],'lastMessageId'=>$row['last_message_id'],'cached'=>$cached,'fetchedAt'=>gmdate('c',strtotime($row['fetched_at']))];
    }
}
