<?php
declare(strict_types=1);
// 1.10.33 · Consumos: de qué subcuenta es cada gasto (búsqueda en todos los datos del cargo, pistas y reglas). Sin base de datos ni red.
require __DIR__.'/../src/AgencyWallet.php';
$n=0;function ck(bool $v,string $l):void{global $n;if(!$v){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
$known=['locA'=>true,'locB'=>true];
$tx=[];
for($i=0;$i<30;$i++)$tx[]=['amount'=>-0.074,'chargeType'=>'whatsapp_marketing_message','description'=>"whatsapp_marketing_message ref: wamid.HBg$i",'meta'=>['phoneNumber'=>$i%3?'+58414001':'+58424002'],'settlementTime'=>'2026-11-12T10:00:00Z'];
$tx[]=['amount'=>-0.5,'chargeType'=>'conversation_ai','entity'=>['type'=>'location','id'=>'locB'],'settlementTime'=>'2026-11-13T10:00:00Z'];
$tx[]=['locationId'=>'locA','amount'=>-1.25,'type'=>'SMS','description'=>'SMS','settlementTime'=>'2026-11-10T10:00:00Z'];
$tx[]=['amount'=>50,'type'=>'Recharge','description'=>'Recarga de saldo','settlementTime'=>'2026-11-02T10:00:00Z'];
$rows=array_map(fn($t)=>AgencyWallet::normalize($t,$known),$tx);
ck($rows[30]['locationId']==='locB','el identificador de la subcuenta se encuentra aunque venga en otro dato');
ck($rows[0]['kind']==='WhatsApp · marketing'&&$rows[0]['description']===''&&$rows[0]['campos']['ref']==='wamid.HBg0','el tipo se lee en palabras y la referencia sale de la descripción');
ck($rows[31]['locationId']==='locA'&&$rows[31]['campos']===[],'lo que ya tiene subcuenta no guarda datos sueltos');
$h=AgencyWallet::hints($rows);$c=array_column($h['campos'],null,'campo');
ck($h['movimientos']===30&&abs($h['monto']-2.22)<1e-9,'las pistas cuentan los 30 cargos sin subcuenta (2,22)');
ck(isset($c['meta.phoneNumber'])&&count($c['meta.phoneNumber']['valores'])===2&&!isset($c['ref'])&&in_array('ref',$h['unicos'],true),'se ofrece el número (2 valores), no la referencia única');
ck(($c['chargeType']['valores'][0]['texto']??'')==='WhatsApp · marketing','el tipo de cargo de las pistas también se lee en palabras');
$r2=AgencyWallet::applyRules($rows,[['campo'=>'meta.phoneNumber','valor'=>'+58414001','locationId'=>'locA']]);
$s=array_column(AgencyWallet::summarize($r2),null,'locationId');
ck(abs($s['locA']['gasto']-(1.25+20*0.074))<1e-9&&abs($s['agencia']['gasto']-10*0.074)<1e-9,'la regla pasa 20 cargos a la subcuenta y 10 quedan en la agencia');
$t1=array_sum(array_column(AgencyWallet::summarize($rows),'gasto'));$t2=array_sum(array_column($s,'gasto'));
ck(abs($t1-$t2)<1e-9,'el total no cambia: solo se reparte');
ck(count(array_filter($r2,fn($r)=>!empty($r['porRegla'])))===20,'se sabe cuántos cargos entraron por regla');
echo "$n comprobaciones\n";
