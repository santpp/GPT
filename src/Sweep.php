<?php
declare(strict_types=1);
require_once __DIR__.'/Installation.php';
require_once __DIR__.'/InboxSync.php';
require_once __DIR__.'/Pulse.php';
require_once __DIR__.'/PushDelivery.php';
require_once __DIR__.'/ExternalUserDirectory.php';

/**
 * La revisión de cada minuto: mira la cuenta de cada conexión, prepara los
 * avisos de lo nuevo y los entrega. La llaman el cron del servidor y, cuando
 * ese cron no se puede poner o no corre, una dirección secreta que visita un
 * servicio de cron externo. Las dos comparten candado: nunca corren a la vez.
 */
final class Sweep {
    private const MIN_GAP=20;

    /** @return array{swept:int,connections:int,delivered:string,skipped:?string} */
    public static function run(string $via):array {
        // Una actualización puede haber llegado desde la última visita.
        Installation::ensureSchema(Installation::checks(false));
        $trace=function(array $value)use($via):void {
            try{query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['sweep_last_run',json_encode($value+['at'=>gmdate('c'),'via'=>$via])]);}catch(Throwable $e){}
        };
        if((int)query("SELECT GET_LOCK('agora-inbox-cron',0)")->fetchColumn()!==1) {
            // Incluso una pasada que se aparta deja rastro: el silencio se leería como «no corre».
            $trace(['skipped'=>'previous_pass_running']);
            return ['swept'=>0,'connections'=>0,'delivered'=>'','skipped'=>'previous_pass_running'];
        }
        $swept=0;$rows=[];
        try {
            $rows=query("SELECT c.* FROM ghl_connections c JOIN ghl_sync_state s ON s.connection_id=c.id WHERE s.enabled=TRUE AND c.status='connected' AND s.request_not_before<=UNIX_TIMESTAMP() ORDER BY s.pulse_next_at LIMIT 20")->fetchAll();
            $trace(['connections'=>count($rows)]);
            $pulse=new Pulse();
            foreach($rows as $row) {
                try {if($pulse->sweep($row))$swept++;}
                catch(GhlCooldown $e) {}
                catch(Throwable $e) {error_log('Sweep failed for connection '.(int)$row['id'].': '.get_class($e));}
            }
            $team=query("SELECT c.* FROM ghl_connections c JOIN ghl_sync_state s ON s.connection_id=c.id WHERE s.enabled=TRUE AND c.status='connected' AND s.users_next_sync_at<=UNIX_TIMESTAMP() AND s.request_not_before<=UNIX_TIMESTAMP() ORDER BY s.users_next_sync_at LIMIT 20")->fetchAll();
            foreach($team as $row) {
                try {(new ExternalUserDirectory())->sync($row);}
                catch(GhlCooldown $e) {}
                catch(Throwable $e) {query('UPDATE ghl_sync_state SET users_next_sync_at=?,users_last_error=? WHERE connection_id=?',[time()+300,'users_sync_failed',$row['id']]);}
            }
        } finally {query("SELECT RELEASE_LOCK('agora-inbox-cron')");}
        // Lo recién descubierto sale en el mismo minuto.
        return ['swept'=>$swept,'connections'=>count($rows),'delivered'=>PushDelivery::deliverPending(),'skipped'=>null];
    }

    /** Si la última pasada fue hace muy poco, una llamada externa no repite el trabajo. */
    public static function tooSoon():bool {
        $last=PushDelivery::recall('sweep_last_run');
        return is_string($last['at']??null)&&time()-strtotime($last['at'])<self::MIN_GAP;
    }

    /**
     * La dirección secreta. Sólo se guarda su huella: la dirección completa se
     * enseña una vez, al crearla, y quien la pierda la cambia por otra.
     */
    public static function createSecret():string {
        $secret=bin2hex(random_bytes(32));
        PushDelivery::remember('cron_secret',['hash'=>hash('sha256',$secret),'created'=>gmdate('c')]);
        return $secret;
    }
    public static function disableSecret():void {
        query('DELETE FROM app_state WHERE name=?',['cron_secret']);
    }
    public static function secretMatches(string $candidate):bool {
        $stored=PushDelivery::recall('cron_secret');
        if(!is_string($stored['hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$candidate))return false;
        return hash_equals($stored['hash'],hash('sha256',$candidate));
    }
    public static function secretCreated():?string {
        $stored=PushDelivery::recall('cron_secret');
        return is_string($stored['created']??null)?$stored['created']:null;
    }
}
