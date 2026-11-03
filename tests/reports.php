<?php
declare(strict_types=1);
// Operación del día, eficacia de ventas, inversión en anuncios y leads por persona, con una cuenta de mentira.
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/OperationsReport.php';
$n=0;function rk(bool $ok,string $l):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $l\n");exit(1);}$n++;echo "PASS $l\n";}
putenv('APP_TIMEZONE=America/Caracas');   // UTC-4, sin horario de verano
$s=bin2hex(random_bytes(4));
query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Rep '.$s,'rep-'.$s]);$t=(int)db()->lastInsertId();
try{
  query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$t,'C','loc-'.$s,'x']);$c=(int)db()->lastInsertId();
  $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$c])->fetch();
  foreach([['ana','Ana Ruiz'],['luis','Luis Mora']] as [$id,$name])query("INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,active) VALUES(?,?,?,?,'{}','[]',1)",[$c,$id,$id.'@example.test',$name]);
  $now=strtotime('2026-11-20 20:00:00 UTC');          // 16:00 en Caracas
  $dayStart=strtotime('2026-11-20 04:00:00 UTC');     // 00:00 en Caracas
  $conv=[
    ['cv1','ana','inbound',$now-120,2],        // espera 2 min
    ['cv2','ana','inbound',$now-1500,1],       // espera 25 min: fuera de la meta
    ['cv3','luis','outbound',$now-600,0],      // ya atendida
    ['cv4',null,'inbound',$now-300,1],         // sin asignar, espera 5 min
    ['cv5','luis','inbound',$dayStart-3600,3], // de ayer: no cuenta en el día, pero sí sin leer
  ];
  foreach($conv as [$id,$who,$dir,$at,$unread])query('INSERT INTO conversation_index(connection_id,conversation_id,payload,unread_count,assigned_to) VALUES(?,?,?,?,?)',[$c,$id,json_encode(['id'=>$id,'locationId'=>'loc-'.$s,'assignedTo'=>$who,'lastMessageDirection'=>$dir,'lastMessageDate'=>$at*1000,'unreadCount'=>$unread]),$unread,$who]);
  $free=fn()=>query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$c]);
  $calls=[];
  $contacts=[
    ['id'=>'p1','dateAdded'=>gmdate('c',$now-3600),'source'=>'Instagram','assignedTo'=>'ana'],
    ['id'=>'p2','dateAdded'=>gmdate('c',$now-7200),'source'=>'Instagram','assignedTo'=>'luis'],
    ['id'=>'p3','dateAdded'=>gmdate('c',$now-9000),'source'=>'Facebook','assignedTo'=>'ana'],
    ['id'=>'p4','dateAdded'=>gmdate('c',$dayStart-7200),'source'=>'Facebook','assignedTo'=>'ana'],   // ayer
  ];
  $won=[
    ['id'=>'w1','locationId'=>'loc-'.$s,'monetaryValue'=>1000,'assignedTo'=>'ana','pipelineId'=>'pl1','createdAt'=>'2026-11-02T00:00:00Z','lastStatusChangeAt'=>'2026-11-12T00:00:00Z','contact'=>['name'=>'A']],   // 10 días
    ['id'=>'w2','locationId'=>'loc-'.$s,'monetaryValue'=>500,'assignedTo'=>'ana','pipelineId'=>'pl1','createdAt'=>'2026-11-08T00:00:00Z','lastStatusChangeAt'=>'2026-11-14T00:00:00Z','contact'=>['name'=>'B']],     // 6 días
    ['id'=>'w3','locationId'=>'loc-'.$s,'monetaryValue'=>300,'assignedTo'=>'luis','pipelineId'=>'pl2','createdAt'=>'2026-11-10T00:00:00Z','lastStatusChangeAt'=>'2026-11-18T00:00:00Z','contact'=>['name'=>'C']],   // 8 días
  ];
  $ads=['INTEGRATION'=>['rows'=>[['spend'=>'100.50','impressions'=>9000,'clicks'=>200,'conversions'=>4]]],'AD_MANAGER'=>[]];$adFail=false;
  $gw=new GhlGateway(function()use(&$calls,&$contacts,&$won,&$ads,&$adFail){return new GhlClient('fake',function($path,$method='GET',$body=null)use(&$calls,&$contacts,&$won,&$ads,&$adFail){
    $calls[]=$path;
    if(str_contains($path,'/contacts/search')){return ['status'=>200,'body'=>json_encode(['total'=>7,'contacts'=>[]])];}
    if(str_contains($path,'/contacts/'))return ['status'=>200,'body'=>json_encode(['contacts'=>$contacts])];
    if(str_contains($path,'/opportunities/search'))return ['status'=>200,'body'=>json_encode(['opportunities'=>$won,'meta'=>['total'=>count($won)]])];
    if(str_contains($path,'/opportunities/pipelines'))return ['status'=>200,'body'=>json_encode(['pipelines'=>[['id'=>'pl1','name'=>'Admisiones','stages'=>[]],['id'=>'pl2','name'=>'Cursos','stages'=>[]]]])];
    if(str_contains($path,'/ad-publishing/')){
      if($adFail)return ['status'=>403,'body'=>'{}'];
      return ['status'=>200,'body'=>json_encode(str_contains($path,'type=INTEGRATION')?$ads['INTEGRATION']:$ads['AD_MANAGER'])];
    }
    return ['status'=>404,'body'=>'{}'];
  });});
  $report=new OperationsReport($gw);
  $admin=['external_user_id'=>null,'capabilities'=>['viewConversations'=>true,'viewOpportunities'=>true,'assignedDataOnly'=>false]];
  $ana=['external_user_id'=>'ana','capabilities'=>['viewConversations'=>true,'viewOpportunities'=>true,'assignedDataOnly'=>true]];

  // ---- el día
  $free();$d=$report->operationDay($connection,$admin,'2026-11-20',$now);
  rk($d['conversacionesDia']===4&&$d['atendidas']===1,'the day counts conversations by the business timezone: four today (one from yesterday stays out), one already answered');
  rk($d['sinLeer']===4&&array_sum(array_column($d['sinLeerPorAsesor'],'n'))===4,'unread counts every conversation with something unread, by advisor');
  $by=array_column($d['sinLeerPorAsesor'],'n','nombre');rk(($by['Ana Ruiz']??0)===2&&($by['Sin asignar']??0)===1&&($by['Luis Mora']??0)===1,'and names the advisors, with «Sin asignar» for the rest');
  rk($d['slaIncumplido']===1&&$d['slaPromedioSeg']===640&&$d['slaPromedio']==='10 min 40 s','the response time is measured on what is waiting now: one over five minutes, average 10 min 40 s');
  rk($d['peorRespuesta']==='25 min'&&$d['peorAsesor']==='Ana Ruiz'&&$d['slaMetaTexto']==='5 min','the slowest wait and whose it is');
  rk($d['leads']===3&&$d['leadsAyer']===1,'leads today and yesterday come from the recent contacts');
  $src=array_column($d['leadsPorFuente'],'n','fuente');rk($src['Instagram']===2&&$src['Facebook']===1,'by source');
  $usr=array_column($d['leadsPorUsuario'],'n','nombre');rk($usr['Ana Ruiz']===2&&$usr['Luis Mora']===1,'and by executive');
  rk($d['sinLeerAyer']===null&&$d['slaPromedioSegAyer']===null,'with no day before saved, there is no comparison rather than a made-up one');
  rk($d['slaBase']==='pendientes','the report says what the response time is based on');
  $calls=[];$d2=$report->operationDay($connection,$admin,'2026-11-20',$now);rk($calls===[],'a second look costs no request: the contacts are kept for a few minutes');
  // «ayer» lo da la foto del día anterior
  query("INSERT INTO daily_metrics(connection_id,day,kind,payload) VALUES(?,?,'dia',?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)",[$c,'2026-11-19',json_encode(['sinLeer'=>9,'slaIncumplido'=>5,'slaPromedioSeg'=>900,'leads'=>2])]);
  $d3=$report->operationDay($connection,$admin,'2026-11-20',$now);
  rk($d3['sinLeerAyer']===9&&$d3['slaIncumplidoAyer']===5&&$d3['slaPromedioSegAyer']===900,'yesterday comes from the snapshot saved the day before');
  $mine=$report->operationDay($connection,$ana,'2026-11-20',$now);
  rk($mine['conversacionesDia']===2&&$mine["sinLeer"]===2&&$mine['leads']===2&&array_column($mine['leadsPorUsuario'],'nombre')===['Ana Ruiz'],'a person who only sees their own gets only their own figures');

  // ---- eficacia
  $free();$e=$report->efficiency($connection,$admin,'2026-11','','',strtotime('2026-11-20 12:00:00 UTC'));
  rk($e['cerradas']===3&&$e['valorTotal']===1800.0&&$e['ticket']===600.0,'closed sales, their value and the average ticket');
  rk($e['duracionPromedioDias']===8&&$e['duracionTexto']==='8 días','the closing time is the average days between creation and winning (10, 6 and 8)');
  rk($e['velocidad']===round(1800/20*30,2),'speed projects the pace of the month so far to the whole month');
  $rows=array_column($e['porUsuario'],null,'nombre');rk($rows['Ana Ruiz']['cerradas']===2&&$rows['Ana Ruiz']['dias']===8&&$rows['Luis Mora']['dias']===8,'by executive');
  rk(array_column($e['pipelines'],'nombre','id')==['pl1'=>'Admisiones','pl2'=>'Cursos'],'the pipelines are offered to filter by');
  $free();$e1=$report->efficiency($connection,$admin,'2026-11','pl2','',strtotime('2026-11-20 12:00:00 UTC'));rk($e1['cerradas']===1&&$e1['pipelineId']==='pl2','it filters by pipeline');
  $free();$e2=$report->efficiency($connection,$admin,'2026-11','','luis',strtotime('2026-11-20 12:00:00 UTC'));rk($e2['cerradas']===1&&$e2['valorTotal']===300.0,'and by executive');
  $free();$e3=$report->efficiency($connection,$ana,'2026-11','','luis',strtotime('2026-11-20 12:00:00 UTC'));rk($e3['cerradas']===2&&$e3['usuarios']===[]&&$e3['userId']==='ana','a person who only sees their own cannot ask for someone else\'s');
  $won=[];$free();query("DELETE FROM directory_cache WHERE connection_id=?",[$c]);$e4=$report->efficiency($connection,$admin,'2026-11','','',strtotime('2026-11-20 12:00:00 UTC'));
  rk($e4['cerradas']===0&&$e4['duracionTexto']==='—'&&$e4['ticket']===null,'with nothing closed it shows dashes, not zeros that look like results');

  // ---- anuncios
  $free();$a=$report->ads($connection,'2026-11','2026-11-20');
  rk($a['estado']==='ok'&&$a['mes']===100.5&&$a['impresiones']===9000&&$a['clics']===200&&$a['conversiones']===4&&$a['costePorConversion']===25.13,'ad spend comes from the connected ad account, with its cost per conversion');
  rk(array_key_exists('presupuestoMes',$a)&&$a['presupuestoMes']===null,'and no budget is invented');
  query("DELETE FROM directory_cache WHERE connection_id=?",[$c]);$adFail=true;$free();$denied=$report->ads($connection,'2026-11','2026-11-20');rk($denied['estado']==='permission','a key without the ads permission is reported as such');
  $adFail=false;$ads['INTEGRATION']=[];query("DELETE FROM directory_cache WHERE connection_id=?",[$c]);$free();$none=$report->ads($connection,'2026-11','2026-11-20');rk($none['estado']==='not_connected','no ad account is reported as not connected');

  // ---- leads por persona
  query("DELETE FROM directory_cache WHERE connection_id=?",[$c]);$free();$l=$report->leadsByMember($connection,'2026-11',['ana','luis']);
  rk($l==['ana'=>7,'luis'=>7],'leads per team member in the month come from one count per person');
  $calls=[];$report->leadsByMember($connection,'2026-11',['ana','luis']);rk($calls===[],'and are kept for a quarter of an hour');
  echo "$n report checks\n";
}finally{
  query('DELETE FROM daily_metrics WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$t]);
  query('DELETE FROM ghl_connections WHERE tenant_id=?',[$t]);query('DELETE FROM tenants WHERE id=?',[$t]);
}
