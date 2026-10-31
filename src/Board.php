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
    public const DEFAULTS=['adSpend'=>null,'fixedCosts'=>null,'commissionRate'=>10.0,'teamGoal'=>null,'goals'=>[],'closingWord'=>'cierres','green'=>100.0,'amber'=>70.0];
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
            'adSpend'=>$money($input['adSpend']??null),
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

        [$records,$total,$partial]=$this->gateway->execute($connection,function(GhlClient $client)use($connection,$from,$to):array {
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
                if(count($items)<self::PAGE)return [$records,$total,false];
                if($page===self::PAGES)$partial=true;
            }
            return [$records,$total,$partial];
        },false);

        if($restricted)$records=array_values(array_filter($records,fn(array $row):bool=>$row['assignedTo']===$mine));
        return $this->present($connection,$period,$settings,$records,$restricted?count($records):($total??count($records)),$partial,$restricted,$mine);
    }

    private function present(array $connection,string $period,array $settings,array $records,int $total,bool $partial,bool $restricted,?string $mine):array {
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
        $ads=$settings['adSpend'];
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
                $ads===null?'adSpend':null,
                $fixed===null?'fixedCosts':null,
                $goal===null?'teamGoal':null,
            ])),
            'readAt'=>gmdate('c'),
        ];
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
            'profitability'=>['sales'=>0.0,'ads'=>null,'commissions'=>0.0,'profit'=>null,'fixedCosts'=>null,'net'=>null,
                'marginPerUnit'=>null,'adsPerUnit'=>null,'commissionPerUnit'=>null,'costPerClosing'=>null,'returnPerHundred'=>null,'marketingCost'=>null],
            'team'=>[],'goal'=>['target'=>null,'sold'=>0.0,'progress'=>null,'missing'=>null],
            'missing'=>['adSpend','fixedCosts','teamGoal'],'readAt'=>gmdate('c')];
    }
}
