<?php
declare(strict_types=1);
// Pestaña «Consumos»: sólo la persona propietaria. Se incluye desde router.php con $u, $path, $method y $appRoot ya definidos.
require_once __DIR__.'/AgencyWallet.php';
if(($u['identity_type']??'native')!=='native'||$u['role']!=='administrator')respond(['error'=>'Acceso restringido'],403);
$tenantId=(int)$u['tenant_id'];
$wallet=new AgencyWallet($tenantId,$GLOBALS['agencyTransport']??null);
$connections=fn()=>query('SELECT id,name,location_id,status FROM ghl_connections WHERE tenant_id=? ORDER BY name',[$tenantId])->fetchAll();
$pretty=function(string $body)use($wallet):string{
    $d=json_decode($body,true);
    $text=is_array($d)?json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):$body;
    return mb_substr($wallet->redact($text),0,30000);
};
try{
    if($path==='/api/admin/consumption'&&$method==='GET')respond($wallet->settings()+['connections'=>$connections(),'limits'=>['page'=>AgencyWallet::PAGE,'maxPages'=>AgencyWallet::MAX_PAGES]]);
    if($path==='/api/admin/consumption/settings'&&$method==='POST'){
        $b=body();
        $saved=$wallet->save(array_key_exists('companyId',$b)?(string)$b['companyId']:null,isset($b['token'])?(string)$b['token']:null);
        respond($saved);
    }
    if($path==='/api/admin/consumption/token'&&$method==='DELETE')respond($wallet->forget());
    // Probar una llamada y ver la respuesta tal cual (con el token tachado): para saber cuál de las dos formas contesta tu cuenta.
    if($path==='/api/admin/consumption/test'&&$method==='POST'){
        $b=body();
        $target=in_array($b['target']??'',['transactions','balance','subscription','plan'],true)?$b['target']:'transactions';
        $verb=($b['method']??'POST')==='GET'?'GET':'POST';
        $month=(string)($b['month']??gmdate('Y-m'));
        $loc=is_string($b['locationId']??null)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$b['locationId'])?$b['locationId']:null;
        $version=is_string($b['version']??null)&&preg_match('/^[A-Za-z0-9.-]{1,20}$/D',$b['version'])?$b['version']:null;
        $limit=min(AgencyWallet::PAGE,max(1,(int)($b['limit']??50)));
        if(in_array($target,['balance','subscription'],true)&&!$loc)respond(['error'=>'Elige una subcuenta para esta prueba.'],422);
        $r=match($target){
            'balance'=>$wallet->balance($loc??'',$version),
            'subscription'=>$wallet->subscription($loc??'',$version),
            'plan'=>$wallet->plan(is_string($b['planId']??null)?$b['planId']:'',$version),
            default=>$wallet->transactions($verb,$month,$loc,0,$limit,$version),
        };
        $analysis=null;
        $data=json_decode($r['body'],true);
        if($target==='transactions'&&is_array($data)){
            $rows=AgencyWallet::rows($data);
            $first=$rows[0]??null;$norm=array_map([AgencyWallet::class,'normalize'],$rows);
            $count=['gasto'=>0,'recarga'=>0,'unknown'=>0];foreach($norm as $n)$count[$n['direction']]=($count[$n['direction']]??0)+1;
            $analysis=['topLevelKeys'=>array_keys($data),'rows'=>count($rows),'firstRowKeys'=>$first?array_keys($first):[],'classified'=>$count,'withLocation'=>count(array_filter($norm,fn($n)=>$n['locationId']!==null)),'withAmount'=>count(array_filter($norm,fn($n)=>$n['amount']!==null)),'firstNormalized'=>$norm[0]??null];
        }
        respond(['request'=>['method'=>$r['method'],'url'=>$r['url'],'version'=>$r['version']],'status'=>$r['status'],'ms'=>$r['ms'],'headers'=>$r['headers'],'error'=>$r['error'],'analysis'=>$analysis,'body'=>$pretty($r['body'])]);
    }
    // El informe del mes: gasto y recargas por subcuenta, con su plan y su saldo.
    if($path==='/api/admin/consumption/report'&&$method==='GET'){
        $s=$wallet->settings();
        if($s['companyId']===''||!$s['tokenSaved'])respond(['error'=>'Falta el Company ID o el token de agencia.','code'=>'agency_not_configured'],409);
        [$month]=AgencyWallet::month($_GET['month']??null);
        $loc=is_string($_GET['locationId']??null)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$_GET['locationId'])?$_GET['locationId']:null;
        $preferred=($_GET['method']??'POST')==='GET'?'GET':'POST';
        $own=$connections();$names=[];foreach($own as $c)$names[$c['location_id']]=$c['name'];
        if($loc!==null&&!isset($names[$loc]))respond(['error'=>'Esa subcuenta no está conectada.'],404);
        $got=$wallet->monthRows($preferred,$month,$loc);
        if($got['error'])respond(['error'=>'La cuenta no devolvió las transacciones (HTTP '.$got['error']['status'].'). Usa «Probar» para ver la respuesta.','code'=>'wallet_upstream','detail'=>$got['error']],424);
        $summary=AgencyWallet::summarize($got['rows']);
        $mine=array_values(array_filter($summary,fn($e)=>isset($names[$e['locationId']])));
        $others=array_values(array_filter($summary,fn($e)=>!isset($names[$e['locationId']])));
        foreach($mine as &$e)$e['locationName']=$names[$e['locationId']];
        unset($e);
        // Las subcuentas conectadas sin movimientos también salen (con cero), para poder elegirlas.
        $have=array_column($mine,'locationId');
        foreach($own as $c)if(($loc===null||$loc===$c['location_id'])&&!in_array($c['location_id'],$have,true))$mine[]=['locationId'=>$c['location_id'],'locationName'=>$c['name'],'gasto'=>0.0,'recargas'=>0.0,'movimientos'=>0,'sinClasificar'=>0,'categorias'=>[],'dias'=>[],'ultimos'=>[]];
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
        respond(['month'=>$month,'method'=>$got['method'],'pages'=>$got['pages'],'partial'=>$got['partial'],'locations'=>$mine,'otros'=>array_map(fn($e)=>['locationId'=>$e['locationId'],'gasto'=>$e['gasto'],'recargas'=>$e['recargas'],'movimientos'=>$e['movimientos']],$others),
            'totales'=>['gasto'=>round(array_sum(array_column($mine,'gasto')),4),'recargas'=>round(array_sum(array_column($mine,'recargas')),4)],'readAt'=>gmdate('c')]);
    }
}catch(InvalidArgumentException $e){respond(['error'=>$e->getMessage()],422);}
catch(RuntimeException $e){respond(['error'=>$e->getMessage()],409);}
respond(['error'=>'Not found'],404);
