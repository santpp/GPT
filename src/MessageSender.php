<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
final class SendProblem extends RuntimeException {
    public function __construct(public readonly string $reason,public readonly int $httpStatus,string $message,public readonly int $retryAfter=0){parent::__construct($message);}
}
final class MessageSender {
    public const CHANNELS=['TYPE_SMS'=>'SMS','TYPE_WHATSAPP'=>'WhatsApp','TYPE_INSTAGRAM'=>'IG','TYPE_FACEBOOK'=>'FB','TYPE_EMAIL'=>'Email'];
    public const LABELS=['SMS'=>'SMS','WhatsApp'=>'WhatsApp','IG'=>'Instagram','FB'=>'Messenger','Email'=>'Email'];
    // A thread the contact opened can always be answered on its own channel. The
    // others need an address of the contact, so they are offered only when the
    // account's own record has one. Nothing here is taken from the browser.
    private const NEEDS_PHONE=['SMS','WhatsApp'];
    private const NEEDS_EMAIL=['Email'];

    /** @return list<array{type:string,label:string,needsSubject:bool}> */
    public static function availableChannels(array $connection,array $conversation):array {
        $current=self::CHANNELS[$conversation['lastMessageType']??'']??null;
        $contact=$conversation['contactId']??null;
        $profile=is_string($contact)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contact)
            ? query('SELECT payload FROM contact_profile_cache WHERE connection_id=? AND contact_id=?',[$connection['id'],$contact])->fetchColumn()
            : false;
        $profile=is_string($profile)?json_decode($profile,true):null;
        $hasPhone=is_array($profile)&&is_string($profile['phone']??null)&&trim($profile['phone'])!=='';
        $hasEmail=is_array($profile)&&is_string($profile['email']??null)&&trim($profile['email'])!=='';
        $allowed=$current!==null?[$current]:[];
        foreach(self::LABELS as $type=>$label) {
            if(in_array($type,$allowed,true))continue;
            if(in_array($type,self::NEEDS_PHONE,true)&&$hasPhone)$allowed[]=$type;
            if(in_array($type,self::NEEDS_EMAIL,true)&&$hasEmail)$allowed[]=$type;
        }
        // Every channel is published so the panel can show the whole row; the
        // ones that cannot be used say why instead of silently disappearing.
        $all=[];
        foreach(self::LABELS as $type=>$label) {
            $usable=in_array($type,$allowed,true);
            $all[]=[
                'type'=>$type,'label'=>$label,'available'=>$usable,'needsSubject'=>$type==='Email',
                'reason'=>$usable?null:(in_array($type,self::NEEDS_PHONE,true)?'needs_phone':(in_array($type,self::NEEDS_EMAIL,true)?'needs_email':'needs_thread')),
            ];
        }
        // The thread's own channel leads, then the rest in a stable order.
        usort($all,fn(array $a,array $b):int=>[$a['type']!==$current,!$a['available']]<=>[$b['type']!==$current,!$b['available']]);
        return $all;
    }
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}
    public function send(array $connection,array $conversation,array $input):array {
        $id=(int)$connection['id'];$conversationId=$conversation['id']??'';
        $request=$input['requestId']??null;$text=$input['message']??null;
        $template=is_array($input['template']??null)?$input['template']:null;
        if(!is_string($request)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$request))throw new SendProblem('invalid_message',422,'Escribe un mensaje de hasta 4.000 caracteres.');
        if(!$template&&(!is_string($text)||trim($text)===''||mb_strlen($text)>4000))throw new SendProblem('invalid_message',422,'Escribe un mensaje de hasta 4.000 caracteres.');
        // The choice is checked against the set the server computes, never trusted.
        $available=array_column(array_values(array_filter(self::availableChannels($connection,$conversation),fn(array $c):bool=>$c['available'])),'type');
        $channel=$input['channel']??null;
        if(!is_string($channel)||!in_array($channel,$available,true))throw new SendProblem('unsupported_channel',422,'Ese canal no está disponible para este contacto.');
        $contact=$conversation['contactId']??null;
        if(!is_string($contact)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$contact)||($conversation['locationId']??null)!==$connection['location_id'])throw new SendProblem('invalid_contact',422,'Actualiza la conversación antes de responder.');
        if($template)$payload=$this->templatePayload($connection,$contact,$channel,$template);
        else $payload=['type'=>$channel,'contactId'=>$contact,'message'=>trim($text)];
        if(!$template&&$channel==='Email'){
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
    /**
     * Una plantilla aprobada de WhatsApp: se busca en la lista del servidor, el
     * número que envía es el de la cuenta y el destino, el teléfono de la ficha.
     * Del navegador sólo llegan qué plantilla y lo escrito en sus variables.
     */
    private function templatePayload(array $connection,string $contact,string $channel,array $input):array {
        require_once __DIR__.'/WhatsappTemplates.php';
        if($channel!=='WhatsApp')throw new SendProblem('template_channel',422,'Las plantillas son sólo para WhatsApp.');
        $from=$connection['whatsapp_number_id']??null;
        if(!is_string($from)||$from==='')throw new SendProblem('number_missing',409,'Falta el identificador del número de WhatsApp de esta cuenta. El administrador lo añade en Conexiones.');
        $profile=query('SELECT payload FROM contact_profile_cache WHERE connection_id=? AND contact_id=?',[$connection['id'],$contact])->fetchColumn();
        $profile=is_string($profile)?json_decode($profile,true):null;
        $to=WhatsappTemplates::phone(is_array($profile)?($profile['phone']??null):null);
        if($to===null)throw new SendProblem('needs_phone',422,'La ficha de este contacto no tiene un teléfono válido para WhatsApp.');
        $id=$input['id']??null;
        try {
            $list=(new WhatsappTemplates($this->gateway))->list($connection);
            $chosen=null;foreach($list as $item)if(is_string($id)&&$item['id']===$id)$chosen=$item;
            if(!$chosen)throw new SendProblem('template_unknown',422,'Esa plantilla ya no está aprobada en esta cuenta. Actualiza la lista.');
            return WhatsappTemplates::payload($chosen,is_array($input['values']??null)?$input['values']:[],$contact,$from,$to);
        } catch(TemplateProblem $e) {throw new SendProblem($e->reason,$e->httpStatus,$e->getMessage());}
        catch(GhlCooldown $e) {throw new SendProblem('send_paused',429,'Envío pausado. El mensaje todavía no se ha enviado.',$e->retryAfter);}
        catch(GhlApiException $e) {throw new SendProblem('templates_unavailable',424,in_array($e->status,[401,403],true)?'La clave de la cuenta no permite leer plantillas. Hay que añadirle el permiso de plantillas.':'No se pudieron leer las plantillas de la cuenta. Vuelve a intentarlo.');}
    }
    private function accepted(array $attempt):array{return ['state'=>'accepted','messageId'=>$attempt['message_id'],'deliveryStatus'=>$attempt['delivery_status'],'requestId'=>$attempt['request_id']];}
    private function markUnknown(int $id,string $request):void{query("UPDATE message_send_attempts SET state='unknown',error_code='unconfirmed' WHERE connection_id=? AND request_id=?",[$id,$request]);}
    private function unknown():SendProblem{return new SendProblem('send_unconfirmed',409,'No se pudo confirmar el envío. Revisa el historial: repetir este mismo mensaje está bloqueado para evitar duplicados.');}
}
