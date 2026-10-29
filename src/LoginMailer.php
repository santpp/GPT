<?php
declare(strict_types=1);

final class MailDeliveryException extends RuntimeException {
    public function __construct(public readonly string $reason='smtp_delivery_failed'){parent::__construct($reason);}
}

final class LoginMailer {
    private Closure $sender;
    public function __construct(?Closure $sender=null){$this->sender=$sender??fn(string $to,string $subject,string $body)=>(new SmtpMailer())->send($to,$subject,$body);}

    public static function configured():bool {
        $port=filter_var(getenv('SMTP_PORT')?:'',FILTER_VALIDATE_INT);
        return filter_var(getenv('LOGIN_MAIL_FROM')?:'',FILTER_VALIDATE_EMAIL)!==false
            &&self::safe(getenv('SMTP_HOST')?:'',253)
            &&self::safe(getenv('SMTP_USERNAME')?:'',253)
            &&is_string(getenv('SMTP_PASSWORD'))&&getenv('SMTP_PASSWORD')!==''
            &&$port!==false&&$port>=1&&$port<=65535
            &&in_array(getenv('SMTP_ENCRYPTION')?:'',['tls','starttls'],true);
    }

    public function sendAccessCode(string $email,string $code):void {
        ($this->sender)($email,'Tu código de acceso a Ágora',"Tu código de acceso es: $code\n\nCaduca en 10 minutos y sólo puede utilizarse una vez.\n");
    }

    public function sendTest(string $email):void {
        ($this->sender)($email,'Prueba de correo de Ágora',"El servidor de Ágora ha entregado correctamente este mensaje al servidor SMTP.\n\nYa puedes probar el acceso del equipo.\n");
    }

    private static function safe(string $value,int $max):bool{return $value!==''&&strlen($value)<=$max&&!preg_match('/[\x00-\x20\x7f]/',$value);}
}

final class SmtpMailer {
    private mixed $stream=null;

    public function send(string $to,string $subject,string $body):void {
        if(!LoginMailer::configured()||filter_var($to,FILTER_VALIDATE_EMAIL)===false)throw new MailDeliveryException('smtp_not_configured');
        $host=getenv('SMTP_HOST');$port=(int)getenv('SMTP_PORT');$encryption=getenv('SMTP_ENCRYPTION');
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'SNI_enabled'=>true]]);
        $remote=($encryption==='tls'?'tls':'tcp').'://'.$host.':'.$port;
        $this->stream=@stream_socket_client($remote,$errno,$error,10,STREAM_CLIENT_CONNECT,$context);
        if(!is_resource($this->stream))throw new MailDeliveryException('smtp_connection_failed');
        stream_set_timeout($this->stream,15);
        try{
            $this->expect([220]);$this->hello();
            if($encryption==='starttls'){
                $this->command('STARTTLS',[220]);
                if(!stream_socket_enable_crypto($this->stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new MailDeliveryException('smtp_tls_failed');
                $this->hello();
            }
            $this->command('AUTH LOGIN',[334]);
            $this->command(base64_encode(getenv('SMTP_USERNAME')),[334]);
            $this->command(base64_encode(getenv('SMTP_PASSWORD')),[235]);
            $from=getenv('LOGIN_MAIL_FROM');
            $this->command('MAIL FROM:<'.$from.'>',[250]);
            $this->command('RCPT TO:<'.$to.'>',[250,251]);
            $this->command('DATA',[354]);
            $domain=parse_url(getenv('APP_ORIGIN')?:'',PHP_URL_HOST)?:'localhost';
            $headers=[
                'Date: '.gmdate('D, d M Y H:i:s').' +0000',
                'From: Ágora <'.$from.'>',
                'To: <'.$to.'>',
                'Subject: '.mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),
                'Message-ID: <'.bin2hex(random_bytes(16)).'@'.$domain.'>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'X-Auto-Response-Suppress: All',
            ];
            $payload=implode("\r\n",$headers)."\r\n\r\n".str_replace(["\r\n","\r"],"\n",$body);
            $payload=str_replace("\n","\r\n",$payload);$payload=preg_replace('/^\./m','..',$payload);
            $this->write($payload."\r\n.\r\n");$this->expect([250]);
            $this->command('QUIT',[221]);
        }catch(MailDeliveryException $e){throw $e;}
        catch(Throwable $e){throw new MailDeliveryException();}
        finally{if(is_resource($this->stream))fclose($this->stream);$this->stream=null;}
    }

    private function hello():void {
        $host=parse_url(getenv('APP_ORIGIN')?:'',PHP_URL_HOST)?:'localhost';
        if(!preg_match('/^[A-Za-z0-9.-]+$/D',$host))$host='localhost';
        $this->command('EHLO '.$host,[250]);
    }
    private function command(string $command,array $codes):void{$this->write($command."\r\n");$this->expect($codes);}
    private function write(string $value):void {
        $written=0;$length=strlen($value);
        while($written<$length){$part=fwrite($this->stream,substr($value,$written));if($part===false||$part===0)throw new MailDeliveryException();$written+=$part;}
    }
    private function expect(array $codes):void {
        $code=0;
        do{$line=fgets($this->stream,4096);if($line===false)throw new MailDeliveryException('smtp_no_response');$code=(int)substr($line,0,3);$more=isset($line[3])&&$line[3]==='-';}while($more);
        if(!in_array($code,$codes,true))throw new MailDeliveryException('smtp_rejected_'.$code);
    }
}
