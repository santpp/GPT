<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/InboxFeed.php';
require_once __DIR__.'/PushQueue.php';

/**
 * Near-live updates without webhooks, and without multiplying requests.
 *
 * The browser asks Ágora every few seconds, never the account: the answer is a
 * token read from the stored index, which only changes when the conversations
 * really changed. A screen reloads when there is something new instead of on a
 * timer, so a quiet account costs the account nothing at all.
 *
 * The account itself is read by one shared pass per connection, spaced by the
 * connection's own interval, whoever asked for it. Ten people looking at the
 * same subaccount cost the same as one.
 */
final class Pulse {
    private const FLOOR=10;
    private const WINDOW=200;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function read(array $connection,array $membership,bool $refresh=true):array {
        $id=(int)$connection['id'];
        // El intervalo de la conexión venía de la sincronización antigua (120 s
        // por defecto) y hacía que un aviso tardara hasta dos minutos. Leer cada
        // 10 segundos es una petición de cada doscientas que permite la cuota.
        $interval=self::FLOOR;
        $paused=null;$retryAfter=0;$refreshed=false;
        if($refresh&&$connection['status']==='connected') {
            try {$refreshed=$this->refreshIfDue($connection,$interval);}
            catch(GhlCooldown $e){$paused='paused';$retryAfter=$e->retryAfter;}
            catch(Throwable $e){$paused='failed';}
        }
        return $this->state($id,$membership)+[
            'intervalSeconds'=>$interval,
            'refreshed'=>$refreshed,
            'reason'=>$paused,
            'retryAfter'=>$retryAfter,
        ];
    }

    /** The fingerprint of what the account last showed, for this member. */
    private function state(int $id,array $membership):array {
        $restricted=($membership['capabilities']['assignedDataOnly']??false)===true;
        $mine=$membership['external_user_id']??null;
        $row=$restricted
            ? query('SELECT COUNT(*) AS seen,COALESCE(SUM(unread_count),0) AS unread,COALESCE(MAX(content_changed_at),0) AS changed FROM conversation_index WHERE connection_id=? AND assigned_to=?',[$id,(string)$mine])->fetch()
            : query('SELECT COUNT(*) AS seen,COALESCE(SUM(unread_count),0) AS unread,COALESCE(MAX(content_changed_at),0) AS changed FROM conversation_index WHERE connection_id=?',[$id])->fetch();
        $synced=query('SELECT last_success_at FROM ghl_sync_state WHERE connection_id=?',[$id])->fetchColumn();
        return [
            // Count included so a conversation leaving the index also shows up.
            'token'=>substr(hash('sha256',implode('|',[$row['seen']??0,$row['changed']??0,$row['unread']??0])),0,32),
            'unread'=>(int)($row['unread']??0),
            'syncedAt'=>is_string($synced)?$synced:null,
        ];
    }

    /**
     * The shared pass, for the cron. Nobody may have the app open, and that is
     * precisely when a notice matters most, so the schedule runs it anyway.
     */
    public function sweep(array $connection):bool {
        if($connection['status']!=='connected')return false;
        return $this->refreshIfDue($connection,self::FLOOR);
    }

    /**
     * Reads the account at most once per interval per connection. A pass that
     * is already running is not waited for: its result arrives on the next
     * pulse, which is seconds away.
     */
    private function refreshIfDue(array $connection,int $interval):bool {
        $id=(int)$connection['id'];
        query('INSERT IGNORE INTO ghl_sync_state(connection_id) VALUES(?)',[$id]);
        $due=(int)query('SELECT pulse_next_at FROM ghl_sync_state WHERE connection_id=?',[$id])->fetchColumn();
        if($due>time())return false;
        $lock='agora-pulse:'.$id;
        if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)return false;
        try {
            // The next pass is scheduled before the read, so a failing account
            // cannot turn a pulse into a loop of requests.
            query('UPDATE ghl_sync_state SET pulse_next_at=? WHERE connection_id=?',[time()+$interval,$id]);
            $limit=(int)(query('SELECT inbox_limit FROM connection_preferences WHERE connection_id=?',[$id])->fetchColumn()?:20);
            if(!in_array($limit,[20,50,100],true))$limit=20;
            $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->conversations($connection['location_id'],min($limit,self::WINDOW)),false);
            $items=$data['conversations']??null;
            $total=$data['total']??null;
            if(!is_array($items)||!array_is_list($items)||!is_numeric($total))throw new RuntimeException('Invalid inbox response');
            foreach($items as $conversation)
                if(!is_array($conversation)||!is_string($conversation['id']??null)||($conversation['locationId']??null)!==$connection['location_id'])throw new RuntimeException('Inbox location mismatch');
            db()->beginTransaction();
            try {
                query('INSERT INTO ghl_inbox_snapshots(connection_id,payload,total) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),total=VALUES(total),synced_at=CURRENT_TIMESTAMP',[$id,json_encode($items,JSON_THROW_ON_ERROR),(int)$total]);
                InboxFeed::index($id,$items);
                query('UPDATE ghl_sync_state SET last_success_at=CURRENT_TIMESTAMP,last_error=NULL WHERE connection_id=?',[$id]);
                db()->commit();
            } catch(Throwable $e) {if(db()->inTransaction())db()->rollBack();throw $e;}
            return true;
        } finally {query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
