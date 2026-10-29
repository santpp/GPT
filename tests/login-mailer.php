<?php
declare(strict_types=1);
require __DIR__.'/../src/LoginMailer.php';
$previous=[];foreach(['LOGIN_MAIL_FROM','SMTP_HOST','SMTP_PORT','SMTP_USERNAME','SMTP_PASSWORD','SMTP_ENCRYPTION'] as $name)$previous[$name]=getenv($name);
function mailCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
try{
 foreach(['LOGIN_MAIL_FROM'=>'acceso@example.test','SMTP_HOST'=>'smtp.example.test','SMTP_PORT'=>'465','SMTP_USERNAME'=>'acceso@example.test','SMTP_PASSWORD'=>'private-test-password','SMTP_ENCRYPTION'=>'tls'] as $name=>$value)putenv($name.'='.$value);
 mailCheck(LoginMailer::configured(),'complete SMTP configuration recognized');$sent=[];
 $mailer=new LoginMailer(function(string $to,string $subject,string $body)use(&$sent):void{$sent=[$to,$subject,$body];});
 $mailer->sendAccessCode('team@example.test','123456');
 mailCheck($sent[0]==='team@example.test'&&str_contains($sent[1],'Ágora')&&str_contains($sent[2],'123456'),'access code delegated without exposing SMTP credentials');
 putenv('SMTP_ENCRYPTION=none');mailCheck(!LoginMailer::configured(),'unencrypted production SMTP configuration rejected');
}finally{foreach($previous as $name=>$value)putenv($value===false?$name:$name.'='.$value);}
