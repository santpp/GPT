<?php
declare(strict_types=1);
// Las rutas de /api/workspace que no dependen de una subcuenta: tareas, auditoría, usuarios, identidad del
// espacio y resumen. Se incluye antes de live-inbox.php; si la ruta no es de aquí, no hace nada.
require_once __DIR__.'/Roles.php';
require_once __DIR__.'/Audit.php';
require_once __DIR__.'/WorkspaceTasks.php';
require_once __DIR__.'/Maintenance.php';

$extra=null;
foreach(['tareas'=>'~^/api/workspace/tareas(?:/(tk_\d+|\d+)/estado)?$~','auditoria'=>'~^/api/workspace/auditoria(/depurar)?$~','branding'=>'~^/api/workspace/branding(/logo)?$~','resumen'=>'~^/api/workspace/resumen$~'] as $name=>$pattern)
    if(preg_match($pattern,$path,$extraMatch)){$extra=$name;break;}
if($extra===null)return;
// Quien entra con correo y clave sin ser propietario no tiene acceso aquí (como en el resto del espacio de trabajo).
if(($u['identity_type']??'native')==='native'&&$u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);

/** Quién es quien mira: su rol, sus nombres (puede llamarse distinto en cada subcuenta) y las subcuentas donde está. */
$context=(function(array $u):array{
    if(($u['identity_type']??'native')==='native')return ['id'=>'n'.$u['id'],'name'=>Audit::actor($u)['name'],'names'=>[Audit::actor($u)['name']],'role'=>Roles::of($u),'connections'=>null,'native'=>true];
    $rows=query('SELECT eu.*,eu.connection_id AS cid FROM external_identity_memberships m JOIN external_users eu ON eu.id=m.external_user_row_id JOIN ghl_connections c ON c.id=eu.connection_id WHERE m.identity_id=? AND eu.active=TRUE AND c.tenant_id=?',[$u['id'],$u['tenant_id']])->fetchAll();
    $rank=['CONSULTA'=>0,'EJECUTIVO'=>1,'SUPERVISOR'=>2,'ADMIN'=>3];$best='CONSULTA';$names=[];$connections=[];
    foreach($rows as $row){
        $row['capabilities']=PermissionPolicy::capabilities($row+['identity_type'=>'external']);
        $row['can_assign']=($row['role_name']??null)==='admin'||($row['role_type']??null)==='agency';
        $role=Roles::of($u,$row);if($rank[$role]>$rank[$best])$best=$role;
        $names[$row['display_name']]=true;$connections[]=(int)$row['cid'];
    }
    return ['id'=>'e'.$u['id'],'name'=>(string)(array_key_first($names)??$u['email']),'names'=>array_keys($names),'role'=>$best,'connections'=>$connections,'native'=>false];
})($u);
$role=$context['role'];
$seesAll=in_array($role,['SUPERADMIN','ADMIN','SUPERVISOR'],true);
$tenant=(int)$u['tenant_id'];
$period=is_string($_GET['period']??null)&&preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$_GET['period'])?$_GET['period']:gmdate('Y-m');

// ---- Tareas ---------------------------------------------------------------------------------------
if($extra==='tareas'){
    if(($extraMatch[1]??'')!==''){
        if($method!=='POST')respond(['error'=>'Método no permitido'],405);
        $task=WorkspaceTasks::find($tenant,$extraMatch[1]);
        if(!$task)respond(['error'=>'Esa tarea ya no existe.'],404);
        $mineTask=$task['created_by']===$context['id']||in_array((string)$task['assignee_name'],$context['names'],true);
        if(!$seesAll&&!$mineTask)respond(['error'=>'Esa tarea no es tuya.'],403);
        if($role==='CONSULTA')respond(['error'=>'Tu rol sólo consulta.'],403);
        try{WorkspaceTasks::setState($task,(string)(body()['estado']??''));}
        catch(InvalidArgumentException $e){respond(['error'=>$e->getMessage()],422);}
        respond(['id'=>'tk_'.$task['id'],'estado'=>(body()['estado'])]);
    }
    if($method==='GET'){
        $user=is_string($_GET['usuario']??null)?mb_substr($_GET['usuario'],0,190):'';
        respond(WorkspaceTasks::list($tenant,$period,$context['id'],$context['names'],$seesAll,$user,$context['connections']));
    }
    if($method!=='POST')respond(['error'=>'Método no permitido'],405);
    if(!Roles::can($role,'tareas'))respond(['error'=>'Tu rol sólo consulta.'],403);
    $input=body();
    // Quien sólo ve lo suyo se asigna las tareas a sí mismo.
    if(!$seesAll)$input['responsable']=$context['name'];
    elseif(trim((string)($input['responsable']??''))==='')$input['responsable']=$context['name'];
    try{respond(WorkspaceTasks::create($tenant,$context['id'],$input),201);}
    catch(InvalidArgumentException $e){respond(['error'=>$e->getMessage()],422);}
}

// ---- Auditoría (sólo la persona propietaria) ------------------------------------------------------
if($extra==='auditoria'){
    if(!Roles::can($role,'auditoria'))respond(['error'=>'La auditoría es sólo de la persona propietaria.'],403);
    if(($extraMatch[1]??'')==='/depurar'){
        if($method!=='POST')respond(['error'=>'Método no permitido'],405);
        $purged=Audit::purgeOld($tenant);
        Audit::record($u,null,['action'=>'elimino','text'=>'Borró '.$purged['borrados'].' registros de hace seis meses o más','module'=>'ajuste','object'=>'Auditoría']);
        respond($purged);
    }
    if($method!=='GET')respond(['error'=>'Método no permitido'],405);
    respond(Audit::list($tenant,$period,null,['modulo'=>(string)($_GET['modulo']??''),'usuario'=>(string)($_GET['usuario']??''),'contacto'=>(string)($_GET['contacto']??'')]));
}

// ---- Identidad del espacio ------------------------------------------------------------------------
if($extra==='branding'){
    require_once __DIR__.'/Branding.php';
    if(($extraMatch[1]??'')==='/logo'){
        $logo=$method==='GET'?Branding::logo($tenant):null;
        if(!$logo)respond(['error'=>'Sin logo'],404);
        header_remove('Content-Type');header('Content-Type: '.$logo[0]);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, max-age=3600');
        // Aunque alguien abra la dirección del logo directamente, nada de lo que lleve puede ejecutarse.
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
        http_response_code(200);echo $logo[1];exit;
    }
    if($method==='POST'){
        if(!Roles::can($role,'branding'))respond(['error'=>'Sólo la persona propietaria cambia la identidad del espacio.'],403);
        try{Branding::save($tenant,body(Branding::MAX_REQUEST));}
        catch(InvalidArgumentException $e){respond(['error'=>$e->getMessage()],422);}
        Audit::record($u,null,['action'=>'edito','text'=>'Cambió la identidad del espacio','module'=>'ajuste','object'=>'Nombre y logo']);
    }
    elseif($method!=='GET')respond(['error'=>'Método no permitido'],405);
    respond(Branding::get($tenant,Maintenance::on()));
}

// ---- Resumen del negocio --------------------------------------------------------------------------
if($extra==='resumen'){
    if($method!=='GET')respond(['error'=>'Método no permitido'],405);
    if(!Roles::can($role,'resumen'))respond(['error'=>'El resumen del negocio es sólo para la persona propietaria.'],403);
    require_once __DIR__.'/BusinessSummary.php';
    respond(BusinessSummary::get($tenant,$period));
}
