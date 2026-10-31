<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/Board.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function boardCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}

final class FakeMonthApi {
    public array $calls=[];
    public array $pages=[];
    public ?int $total=null;
    public array $ads=['INTEGRATION'=>['status'=>404,'body'=>'{}'],'AD_MANAGER'=>['status'=>404,'body'=>'{}']];
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=$path;
            parse_str((string)parse_url($path,PHP_URL_QUERY),$q);
            if(str_starts_with($path,'/ad-publishing/facebook/reporting')) {
                $answer=$this->ads[$q['type']??'']??['status'=>404,'body'=>'{}'];
                if($answer['status']>=400)throw new GhlApiException($answer['status']);
                return $answer;
            }
            $page=(int)($q['page']??1);
            $items=$this->pages[$page-1]??[];
            return ['status'=>200,'body'=>json_encode(['opportunities'=>$items,'meta'=>['total'=>$this->total??array_sum(array_map('count',$this->pages))]])];
        }));
    }
    public function reset():void {$this->calls=[];}
    public function query():array {
        foreach($this->calls as $call)if(str_starts_with($call,'/opportunities/search')){parse_str((string)parse_url($call,PHP_URL_QUERY),$q);return $q;}
        return [];
    }
    public function adQuery():array {
        foreach($this->calls as $call)if(str_starts_with($call,'/ad-publishing/')){parse_str((string)parse_url($call,PHP_URL_QUERY),$q);return $q;}
        return [];
    }
}
function boardFree(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0 WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Board test','board-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 foreach([['seller-1','Rosangélica Pérez'],['seller-2','María Carvallo'],['seller-3','Lisbella Moreno']] as [$external,$name])
  query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,permissions,scopes,assigned_data_only) VALUES(?,?,?,?,?,?,0)',
   [$connectionId,$external,$external.'-'.$suffix.'@example.test',$name,'{}','[]']);

 $won=fn(string $assigned,float $value)=>['id'=>uniqid(),'locationId'=>$location,'assignedTo'=>$assigned,'monetaryValue'=>$value,'status'=>'won'];
 $api=new FakeMonthApi($location);
 $api->pages=[[
   $won('seller-1',2000.00),$won('seller-1',1858.25),
   $won('seller-2',4794.00),
   $won('seller-3',2840.00),
   ['id'=>'ajena','locationId'=>'otra-cuenta','assignedTo'=>'seller-1','monetaryValue'=>9999.00],
   ['id'=>'sin-duenno','locationId'=>$location,'monetaryValue'=>403.00],
 ]];
 $owner=['external_user_id'=>null,'capabilities'=>['viewOpportunities'=>true,'assignedDataOnly'=>false]];
 $mine=['external_user_id'=>'seller-1','capabilities'=>['viewOpportunities'=>true,'assignedDataOnly'=>true]];
 $board=fn():Board=>new Board($api->gateway());

 // ---- lo cerrado sale de la cuenta ------------------------------------------
 boardFree($connectionId);
 $month=$board()->month($connection,'2026-10',$owner);
 $q=$api->query();
 boardCheck(($q['status']??'')==='won','el mes se lee con la búsqueda publicada, filtrando por cerradas');
 boardCheck(($q['date']??'')==='10-01-2026'&&($q['endDate']??'')==='10-31-2026','y con el rango exacto del mes, en el formato que pide la cuenta');
 boardCheck($month['sales']['total']===11895.25,'suma sólo lo cerrado de esta cuenta: '.$month['sales']['total']);
 boardCheck($month['sales']['closings']===5,'y cuenta los cierres que realmente llegaron');
 boardCheck($month['profitability']['commissions']===1189.53,'la comisión sale del porcentaje guardado, no de una cifra suelta');
 boardCheck($month['profitability']['profit']===null&&in_array('ads',$month['missing'],true),'sin saber lo invertido no se inventa una ganancia: se dice qué falta');
 boardCheck($month['profitability']['adsReason']==='not_connected','y si la cuenta no tiene publicidad conectada, se dice exactamente eso');

 // ---- las cifras del propietario --------------------------------------------
 Board::save($connectionId,'2026-10',['otherAds'=>'85,00','fixedCosts'=>'2056','commissionRate'=>'10','teamGoal'=>'20100','closingWord'=>'estudiantes','green'=>'100','amber'=>'70',
   'goals'=>['seller-1'=>'4800','seller-2'=>'4700','seller-3'=>'5500','intruso!'=>'100']]);
 $saved=Board::settings($connectionId,'2026-10');
 boardCheck($saved['otherAds']===85.00&&$saved['fixedCosts']===2056.00,'una cifra escrita a la española se guarda como número');
 boardCheck(!isset($saved['goals']['intruso!'])&&count($saved['goals'])===3,'una meta con un identificador imposible se descarta');
 boardCheck($saved['closingWord']==='estudiantes','el nombre de cada cierre lo elige quien administra');

 $api->reset();boardFree($connectionId);
 // La publicidad de Meta llega de la cuenta publicitaria conectada.
 $api->ads['INTEGRATION']=['status'=>200,'body'=>json_encode(['data'=>[['date_start'=>'2026-10-01','spend'=>'1300.00','conversions'=>'40']]])];
 $month=$board()->month($connection,'2026-10',$owner);
 $p=$month['profitability'];
 $aq=$api->adQuery();
 boardCheck(($aq['startDate']??'')==='2026-10-01'&&($aq['endDate']??'')==='2026-10-31'&&($aq['groupBy']??'')==='month'&&($aq['type']??'')==='INTEGRATION','lo invertido se pide a la cuenta publicitaria conectada, para el mes exacto');
 boardCheck(str_contains($aq['fields']??'','spend'),'pidiendo el gasto entre los campos publicados');
 boardCheck($p['adsFromAccount']===1300.00&&$p['adsOther']===85.00&&$p['ads']===1385.00&&$p['adsSource']==='INTEGRATION','lo de Meta y lo indicado aparte se suman, y se sabe de dónde viene cada parte');
 boardCheck($p['profit']===9320.72&&$p['net']===7264.72,'ganancia y ganancia real salen de ventas, publicidad, comisiones y costos fijos');
 boardCheck($p['costPerClosing']===277.00&&$p['returnPerHundred']===858.86,'el costo por cierre y el retorno del gasto se calculan sobre lo real');
 boardCheck($p['marginPerUnit']===0.78,'y se dice cuánto queda de cada unidad vendida');
 boardCheck($month['goal']['progress']===59.2&&$month['goal']['missing']===8204.75,'el avance del equipo se mide contra la meta del mes');

 // ---- el semáforo -------------------------------------------------------------
 $team=array_column($month['team'],null,'name');
 boardCheck($team['María Carvallo']['state']==='lograda'&&$team['María Carvallo']['difference']===94.00,'quien pasa su meta aparece como lograda, con su diferencia');
 boardCheck($team['Rosangélica Pérez']['state']==='cerca','quien está entre los dos umbrales aparece como cerca de la meta');
 boardCheck($team['Lisbella Moreno']['state']==='apoyo','y quien está por debajo, como que necesita apoyo');
 boardCheck(isset($team['Sin asignar'])&&$team['Sin asignar']['sold']===403.00,'lo cerrado sin responsable no desaparece del resumen');
 boardCheck($team['María Carvallo']['commission']===479.40,'cada quien ve la comisión que le corresponde');

 Board::save($connectionId,'2026-10',['adSpend'=>'1385','commissionRate'=>'10','teamGoal'=>'20100','green'=>'70','amber'=>'100','goals'=>['seller-1'=>'4800']]);
 boardCheck(Board::settings($connectionId,'2026-10')['amber']<=Board::settings($connectionId,'2026-10')['green'],'el umbral ámbar nunca puede quedar por encima del verde');

 // ---- formas de respuesta que no están publicadas --------------------------------
 boardCheck(Board::spendIn(['spend'=>'500.5'])===500.5,'un total suelto se lee como total');
 boardCheck(Board::spendIn(['data'=>[['spend'=>100],['spend'=>'50.25']]])===150.25,'filas sueltas se suman');
 boardCheck(Board::spendIn(['summary'=>['spend'=>150],'data'=>[['spend'=>100],['spend'=>50]]])===150.0,'un total junto a sus filas no se cuenta dos veces');
 boardCheck(Board::spendIn(['data'=>[['clicks'=>3]]])===null&&Board::spendIn('texto')===null&&Board::spendIn(['spend'=>'mucho'])===null,'sin un gasto numérico no hay cifra, en lugar de un cero');

 $api->reset();boardFree($connectionId);
 $api->ads['INTEGRATION']=['status'=>403,'body'=>'{}'];
 $denied=$board()->month($connection,'2026-10',$owner);
 boardCheck($denied['profitability']['adsReason']==='permission'&&$denied['profitability']['adsFromAccount']===null,'sin el permiso de anuncios en la clave se dice eso, no un error');
 boardCheck($denied['sales']['total']===11895.25,'y lo vendido se sigue leyendo igual');
 boardCheck(count(array_filter($api->calls,fn($c)=>str_starts_with($c,'/ad-publishing/')))===1,'sin permiso no se insiste con la otra fuente');

 $api->reset();boardFree($connectionId);
 $api->ads['INTEGRATION']=['status'=>200,'body'=>json_encode(['data'=>[]])];
 $api->ads['AD_MANAGER']=['status'=>200,'body'=>json_encode(['spend'=>420])];
 $manager=$board()->month($connection,'2026-10',$owner);
 boardCheck($manager['profitability']['adsFromAccount']===420.0&&$manager['profitability']['adsSource']==='AD_MANAGER','si la cuenta conectada no informa gasto, se prueba con las campañas del gestor de anuncios');
 $api->ads['INTEGRATION']=['status'=>200,'body'=>json_encode(['data'=>[['spend'=>'1300.00']]])];

 // ---- cada quien ve lo suyo ---------------------------------------------------
 $api->reset();boardFree($connectionId);
 $own=$board()->month($connection,'2026-10',$mine);
 boardCheck(count($own['team'])===1&&$own['team'][0]['name']==='Rosangélica Pérez','un integrante limitado a sus datos sólo se ve a sí mismo');
 boardCheck($own['sales']['total']===3858.25&&$own['restricted']===true,'y sus cifras son las suyas, no las de la cuenta');
 boardCheck(count(array_filter($api->calls,fn($c)=>str_starts_with($c,'/ad-publishing/')))===0,'y lo invertido por la empresa ni siquiera se pide para esa persona');

 // ---- meses y paginación -------------------------------------------------------
 $api->reset();boardFree($connectionId);
 $board()->month($connection,'2026-02',$owner);
 $q=$api->query();
 boardCheck(($q['date']??'')==='02-01-2026'&&($q['endDate']??'')==='02-28-2026','otro mes se pide con su último día real');
 boardCheck(Board::period('no-es-un-mes')===gmdate('Y-m')&&Board::period('2026-13')===gmdate('Y-m'),'un mes imposible se trata como el mes corriente');

 $api->reset();boardFree($connectionId);
 $api->pages=[array_fill(0,100,$won('seller-1',10.00)),array_fill(0,40,$won('seller-2',5.00))];
 $api->total=140;
 $many=$board()->month($connection,'2026-10',$owner);
 $pages=count(array_filter($api->calls,fn($c)=>str_starts_with($c,'/opportunities/search')));
 boardCheck($pages===2&&$many['sales']['closings']===140,'un mes que no cabe en una página se pide por páginas hasta terminar');
 boardCheck($many['sales']['total']===1200.00&&$many['sales']['partial']===false,'y se suma entero sin decir que está recortado');

 echo 'PASS '.$checks.' board checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
