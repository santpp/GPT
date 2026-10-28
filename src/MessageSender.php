<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
final class SendProblem extends RuntimeException {
    public function __construct(public readonly string $reason,public readonly int $httpStatus,string $message,public readonly int $retryAfter=0){parent::__construct($message);}
}
final class MessageSender {
    public const CHANNELS=['TYPE_SMS'=>'SMS','TYPE_WHATSAPP'=>'WhatsApp','TYPE_INSTAGRAM'=>'IG','TYPE_FACEBOOK'=>'FB','TYPE_EMAIL'=>'Email'];
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}
    public function send(array $connection,array $conversation,array $input):array {
        $id=(int)$connection['id'];$conversationId=$conversation['id']??'';
        $request=$input['requestId']??null;$text=$input['message']??null;$channel=self::CHANNELS[$conversation['lastMessageType']??'']??null;
        if(!is_string($request)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$request)||!is_string($text)||trim($text)===''||mb_strlen($text)>4000)throw new SendProblem('invalid_message',422,'Escribe un mensaje de hasta 4.000 caracteres.');
        if(!$channel||($input['channel']??null)!==$channel)throw new SendProblem('unsupported_channel',422,'Este canal no está disponible para responder desde aquí.');
        $contact=$conversation['contactId']??null;
        if(!is_string($contact)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contact)||($conversation['locationId']??null)!==$connection['location_id'])throw new SendProblem('invalid_contact',422,'Actualiza la conversación antes de responder.');
        $payload=['type'=>$channel,'contactId'=>$contact,'message'=>trim($text)];
        if($channel==='Email'){
            $subject=$input['subject']??null;
            if(!is_string($subject)||trim($subject)===''||mb_strlen($subject)>200)throw new SendProblem('invalid_subject',422,'Añade un asunto de hasta 200 caracteres.');
            $payload['subject']=trim($subject);
        }
        // The destination is taken only from the tenant-scoped snapshot, never from input.
        $hash=hash('sha256',json_encode([$conversationId,$payload],JSON_THROW_ON_ERROR));
        $lock='agora-send:'.$id.':'.substr(hash('sha256',$conversationId),0,32);
        if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)throw new SendProblem('send_busy',409,'Hay un envío en curso. Espera antes de repetir.',2);
        try {
            $attempt=query('SELECT * FROM message_send_attempts WHERE connection_id=? AND request_id=?',[$id,$request])->fetch();
            if($attempt){
                if(!hash_equals($attempt['request_hash'],$hash))throw new SendProblem('request_conflict',409,'Este identificador corresponde a otro mensaje.');
                if($attempt['state']==='accepted')return $this->accepted($attempt);
                if(in_array($attempt['state'],['sending','unknown'],true))throw $this->unknown();
                if($attempt['state']==='rejected')throw new SendProblem('send_rejected',422,'El envío anterior fue rechazado. Revisa el canal y los permisos antes de intentar un nuevo envío.');
            }
            // A new browser key must not resend identical content with an uncertain result.
            if(query("SELECT id FROM message_send_attempts WHERE connection_id=? AND conversation_id=? AND request_hash=? AND state IN ('sending','unknown') LIMIT 1",[$id,$conversationId,$hash])->fetchColumn())throw $this->unknown();
            if($connection['status']!=='connected')throw new SendProblem('connection_unverified',422,'Comprueba la conexión antes de enviar.');
            if(!$attempt)query("INSERT INTO message_send_attempts(connection_id,request_id,conversation_id,request_hash,state) VALUES(?,?,?,?,'sending')",[$id,$request,$conversationId,$hash]);
            else query("UPDATE message_send_attempts SET state='sending',error_code=NULL WHERE connection_id=? AND request_id=?",[$id,$request]);
            try {
                $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->sendMessage($payload));
                if(!is_string($data['messageId']??null)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$data['messageId'])||($data['conversationId']??null)!==$conversationId)throw new RuntimeException('Unconfirmed send response');
                $delivery=in_array($data['status']??null,['pending','delivered','read','failed'],true)?$data['status']:'pending';
                query("UPDATE message_send_attempts SET state='accepted',message_id=?,delivery_status=?,error_code=NULL WHERE connection_id=? AND request_id=?",[$data['messageId'],$delivery,$id,$request]);
                return ['state'=>'accepted','messageId'=>$data['messageId'],'deliveryStatus'=>$delivery,'requestId'=>$request];
            }catch(GhlCooldown $e){
                query("UPDATE message_send_attempts SET state='retryable',error_code='cooldown' WHERE connection_id=? AND request_id=?",[$id,$request]);
                throw new SendProblem('send_paused',429,'Envío pausado. El mensaje todavía no se ha enviado.',$e->retryAfter);
            }catch(GhlApiException $e){
                if(in_array($e->status,[400,401,403,404,413,415,422,429],true)){
                    query("UPDATE message_send_attempts SET state='rejected',error_code=? WHERE connection_id=? AND request_id=?",['http_'.$e->status,$id,$request]);
                    throw new SendProblem('send_rejected',422,'El canal rechazó el envío (HTTP '.$e->status.'). '.(in_array($e->status,[401,403],true)?'Revisa la credencial y los permisos de escritura.':($channel==='Email'?'Revisa el remitente de correo configurado y los datos del mensaje.':'Revisa los datos y las condiciones del canal.')));
                }
                $this->markUnknown($id,$request);throw $this->unknown();
            }catch(Throwable $e){$this->markUnknown($id,$request);throw $this->unknown();}
        }finally{query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    private function accepted(array $attempt):array{return ['state'=>'accepted','messageId'=>$attempt['message_id'],'deliveryStatus'=>$attempt['delivery_status'],'requestId'=>$attempt['request_id']];}
    private function markUnknown(int $id,string $request):void{query("UPDATE message_send_attempts SET state='unknown',error_code='unconfirmed' WHERE connection_id=? AND request_id=?",[$id,$request]);}
    private function unknown():SendProblem{return new SendProblem('send_unconfirmed',409,'No se pudo confirmar el envío. Revisa el historial: repetir este mismo mensaje está bloqueado para evitar duplicados.');}
}
