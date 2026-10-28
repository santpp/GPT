<?php
declare(strict_types=1);
final class GhlApiException extends RuntimeException {
    public function __construct(public readonly int $status,public readonly int $retryAfter=0) {parent::__construct('GHL request failed: '.$status);}
}
final class GhlClient {
    public array $headers=[];
    public int $status=0;
    // An injected transport is for server-side tests only. Callers cannot set URLs.
    public function __construct(private readonly string $token,private readonly ?Closure $transport=null) {}
    public function checkLocation(string $id): void {
        $result=$this->get('/locations/'.rawurlencode($id),'2021-07-28');
        if(($result['location']['id'] ?? null)!==$id) throw new RuntimeException('Location mismatch');
    }
    public function conversations(string $location):array {
        return $this->get('/conversations/search?'.http_build_query(['locationId'=>$location,'limit'=>20,'sort'=>'desc','sortBy'=>'last_message_date']),'2021-04-15');
    }
    public function conversationMessages(string $id): array {
        return $this->get('/conversations/'.rawurlencode($id).'/messages?limit=20','2021-04-15');
    }
    public function contact(string $id): array {return $this->get('/contacts/'.rawurlencode($id),'2021-07-28');}
    private function get(string $path,string $version):array {
        if($this->token==='')throw new RuntimeException('GHL credential missing');
        $this->headers=[];$this->status=0;
        if($this->transport) {
            $r=($this->transport)($path,$version);$this->status=$r['status'];$this->headers=array_change_key_case($r['headers']??[]);$raw=$r['body'];
        } else {
            $ch=curl_init('https://services.leadconnectorhq.com'.$path);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token,'Version: '.$version,'Accept: application/json'],CURLOPT_HEADERFUNCTION=>function($ch,string $line):int {
                $parts=explode(':',$line,2);if(count($parts)===2)$this->headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);
            }]);
            $raw=curl_exec($ch);$this->status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        }
        if($raw===false || $this->status<200 || $this->status>=300) {
            $retry=$this->headers['retry-after']??'';
            $seconds=ctype_digit((string)$retry)?(int)$retry:max(0,(strtotime((string)$retry)?:time())-time());
            throw new GhlApiException($this->status,min(86400,$seconds));
        }
        $result=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($result))throw new RuntimeException('Invalid GHL response');
        return $result;
    }
}
