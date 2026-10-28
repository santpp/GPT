<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
final class InboxSync {
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}
    public function sync(array $connection):array {
        $id=(int)$connection['id'];$key='agora-sync:'.$id;
        if((int)query('SELECT GET_LOCK(?,0)',[$key])->fetchColumn()!==1)throw new GhlCooldown(2);
        try {
            query('INSERT IGNORE INTO ghl_sync_state(connection_id) VALUES(?)',[$id]);
            $state=query('SELECT * FROM ghl_sync_state WHERE connection_id=?',[$id])->fetch();
            if((int)$state['next_sync_at']>time())throw new GhlCooldown((int)$state['next_sync_at']-time());
            if($connection['status']!=='connected')throw new InvalidArgumentException('Connection must be verified first');
            $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->conversations($connection['location_id']));
            query('UPDATE ghl_sync_state SET next_sync_at=? WHERE connection_id=?',[time()+max(60,(int)$connection['sync_interval_seconds']),$id]);
            if(!isset($data['conversations'],$data['total']) || !is_array($data['conversations']) || !array_is_list($data['conversations']) || count($data['conversations'])>20 || !is_numeric($data['total']) || $data['total']<0)throw new RuntimeException('Invalid inbox response');
            foreach($data['conversations'] as $c) {
                if(!is_array($c)||!is_string($c['id']??null)||($c['locationId']??null)!==$connection['location_id'])throw new RuntimeException('Inbox location mismatch');
            }
            db()->beginTransaction();
            query('INSERT INTO ghl_inbox_snapshots(connection_id,payload,total) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),total=VALUES(total),synced_at=CURRENT_TIMESTAMP',[$id,json_encode($data['conversations'],JSON_THROW_ON_ERROR),(int)$data['total']]);
            query('UPDATE ghl_sync_state SET last_success_at=CURRENT_TIMESTAMP,last_error=NULL WHERE connection_id=?',[$id]);
            db()->commit();
            return ['count'=>count($data['conversations']),'total'=>(int)$data['total'],'partial'=>(int)$data['total']>count($data['conversations'])];
        } catch(Throwable $e) {
            if(db()->inTransaction())db()->rollBack();
            if(!$e instanceof GhlCooldown)query("UPDATE ghl_sync_state SET last_error=COALESCE(last_error,'invalid_or_failed_sync') WHERE connection_id=?",[$id]);
            throw $e;
        } finally {query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
}
