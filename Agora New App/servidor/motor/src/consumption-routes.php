<?php
declare(strict_types=1);
// Pestaña «Consumos»: sólo la persona propietaria. Se incluye desde router.php con $u, $path, $method y $appRoot ya definidos.
require_once __DIR__.'/AgencyWallet.php';
if(($u['identity_type']??'native')!=='native'||$u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
$tenantId=(int)$u['tenant_id'];
$wallet=new AgencyWallet($tenantId,$GLOBALS['agencyTransport']??null);
$connections=fn()=>query('SELECT id,name,location_id,status FROM ghl_connections WHERE tenant_id=? ORDER BY name',[$tenantId])->fetchAll();
try{
    if($path==='/api/admin/consumption'&&$method==='GET')respond($wallet->settings()+['connections'=>$connections(),'limits'=>['page'=>AgencyWallet::PAGE,'maxPages'=>AgencyWallet::MAX_PAGES]]);
    if($path==='/api/admin/consumption/settings'&&$method==='POST'){
        $b=body();
        $saved=$wallet->save(array_key_exists('companyId',$b)?(string)$b['companyId']:null,isset($b['token'])?(string)$b['token']:null);
        respond($saved);
    }
    if($path==='/api/admin/consumption/token'&&$method==='DELETE')respond($wallet->forget());
    // Agora New App 2.11.8: reglas «los cargos con este dato son de esta subcuenta», para repartir lo que la cuenta no atribuye.
    $rulesKey='agency:wallet-rules:'.$tenantId;
    $rules=function()use($rulesKey):array{$r=json_decode((string)query('SELECT value FROM app_state WHERE name=?',[$rulesKey])->fetchColumn(),true);return is_array($r)?array_values(array_filter($r,fn($x)=>is_array($x)&&isset($x['campo'],$x['valor'],$x['locationId']))):[];};
    $saveRules=fn(array $r)=>query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$rulesKey,json_encode(array_values($r),JSON_THROW_ON_ERROR)]);
    if($path==='/api/admin/consumption/reglas'&&$method==='GET')respond(['reglas'=>$rules()]);
    if($path==='/api/admin/consumption/reglas'&&$method==='POST'){
        $b=body();$campo=trim((string)($b['campo']??''));$valor=trim((string)($b['valor']??''));$locId=trim((string)($b['locationId']??''));
        if($campo===''||mb_strlen($campo)>120||$valor===''||mb_strlen($valor)>80)respond(['error'=>'Elige el dato y su valor.'],422);
        $own=array_column($connections(),'name','location_id');
        if(!isset($own[$locId]))respond(['error'=>'Elige una subcuenta conectada.'],422);
        $r=array_values(array_filter($rules(),fn($x)=>!($x['campo']===$campo&&$x['valor']===$valor)));
        if(count($r)>=100)respond(['error'=>'Ya hay 100 reglas. Quita alguna antes de agregar otra.'],422);
        $r[]=['campo'=>$campo,'valor'=>$valor,'locationId'=>$locId,'creada'=>gmdate('c')];$saveRules($r);
        respond(['reglas'=>$r,'subcuenta'=>$own[$locId]]);
    }
    if($path==='/api/admin/consumption/reglas'&&$method==='DELETE'){
        $b=body();$campo=(string)($b['campo']??'');$valor=(string)($b['valor']??'');
        $r=array_values(array_filter($rules(),fn($x)=>!($x['campo']===$campo&&$x['valor']===$valor)));$saveRules($r);respond(['reglas'=>$r]);
    }
    // El informe del mes: gasto y recargas por subcuenta, con su plan y su saldo.
    if($path==='/api/admin/consumption/report'&&$method==='GET'){
        $s=$wallet->settings();
        if($s['companyId']===''||!$s['tokenSaved'])respond(['error'=>'Falta el Company ID o el token de agencia.','code'=>'agency_not_configured'],409);
        [$month]=AgencyWallet::month($_GET['month']??null);
        $agencyOnly=($_GET['locationId']??'')==='__agency';
        $loc=!$agencyOnly&&is_string($_GET['locationId']??null)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$_GET['locationId'])?$_GET['locationId']:null;
        $own=$connections();$names=[];foreach($own as $c)$names[$c['location_id']]=$c['name'];
        if($loc!==null&&!isset($names[$loc]))respond(['error'=>'Esa subcuenta no está conectada.'],404);
        $got=$wallet->monthRows($month,$agencyOnly||$rules()?null:$loc,array_fill_keys(array_keys($names),true));
        // Agora New App 2.11.8: con una subcuenta elegida se trae todo el mes, para sumarle también lo que le toca por las reglas.
        if(!$got['error']){$got['rows']=AgencyWallet::applyRules($got['rows'],$rules());if($loc!==null)$got['rows']=array_values(array_filter($got['rows'],fn($r)=>$r['locationId']===$loc));}
        $pistas=$loc===null&&!$got['error']?AgencyWallet::hints($got['rows']):null;
        $porRegla=count(array_filter($got['rows'],fn($r)=>!empty($r['porRegla'])));
        if($got['error'])respond(['error'=>'La cuenta no devolvió las transacciones (HTTP '.$got['error']['status'].'). Revisa el Company ID y el token de agencia.','code'=>'wallet_upstream','detail'=>$got['error']],424);
        $summary=AgencyWallet::summarize($got['rows'],$loc!==null||$agencyOnly?1000:60);
        $mine=[];$extra=[];
        foreach($summary as $e){
            if(isset($names[$e['locationId']])){if(!$agencyOnly){$e['locationName']=$names[$e['locationId']];$mine[]=$e;}continue;}
            if($e['locationId']==='agencia'){$e['locationName']='Agencia · sin subcuenta identificada';$e['tipo']='agencia';$extra[]=$e;continue;}
            if($agencyOnly)continue;
            $e['locationName']='Subcuenta no conectada a Ágora ('.$e['locationId'].')';$e['tipo']='otra';$extra[]=$e;
        }
        // Las subcuentas conectadas sin movimientos también salen (con cero), para poder elegirlas.
        $have=array_column($mine,'locationId');
        if(!$agencyOnly)foreach($own as $c)if(($loc===null||$loc===$c['location_id'])&&!in_array($c['location_id'],$have,true))$mine[]=['locationId'=>$c['location_id'],'locationName'=>$c['name'],'gasto'=>0.0,'recargas'=>0.0,'movimientos'=>0,'sinClasificar'=>0,'categorias'=>[],'dias'=>[],'ultimos'=>[]];
        // Plan y saldo: una subcuenta si se eligió, o todas con una hora de caché.
        $planKey='agency:plans:'.$tenantId;$cache=json_decode((string)query('SELECT value FROM app_state WHERE name=?',[$planKey])->fetchColumn(),true);
        $cache=is_array($cache)&&($cache['at']??0)>time()-3600?$cache['items']:[];
        foreach($mine as &$e){
            if(isset($cache[$e['locationId']])){$e['plan']=$cache[$e['locationId']];continue;}
            try{$e['plan']=$wallet->planOf($e['locationId']);$cache[$e['locationId']]=$e['plan'];}catch(Throwable $x){$e['plan']=['plan'=>null,'errores'=>['No se pudo leer el plan.']];}
        }
        unset($e);
        query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$planKey,json_encode(['at'=>time(),'items'=>$cache],JSON_THROW_ON_ERROR)]);
        usort($mine,fn($a,$b)=>$b['gasto']<=>$a['gasto']);
        $subcount=count($mine);
        // La agencia y lo que no es de una subcuenta conectada van después, y cuentan en los totales.
        $mine=array_merge($mine,$loc!==null?[]:$extra);
        respond(['month'=>$month,'pages'=>$got['pages'],'partial'=>$got['partial'],'locations'=>$mine,'subcuentas'=>$subcount,
            'totales'=>['gasto'=>round(array_sum(array_column($mine,'gasto')),4),'recargas'=>round(array_sum(array_column($mine,'recargas')),4)],'pistas'=>$pistas,'porRegla'=>$porRegla,'reglas'=>$rules(),'readAt'=>gmdate('c')]);
    }
}catch(InvalidArgumentException $e){respond(['error'=>$e->getMessage()],422);}
catch(RuntimeException $e){respond(['error'=>$e->getMessage()],409);}
respond(['error'=>'Not found'],404);
