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
    public function __construct(public readonly string $location) {}
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=$path;
            parse_str((string)parse_url($path,PHP_URL_QUERY),$q);
            $page=(int)($q['page']??1);
            $items=$this->pages[$page-1]??[];
            return ['status'=>200,'body'=>json_encode(['opportunities'=>$items,'meta'=>['total'=>$this->total??array_sum(array_map('count',$this->pages))]])];
        }));
    }
    public function reset():void {$this->calls=[];}
    public function query():array {parse_str((string)parse_url($this->calls[0],PHP_URL_QUERY),$q);return $q;}
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
 boardCheck(str_starts_with($api->calls[0],'/opportunities/search')&&($q['status']??'')==='won','el mes se lee con la búsqueda publicada, filtrando por cerradas');
 boardCheck(($q['date']??'')==='10-01-2026'&&($q['endDate']??'')==='10-31-2026','y con el rango exacto del mes, en el formato que pide la cuenta');
 boardCheck($month['sales']['total']===11895.25,'suma sólo lo cerrado de esta cuenta: '.$month['sales']['total']);
 boardCheck($month['sales']['closings']===5,'y cuenta los cierres que realmente llegaron');
 boardCheck($month['profitability']['commissions']===1189.53,'la comisión sale del porcentaje guardado, no de una cifra suelta');
 boardCheck($month['profitability']['profit']===null&&in_array('adSpend',$month['missing'],true),'sin saber lo invertido no se inventa una ganancia: se dice qué falta');

 // ---- las cifras del propietario --------------------------------------------
 Board::save($connectionId,'2026-10',['adSpend'=>'1.385,00','fixedCosts'=>'2056','commissionRate'=>'10','teamGoal'=>'20100','closingWord'=>'estudiantes','green'=>'100','amber'=>'70',
   'goals'=>['seller-1'=>'4800','seller-2'=>'4700','seller-3'=>'5500','intruso!'=>'100']]);
 $saved=Board::settings($connectionId,'2026-10');
 boardCheck($saved['adSpend']===1385.00&&$saved['fixedCosts']===2056.00,'una cifra escrita a la española se guarda como número');
 boardCheck(!isset($saved['goals']['intruso!'])&&count($saved['goals'])===3,'una meta con un identificador imposible se descarta');
 boardCheck($saved['closingWord']==='estudiantes','el nombre de cada cierre lo elige quien administra');

 $api->reset();boardFree($connectionId);
 $month=$board()->month($connection,'2026-10',$owner);
 $p=$month['profitability'];
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

 // ---- cada quien ve lo suyo ---------------------------------------------------
 $api->reset();boardFree($connectionId);
 $own=$board()->month($connection,'2026-10',$mine);
 boardCheck(count($own['team'])===1&&$own['team'][0]['name']==='Rosangélica Pérez','un integrante limitado a sus datos sólo se ve a sí mismo');
 boardCheck($own['sales']['total']===3858.25&&$own['restricted']===true,'y sus cifras son las suyas, no las de la cuenta');

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
 boardCheck(count($api->calls)===2&&$many['sales']['closings']===140,'un mes que no cabe en una página se pide por páginas hasta terminar');
 boardCheck($many['sales']['total']===1200.00&&$many['sales']['partial']===false,'y se suma entero sin decir que está recortado');

 echo 'PASS '.$checks.' board checks (simulated API client, real MariaDB)'.PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
