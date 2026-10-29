<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

/**
 * The inbox as the account has it right now. Every load asks the account; the
 * stored copy is only a fallback for a paused budget or a failed read, and it
 * is reported as such instead of passing for current.
 *
 * Filtering happens in the account, not here, so "unread" means every unread
 * conversation it holds and not merely the unread ones inside a window.
 */
final class InboxFeed {
    public const CHANNELS=['TYPE_SMS'=>'SMS','TYPE_WHATSAPP'=>'WhatsApp','TYPE_INSTAGRAM'=>'Instagram','TYPE_FACEBOOK'=>'Messenger','TYPE_EMAIL'=>'Email','TYPE_WEBCHAT'=>'Chat web','TYPE_CALL'=>'Llamada'];
    private const STATUSES=['all','unread','starred','recents'];
    private const KEEP=400;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    /** @return array{filters:array<string,string>,query:?string} */
    public static function readFilters(array $input,array $membership):array {
        $filters=[];
        $status=$input['status']??null;
        if(is_string($status)&&in_array($status,self::STATUSES,true)&&$status!=='all')$filters['status']=$status;
        $channel=$input['channel']??null;
        if(is_string($channel)&&isset(self::CHANNELS[$channel]))$filters['lastMessageType']=$channel;
        $direction=$input['direction']??null;
        if(is_string($direction)&&in_array($direction,['inbound','outbound'],true))$filters['lastMessageDirection']=$direction;
        // "Mine" can only ever mean the member's own imported identity.
        if(($input['mine']??null)==='1'&&is_string($membership['external_user_id']??null))$filters['assignedTo']=$membership['external_user_id'];
        $query=$input['query']??null;
        $query=is_string($query)?trim($query):'';
        $query=$query!==''&&mb_strlen($query)<=100?preg_replace('/[^\p{L}\p{N}@._+\- ]/u','',$query):null;
        if($query!==null&&$query!=='')$filters['query']=$query;
        return ['filters'=>$filters,'query'=>$filters['query']??null];
    }

    public function load(array $connection,int $limit,array $filters,array $membership):array {
        if(!in_array($limit,[20,50,100],true))$limit=20;
        $id=(int)$connection['id'];
        // A connection nobody has verified yet has nothing to show and nothing to
        // report as broken: it answers an empty inbox that says what is missing.
        if($connection['status']!=='connected')
            return $this->answer($connection,[],0,$membership,true,'unverified')+['syncedAt'=>null,'retryAfter'=>0];
        try {
            $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->conversations($connection['location_id'],$limit,$filters));
            $items=$this->normalize($data,$connection,$limit);
            $this->remember($id,$items,$data,$filters);
            return $this->answer($connection,$items,(int)$data['total'],$membership,false,null);
        } catch(GhlCooldown $e) {
            return $this->fallback($connection,$membership,'paused',$e->retryAfter);
        } catch(Throwable $e) {
            $stored=$this->fallback($connection,$membership,'stale',0);
            if($stored['items']!==[]||$stored['syncedAt']!==null)return $stored;
            throw $e;
        }
    }

    /** The last list the account gave, clearly marked as not current. */
    private function fallback(array $connection,array $membership,string $reason,int $retryAfter):array {
        $row=query('SELECT payload,total,synced_at FROM ghl_inbox_snapshots WHERE connection_id=?',[$connection['id']])->fetch();
        $items=$row?json_decode($row['payload'],true):[];
        if(!is_array($items))$items=[];
        $answer=$this->answer($connection,$items,$row?(int)$row['total']:0,$membership,true,$reason);
        $answer['syncedAt']=$row['synced_at']??null;
        $answer['retryAfter']=$retryAfter;
        return $answer;
    }

    private function answer(array $connection,array $items,int $total,array $membership,bool $stale,?string $reason):array {
        $visible=Actor::filterConversations($items,$membership);
        $restricted=(bool)($membership['capabilities']['assignedDataOnly']??false);
        return [
            'items'=>$visible,
            'total'=>$restricted?count($visible):$total,
            'partial'=>$total>count($items),
            'stale'=>$stale,
            'reason'=>$reason,
            'syncedAt'=>$stale?null:gmdate('c'),
            'unread'=>array_sum(array_map(fn(array $c):int=>max(0,(int)($c['unreadCount']??0)),$visible)),
            'channels'=>self::CHANNELS,
        ];
    }

    private function normalize(array $data,array $connection,int $limit):array {
        $items=$data['conversations']??null;
        if(!is_array($items)||!array_is_list($items)||count($items)>$limit||!is_numeric($data['total']??null)||$data['total']<0)throw new RuntimeException('Invalid inbox response');
        foreach($items as $conversation)
            if(!is_array($conversation)||!is_string($conversation['id']??null)||($conversation['locationId']??null)!==$connection['location_id'])throw new RuntimeException('Inbox location mismatch');
        return $items;
    }

    /**
     * Keeps the last list for the fallback, and records every conversation seen
     * so a filter can never make an already open one unreachable.
     */
    private function remember(int $id,array $items,array $data,array $filters):void {
        db()->beginTransaction();
        try {
            // Only an unfiltered read may replace the fallback list.
            if($filters===[])query('INSERT INTO ghl_inbox_snapshots(connection_id,payload,total) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),total=VALUES(total),synced_at=CURRENT_TIMESTAMP',[$id,json_encode($items,JSON_THROW_ON_ERROR),(int)$data['total']]);
            foreach($items as $conversation)
                query('INSERT INTO conversation_index(connection_id,conversation_id,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),seen_at=CURRENT_TIMESTAMP',[$id,$conversation['id'],json_encode($conversation,JSON_THROW_ON_ERROR)]);
            query('UPDATE ghl_sync_state SET last_success_at=CURRENT_TIMESTAMP,last_error=NULL WHERE connection_id=?',[$id]);
            db()->commit();
        } catch(Throwable $e) {if(db()->inTransaction())db()->rollBack();throw $e;}
        // Bounded so a busy account cannot grow the index without end.
        query('DELETE FROM conversation_index WHERE connection_id=? AND conversation_id NOT IN (SELECT conversation_id FROM (SELECT conversation_id FROM conversation_index WHERE connection_id=? ORDER BY seen_at DESC LIMIT '.self::KEEP.') AS keep)',[$id,$id]);
    }

    /** The conversation as the account last showed it, for authorization. */
    public static function known(int $connectionId,string $conversationId):?array {
        $payload=query('SELECT payload FROM conversation_index WHERE connection_id=? AND conversation_id=?',[$connectionId,$conversationId])->fetchColumn();
        if(!is_string($payload))return null;
        $value=json_decode($payload,true);
        return is_array($value)?$value:null;
    }

    public function markRead(array $connection,array $conversation):void {
        $this->gateway->execute($connection,fn(GhlClient $client)=>$client->markConversationRead($conversation['id'],$connection['location_id']),false);
        query('UPDATE conversation_index SET payload=JSON_SET(payload,\'$.unreadCount\',0) WHERE connection_id=? AND conversation_id=?',[$connection['id'],$conversation['id']]);
    }
}
