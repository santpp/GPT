<?php
declare(strict_types=1);
require_once __DIR__.'/GhlClient.php';
require_once __DIR__.'/TokenVault.php';
final class GhlCooldown extends RuntimeException {
    public function __construct(public readonly int $retryAfter) {parent::__construct('API cooldown');}
}
final class GhlGateway {
    // The conservative local budget is a safety cap, not a claim about PIT quotas.
    private const DAILY_BUDGET=5000;
    // The published OAuth burst limit is 100 requests per 10 seconds per resource.
    // A fifth of that leaves room for anything else using the same credential,
    // while letting one screen make its handful of reads without waiting.
    private const BURST=20;
    private const WINDOW=10;
    public function __construct(private readonly ?Closure $factory=null) {}
    public function execute(array $connection,Closure $operation,bool $disableOnForbidden=true):array {
        $id=(int)$connection['id'];$key='agora-api:'.$id;
        if((int)query('SELECT GET_LOCK(?,0)',[$key])->fetchColumn()!==1)throw new GhlCooldown(2);
        try {
            $fresh=query('SELECT * FROM ghl_connections WHERE id=? AND tenant_id=?',[$id,$connection['tenant_id']])->fetch();
            if(!$fresh || !hash_equals($fresh['token_ciphertext'],$connection['token_ciphertext']))throw new GhlCooldown(2);
            query('INSERT IGNORE INTO ghl_sync_state(connection_id) VALUES(?)',[$id]);
            $state=query('SELECT * FROM ghl_sync_state WHERE connection_id=?',[$id])->fetch();$now=time();$day=gmdate('Y-m-d');
            if((int)$state['request_not_before']>$now)throw new GhlCooldown((int)$state['request_not_before']-$now);
            $used=$state['budget_day']===$day?(int)$state['requests_today']:0;
            if($used>=self::DAILY_BUDGET)throw new GhlCooldown(strtotime('tomorrow UTC')-$now);
            $windowStart=(int)($state['rate_window_start']??0);$windowCount=(int)($state['rate_window_count']??0);
            if($now-$windowStart>=self::WINDOW){$windowStart=$now;$windowCount=0;}
            if($windowCount>=self::BURST)throw new GhlCooldown(max(1,$windowStart+self::WINDOW-$now));
            query('UPDATE ghl_sync_state SET budget_day=?,requests_today=?,rate_window_start=?,rate_window_count=? WHERE connection_id=?',[$day,$used+1,$windowStart,$windowCount+1,$id]);
            $client=$this->factory ? ($this->factory)($connection) : new GhlClient(TokenVault::fromEnvironment()->open($connection['token_ciphertext'],(int)$connection['tenant_id'],$connection['location_id']));
            try {
                $result=$operation($client);
                $quota=$this->quota($client);$pause=$this->quotaPause($quota,$now);
                query('UPDATE ghl_sync_state SET last_http_status=?,quota=?,request_not_before=GREATEST(request_not_before,?),last_error=NULL,failures=0 WHERE connection_id=?',[$client->status,json_encode($quota,JSON_THROW_ON_ERROR),$pause,$id]);
                return $result;
            } catch(Throwable $error) {
                $status=$error instanceof GhlApiException?$error->status:$client->status;
                $wait=$error instanceof GhlApiException && $status===429 ? max(30,$error->retryAfter) : min(3600,15*(2**min(8,(int)$state['failures'])));
                $quota=$this->quota($client);$pause=max($now+$wait,$this->quotaPause($quota,$now));
                $code=in_array($status,[401,403],true)?'authentication_or_scope':($status===429?'rate_limited':'upstream_error');
                query('UPDATE ghl_sync_state SET request_not_before=?,last_http_status=?,last_error=?,quota=?,failures=failures+1,enabled=IF(?,FALSE,enabled) WHERE connection_id=?',[$pause,$status,$code,json_encode($quota,JSON_THROW_ON_ERROR),(int)($status===401||($status===403&&$disableOnForbidden)),$id]);
                throw $error;
            }
        } finally {query('SELECT RELEASE_LOCK(?)',[$key]);}
    }
    private function quota(GhlClient $client):array {
        $out=[];
        foreach(['x-ratelimit-limit-daily','x-ratelimit-daily-remaining','x-ratelimit-interval-milliseconds','x-ratelimit-max','x-ratelimit-remaining'] as $name) {
            $v=$client->headers[$name]??null;if(is_scalar($v)&&ctype_digit((string)$v))$out[$name]=(int)$v;
        }
        return $out;
    }
    private function quotaPause(array $quota,int $now):int {
        if(($quota['x-ratelimit-daily-remaining']??1)===0)return strtotime('tomorrow UTC');
        if(($quota['x-ratelimit-remaining']??1)===0)return $now+max(1,(int)ceil(($quota['x-ratelimit-interval-milliseconds']??10000)/1000));
        return $now;
    }
}
