<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

/**
 * La rentabilidad del mes y el desempeño del equipo.
 *
 * Lo cerrado sale de la cuenta: la búsqueda publicada de oportunidades filtra
 * por estado y por fechas y devuelve un total real. Lo que la cuenta no sabe
 * —lo invertido en publicidad, los costos fijos, el porcentaje de comisión y
 * las metas— lo escribe el propietario y se guarda por subcuenta y por mes.
 *
 * Ninguna cifra se inventa: si falta un dato del propietario, el bloque lo dice
 * en lugar de enseñar un cero que parezca un resultado.
 */
final class Board {
    public const DEFAULTS=['otherAds'=>null,'fixedCosts'=>null,'commissionRate'=>10.0,'teamGoal'=>null,'goals'=>[],'closingWord'=>'cierres','green'=>100.0,'amber'=>70.0];
    private const PAGE=100;
    private const PAGES=10;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    /** El mes pedido, o el corriente, siempre como AAAA-MM. */
    public static function period(mixed $value):string {
        return is_string($value)&&preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$value)?$value:gmdate('Y-m');
    }

    public static function settings(int $connectionId,string $period):array {
        $row=query('SELECT payload FROM board_settings WHERE connection_id=? AND period=?',[$connectionId,$period])->fetchColumn();
        $stored=is_string($row)?json_decode($row,true):null;
        return self::clean(is_array($stored)?$stored:[]);
    }

    /** Guarda sólo lo que es una cifra razonable; lo demás se descarta. */
    public static function save(int $connectionId,string $period,array $input):array {
        $clean=self::clean($input);
        query('INSERT INTO board_settings(connection_id,period,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)',
            [$connectionId,$period,json_encode($clean,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $clean;
    }

    private static function clean(array $input):array {
        $money=function(mixed $value):?float {
            if($value===null||$value===''||is_bool($value))return null;
            if(is_string($value))$value=str_replace([' ','.'],['',''],trim($value));
            if(is_string($value))$value=str_replace(',','.',$value);
            return is_numeric($value)&&(float)$value>=0&&(float)$value<=99999999?round((float)$value,2):null;
        };
        $rate=function(mixed $value,float $fallback):float {
            $value=is_string($value)?str_replace(',','.',trim($value)):$value;
            return is_numeric($value)&&(float)$value>=0&&(float)$value<=100?round((float)$value,2):$fallback;
        };
        $goals=[];
        foreach(is_array($input['goals']??null)?$input['goals']:[] as $id=>$goal) {
            if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id))continue;
            $value=$money(is_array($goal)?($goal['goal']??null):$goal);
            if($value!==null)$goals[$id]=$value;
            if(count($goals)>=200)break;
        }
        $word=$input['closingWord']??self::DEFAULTS['closingWord'];
        $word=is_string($word)&&trim($word)!==''?mb_substr(preg_replace('/[^\p{L}\p{N} ]/u','',trim($word)),0,24):self::DEFAULTS['closingWord'];
        $green=$rate($input['green']??null,self::DEFAULTS['green']);
        $amber=$rate($input['amber']??null,self::DEFAULTS['amber']);
        return [
            // Lo que no pasa por la cuenta publicitaria conectada (otras redes,
            // impresos). Se suma a lo que informa la cuenta, nunca lo sustituye.
            'otherAds'=>$money($input['otherAds']??null),
            'fixedCosts'=>$money($input['fixedCosts']??null),
            'commissionRate'=>$rate($input['commissionRate']??null,self::DEFAULTS['commissionRate']),
            'teamGoal'=>$money($input['teamGoal']??null),
            'goals'=>$goals,
            'closingWord'=>$word,
            // El umbral ámbar nunca puede quedar por encima del verde.
            'green'=>max($green,$amber),
            'amber'=>min($green,$amber),
        ];
    }

    /** @return array{0:string,1:string} el mes en el formato que publica la cuenta */
    private static function range(string $period):array {
        $start=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$period.'-01 00:00:00',new DateTimeZone('UTC'));
        $end=$start->modify('last day of this month');
        return [$start->format('m-d-Y'),$end->format('m-d-Y')];
    }

    /**
     * Todo lo cerrado del mes, por asesora. Se pagina hasta un tope para que un
     * mes enorme no cueste la cuota entera, y se dice cuándo se llegó a ese tope.
     */
    public function month(array $connection,string $period,array $membership):array {
        $id=(int)$connection['id'];
        $settings=self::settings($id,$period);
        [$from,$to]=self::range($period);
        $restricted=($membership['capabilities']['assignedDataOnly']??false)===true;
        $mine=$membership['external_user_id']??null;
        if($restricted&&$mine===null)return self::empty($period,$settings,true);

        [$records,$total,$partial,$ads]=$this->gateway->execute($connection,function(GhlClient $client)use($connection,$from,$to,$period,$restricted):array {
            // Lo invertido es de la empresa: quien sólo ve lo suyo no lo recibe.
            $ads=$restricted?['amount'=>null,'source'=>null,'reason'=>'restricted']:self::adSpend($client,$connection['location_id'],$period);
            $records=[];$total=null;$partial=false;
            for($page=1;$page<=self::PAGES;$page++) {
                $data=$client->wonOpportunities($connection['location_id'],$from,$to,$page,self::PAGE);
                $items=$data['opportunities']??null;
                if(!is_array($items)||!array_is_list($items))throw new RuntimeException('Invalid opportunities response');
                $total??=is_numeric($data['meta']['total']??null)?(int)$data['meta']['total']:null;
                foreach($items as $opportunity) {
                    if(!is_array($opportunity))continue;
                    if(isset($opportunity['locationId'])&&$opportunity['locationId']!==$connection['location_id'])continue;
                    $value=$opportunity['monetaryValue']??null;
                    $records[]=[
                        'assignedTo'=>is_string($opportunity['assignedTo']??null)?$opportunity['assignedTo']:null,
                        'value'=>is_int($value)||is_float($value)?(float)$value:(is_string($value)&&is_numeric($value)?(float)$value:0.0),
                    ];
                }
                if(count($items)<self::PAGE)return [$records,$total,false,$ads];
                if($page===self::PAGES)$partial=true;
            }
            return [$records,$total,$partial,$ads];
        },false);

        if($restricted)$records=array_values(array_filter($records,fn(array $row):bool=>$row['assignedTo']===$mine));
        return $this->present($connection,$period,$settings,$records,$restricted?count($records):($total??count($records)),$partial,$restricted,$mine,$ads);
    }

    private function present(array $connection,string $period,array $settings,array $records,int $total,bool $partial,bool $restricted,?string $mine,array $ads):array {
        $sales=0.0;$byMember=[];
        foreach($records as $row) {
            $sales+=$row['value'];
            $key=$row['assignedTo']??'';
            $byMember[$key]??=['sold'=>0.0,'closings'=>0];
            $byMember[$key]['sold']+=$row['value'];
            $byMember[$key]['closings']++;
        }
        $closings=count($records);
        $rate=(float)$settings['commissionRate'];
        $commissions=round($sales*$rate/100,2);
        // Lo que dice la cuenta publicitaria, más lo que se indicó a mano aparte.
        $account=$ads['amount'];
        $other=$settings['otherAds'];
        $adSource=$ads;
        $ads=$account===null&&$other===null?null:round(($account??0)+($other??0),2);
        $fixed=$settings['fixedCosts'];
        $profit=$ads===null?null:round($sales-$ads-$commissions,2);
        $net=$profit===null||$fixed===null?null:round($profit-$fixed,2);

        $team=$this->team($connection,$settings,$byMember,$restricted,$mine);
        $goal=$settings['teamGoal'];
        return [
            'period'=>$period,
            'restricted'=>$restricted,
            'settings'=>$settings,
            'sales'=>['total'=>round($sales,2),'closings'=>$closings,'counted'=>$total,'partial'=>$partial],
            'profitability'=>[
                'sales'=>round($sales,2),
                'ads'=>$ads,
                'adsFromAccount'=>$account,
                'adsOther'=>$other,
                'adsSource'=>$adSource['source'],
                'adsReason'=>$adSource['reason'],
                'commissions'=>$commissions,
                'profit'=>$profit,
                'fixedCosts'=>$fixed,
                'net'=>$net,
                // Lo que queda de cada unidad vendida, que es la lectura rápida.
                'marginPerUnit'=>$profit!==null&&$sales>0?round($profit/$sales,2):null,
                'adsPerUnit'=>$ads!==null&&$sales>0?round($ads/$sales,2):null,
                'commissionPerUnit'=>$sales>0?round($commissions/$sales,2):null,
                'costPerClosing'=>$ads!==null&&$closings>0?round($ads/$closings,2):null,
                'returnPerHundred'=>$ads!==null&&$ads>0?round($sales/$ads*100,2):null,
                'marketingCost'=>$ads===null&&$fixed===null?null:round(($ads??0)+($fixed??0),2),
            ],
            'team'=>$team,
            'goal'=>[
                'target'=>$goal,
                'sold'=>round($restricted?array_sum(array_column($team,'sold')):$sales,2),
                'progress'=>$goal!==null&&$goal>0?round(($restricted?array_sum(array_column($team,'sold')):$sales)/$goal*100,1):null,
                'missing'=>$goal!==null?round(max(0,$goal-($restricted?array_sum(array_column($team,'sold')):$sales)),2):null,
            ],
            'missing'=>array_values(array_filter([
                $ads===null?'ads':null,
                $fixed===null?'fixedCosts':null,
                $goal===null?'teamGoal':null,
            ])),
            'readAt'=>gmdate('c'),
        ];
    }

    /**
     * Lo gastado en anuncios durante el mes, según la cuenta.
     *
     * La respuesta de este informe no tiene esquema publicado, así que se lee
     * sin suponer su forma: se buscan los valores numéricos de `spend` y se
     * suman los del nivel más alto en que aparecen, de modo que un total y sus
     * filas nunca se cuenten dos veces. Si no aparece ninguno, no hay cifra.
     *
     * @return array{amount:?float,source:?string,reason:?string}
     */
    public static function adSpend(GhlClient $client,string $location,string $period):array {
        $start=$period.'-01';
        $end=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->modify('last day of this month')->format('Y-m-d');
        $reason='not_connected';
        foreach(['INTEGRATION','AD_MANAGER'] as $type) {
            try {$response=$client->adReporting($location,$start,$end,$type);}
            catch(GhlApiException $e) {
                // Sin el permiso de anuncios en la clave, la otra fuente tampoco responderá.
                if(in_array($e->status,[401,403],true))return ['amount'=>null,'source'=>null,'reason'=>'permission'];
                continue;
            }
            catch(Throwable $e){$reason='unreadable';continue;}
            $amount=self::spendIn($response);
            if($amount!==null)return ['amount'=>round($amount,2),'source'=>$type,'reason'=>null];
            $reason='no_spend';
        }
        return ['amount'=>null,'source'=>null,'reason'=>$reason];
    }

    /** Suma los `spend` del nivel más alto en que aparecen. */
    public static function spendIn(mixed $node):?float {
        $found=[];
        $walk=function(mixed $value,int $depth)use(&$walk,&$found):void {
            if($depth>6||!is_array($value))return;
            foreach($value as $key=>$child) {
                if(is_string($key)&&strtolower($key)==='spend') {
                    $number=is_int($child)||is_float($child)?(float)$child:(is_string($child)&&is_numeric(trim($child))?(float)trim($child):null);
                    if($number!==null&&$number>=0&&$number<1e10)$found[$depth][]=$number;
                    continue;
                }
                $walk($child,$depth+1);
            }
        };
        $walk($node,0);
        if($found===[])return null;
        return array_sum($found[min(array_keys($found))]);
    }

    /** Una fila por integrante activo del equipo importado, más su estado. */
    private function team(array $connection,array $settings,array $byMember,bool $restricted,?string $mine):array {
        $members=query('SELECT external_user_id AS id,display_name AS name FROM external_users WHERE connection_id=? AND active=TRUE ORDER BY display_name',[$connection['id']])->fetchAll();
        $rows=[];
        foreach($members as $member) {
            if($restricted&&$member['id']!==$mine)continue;
            $sold=$byMember[$member['id']]['sold']??0.0;
            $closings=$byMember[$member['id']]['closings']??0;
            $goal=$settings['goals'][$member['id']]??null;
            $rows[]=$this->row($member['id'],$member['name'],$sold,$closings,$goal,$settings);
            unset($byMember[$member['id']]);
        }
        // Lo cerrado sin responsable no desaparece: se muestra aparte.
        if(!$restricted&&isset($byMember['']))
            $rows[]=$this->row(null,'Sin asignar',$byMember['']['sold'],$byMember['']['closings'],null,$settings);
        usort($rows,fn(array $a,array $b):int=>$b['sold']<=>$a['sold']);
        return $rows;
    }

    private function row(?string $id,string $name,float $sold,int $closings,?float $goal,array $settings):array {
        $progress=$goal!==null&&$goal>0?round($sold/$goal*100,1):null;
        return [
            'id'=>$id,'name'=>$name,'sold'=>round($sold,2),'closings'=>$closings,'goal'=>$goal,
            'difference'=>$goal!==null?round($sold-$goal,2):null,
            'progress'=>$progress,
            'state'=>$progress===null?'sin-meta':($progress>=$settings['green']?'lograda':($progress>=$settings['amber']?'cerca':'apoyo')),
            'commission'=>round($sold*(float)$settings['commissionRate']/100,2),
        ];
    }

    private static function empty(string $period,array $settings,bool $restricted):array {
        return ['period'=>$period,'restricted'=>$restricted,'settings'=>$settings,
            'sales'=>['total'=>0.0,'closings'=>0,'counted'=>0,'partial'=>false],
            'profitability'=>['sales'=>0.0,'ads'=>null,'adsFromAccount'=>null,'adsOther'=>null,'adsSource'=>null,'adsReason'=>'restricted','commissions'=>0.0,'profit'=>null,'fixedCosts'=>null,'net'=>null,
                'marginPerUnit'=>null,'adsPerUnit'=>null,'commissionPerUnit'=>null,'costPerClosing'=>null,'returnPerHundred'=>null,'marketingCost'=>null],
            'team'=>[],'goal'=>['target'=>null,'sold'=>0.0,'progress'=>null,'missing'=>null],
            'missing'=>['ads','fixedCosts','teamGoal'],'readAt'=>gmdate('c')];
    }
}
