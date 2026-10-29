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
    public function conversations(string $location,int $limit=20,array $filters=[]):array {
        $query=['locationId'=>$location,'limit'=>$limit,'sort'=>'desc','sortBy'=>'last_message_date']+$filters;
        return $this->get('/conversations/search?'.http_build_query(array_filter($query,fn($v)=>$v!==null&&$v!=='')),'2021-04-15');
    }
    public function markConversationRead(string $conversation,string $location):array {
        return $this->put('/conversations/'.rawurlencode($conversation),'2021-04-15',['locationId'=>$location,'unreadCount'=>0]);
    }
    public function conversationMessages(string $id,?string $cursor=null): array {
        return $this->get('/conversations/'.rawurlencode($id).'/messages?'.http_build_query(array_filter(['limit'=>20,'lastMessageId'=>$cursor],fn($v)=>$v!==null)),'2021-04-15');
    }
    public function contact(string $id): array {return $this->get('/contacts/'.rawurlencode($id),'2021-07-28');}
    public function contactFields(string $location):array{return $this->get('/locations/'.rawurlencode($location).'/customFields','2021-07-28');}
    public function locationUsers(string $location):array {return $this->get('/users/?'.http_build_query(['locationId'=>$location]),'2021-07-28');}
    public function sendMessage(array $payload):array {return $this->get('/conversations/messages','2021-04-15',$payload);}
    public function duplicateContact(string $location,string $email):array {
        return $this->get('/contacts/search/duplicate?'.http_build_query(['locationId'=>$location,'email'=>$email]),'2021-07-28');
    }
    public function upsertContact(array $payload):array {return $this->get('/contacts/upsert','2021-07-28',$payload);}
    public function addContactTags(string $id,array $tags):array {return $this->get('/contacts/'.rawurlencode($id).'/tags','2021-07-28',['tags'=>$tags]);}
    public function contactOpportunities(string $location,string $contact,int $limit=20):array {
        return $this->get('/opportunities/search?'.http_build_query(['location_id'=>$location,'contact_id'=>$contact,'status'=>'all','limit'=>$limit]),'2021-07-28');
    }
    public function pipelines(string $location):array {return $this->get('/opportunities/pipelines?'.http_build_query(['locationId'=>$location]),'2021-07-28');}
    public function locationContacts(string $location,?string $query,int $limit=50):array {
        return $this->get('/contacts/?'.http_build_query(array_filter(['locationId'=>$location,'query'=>$query,'limit'=>$limit],fn($v)=>$v!==null&&$v!=='')),'2021-07-28');
    }
    public function locationOpportunities(string $location,?string $pipeline,int $limit=100):array {
        return $this->get('/opportunities/search?'.http_build_query(array_filter(['location_id'=>$location,'pipeline_id'=>$pipeline,'status'=>'open','limit'=>$limit],fn($v)=>$v!==null&&$v!=='')),'2021-07-28');
    }
    public function contactTasks(string $contact):array {return $this->get('/contacts/'.rawurlencode($contact).'/tasks','2021-07-28');}
    public function contactNotes(string $contact):array {return $this->get('/contacts/'.rawurlencode($contact).'/notes','2021-07-28');}
    public function contactAppointments(string $contact):array {return $this->get('/contacts/'.rawurlencode($contact).'/appointments','2021-07-28');}
    public function createContactTask(string $contact,array $payload):array {return $this->get('/contacts/'.rawurlencode($contact).'/tasks','2021-07-28',$payload);}
    public function createContactNote(string $contact,array $payload):array {return $this->get('/contacts/'.rawurlencode($contact).'/notes','2021-07-28',$payload);}
    public function updateContact(string $contact,array $payload):array {return $this->put('/contacts/'.rawurlencode($contact),'2021-07-28',$payload);}
    public function removeContactTags(string $contact,array $tags):array {return $this->delete('/contacts/'.rawurlencode($contact).'/tags','2021-07-28',['tags'=>$tags]);}
    public function completeContactTask(string $contact,string $task,bool $completed):array {
        return $this->put('/contacts/'.rawurlencode($contact).'/tasks/'.rawurlencode($task).'/completed','2021-07-28',['completed'=>$completed]);
    }
    private function put(string $path,string $version,array $payload):array {return $this->get($path,$version,$payload,'PUT');}
    private function delete(string $path,string $version,array $payload):array {return $this->get($path,$version,$payload,'DELETE');}
    private function get(string $path,string $version,?array $payload=null,?string $verb=null):array {
        if($this->token==='')throw new RuntimeException('GHL credential missing');
        $this->headers=[];$this->status=0;
        $method=$verb??($payload===null?'GET':'POST');
        if($this->transport) {
            $r=($this->transport)($path,$version,$method,$payload);$this->status=$r['status'];$this->headers=array_change_key_case($r['headers']??[]);$raw=$r['body'];
        } else {
            $ch=curl_init('https://services.leadconnectorhq.com'.$path);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token,'Version: '.$version,'Accept: application/json'],CURLOPT_HEADERFUNCTION=>function($ch,string $line):int {
                $parts=explode(':',$line,2);if(count($parts)===2)$this->headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);
            }]);
            if($payload!==null)curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token,'Version: '.$version,'Accept: application/json','Content-Type: application/json']]);
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
