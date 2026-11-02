<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/Board.php';

/**
 * Las ventas ganadas de un mes, con su monto y su responsable, y la alerta de las que están ganadas
 * pero sin monto (vacío o cero). La lista se lee de la cuenta con una caché corta que se borra al editar
 * una oportunidad, de modo que la alerta no cuesta una consulta por pantalla.
 */
final class WonSales {
    private const PAGE=100;
    private const PAGES=5;
    private const TTL=120;
    private const LIST_LIMIT=300;
    private const ALERT_LIMIT=30;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    /** @return array{records:list<array>,partial:bool,fetchedAt:string} */
    public function month(array $connection,string $period):array {
        $id=(int)$connection['id'];$key='won:'.$period;
        $row=query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS at FROM directory_cache WHERE connection_id=? AND cache_key=?',[$id,$key])->fetch();
        if($row&&time()-(int)$row['at']<self::TTL){$cached=json_decode((string)$row['payload'],true);if(is_array($cached)&&isset($cached['records']))return $cached;}
        $start=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$period.'-01 00:00:00',new DateTimeZone('UTC'));
        $from=$start->format('m-d-Y');$to=$start->modify('last day of this month')->format('m-d-Y');
        [$records,$partial]=$this->gateway->execute($connection,function(GhlClient $client)use($connection,$from,$to):array {
            $out=[];$partial=false;
            for($page=1;$page<=self::PAGES;$page++) {
                $data=$client->wonOpportunities($connection['location_id'],$from,$to,$page,self::PAGE);
                $items=$data['opportunities']??null;
                if(!is_array($items)||!array_is_list($items))throw new RuntimeException('Invalid opportunities response');
                foreach($items as $o) {
                    if(!is_array($o)||!is_string($o['id']??null))continue;
                    if(isset($o['locationId'])&&$o['locationId']!==$connection['location_id'])continue;
                    $value=$o['monetaryValue']??null;
                    $when=$o['lastStatusChangeAt']??($o['updatedAt']??($o['createdAt']??null));
                    $out[$o['id']]=[
                        'id'=>$o['id'],
                        'name'=>is_string($o['name']??null)?mb_substr($o['name'],0,190):null,
                        'contactName'=>is_string($o['contact']['name']??null)?mb_substr($o['contact']['name'],0,120):null,
                        'assignedTo'=>is_string($o['assignedTo']??null)&&$o['assignedTo']!==''?$o['assignedTo']:null,
                        'value'=>is_numeric($value)?(float)$value:0.0,
                        'wonAt'=>is_string($when)&&strtotime($when)!==false?gmdate('c',strtotime($when)):null,
                    ];
                }
                if(count($items)<self::PAGE)return [array_values($out),false];
                if($page===self::PAGES)$partial=true;
            }
            return [array_values($out),$partial];
        },false);
        // Lo ganado desde Ágora que la cuenta no devolvió para el mes, sin repetir ninguna.
        try {
            $seen=array_flip(array_column($records,'id'));
            $next=$start->modify('+1 month')->format('Y-m-d 00:00:00');
            foreach(query('SELECT opportunity_id,assigned_to,value,won_at FROM won_sales WHERE connection_id=? AND won_at>=? AND won_at<?',[$id,$period.'-01 00:00:00',$next])->fetchAll() as $r)
                if(!isset($seen[$r['opportunity_id']]))$records[]=['id'=>$r['opportunity_id'],'name'=>null,'contactName'=>null,'assignedTo'=>$r['assigned_to']?:null,'value'=>(float)$r['value'],'wonAt'=>gmdate('c',strtotime($r['won_at'].' UTC'))];
        } catch(PDOException $e) {}
        $result=['records'=>$records,'partial'=>$partial,'fetchedAt'=>gmdate('c')];
        query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP',[$id,$key,json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return $result;
    }

    /** Quién ve todo: los administradores de la subcuenta y el propietario; el resto, sólo lo suyo. */
    public static function seesAll(array $membership):bool {return ($membership['can_assign']??false)===true;}

    private static function names(int $connection):array {
        $out=[];
        foreach(query('SELECT external_user_id,display_name FROM external_users WHERE connection_id=? AND active=TRUE',[$connection])->fetchAll() as $r)$out[$r['external_user_id']]=$r['display_name'];
        return $out;
    }

    private static function age(?string $when,int $now):?int {
        $t=$when?strtotime($when):false;
        return $t===false?null:max(0,intdiv($now-$t,86400));
    }

    /** La lista del mes que corresponde a quien mira, con totales y (para quien ve todo) el resumen por vendedor. */
    public function listing(array $connection,string $period,array $membership,?string $seller=null,?int $now=null):array {
        $now??=time();
        $data=$this->month($connection,$period);
        $all=self::seesAll($membership);$self=$membership['self_id']??null;
        $names=self::names((int)$connection['id']);
        $rows=[];
        foreach($data['records'] as $r) {
            if(!$all&&($self===null||$r['assignedTo']!==$self))continue;
            $rows[]=$r+['assignedName'=>$r['assignedTo']!==null?($names[$r['assignedTo']]??null):null,'missing'=>$r['value']<=0,'days'=>self::age($r['wonAt'],$now)];
        }
        $by=[];
        foreach($rows as $r) {
            $k=$r['assignedTo']??'';
            $by[$k]??=['id'=>$r['assignedTo'],'name'=>$r['assignedName']??($r['assignedTo']===null?'Sin responsable':'Usuario'),'count'=>0,'sum'=>0.0,'missing'=>0];
            $by[$k]['count']++;$by[$k]['sum']+=$r['value'];if($r['missing'])$by[$k]['missing']++;
        }
        foreach($by as &$b)$b['sum']=round($b['sum'],2);unset($b);
        if($all&&$seller!==null&&$seller!=='')$rows=array_values(array_filter($rows,fn($r)=>($seller==='none'?$r['assignedTo']===null:$r['assignedTo']===$seller)));
        usort($rows,fn($a,$b)=>[!$a['missing'],-(strtotime((string)$a['wonAt'])?:0)]<=>[!$b['missing'],-(strtotime((string)$b['wonAt'])?:0)]);
        return [
            'period'=>$period,
            'items'=>array_slice($rows,0,self::LIST_LIMIT),
            'totals'=>['count'=>count($rows),'sum'=>round(array_sum(array_column($rows,'value')),2),'missing'=>count(array_filter($rows,fn($r)=>$r['missing']))],
            'bySeller'=>$all?array_values($by):[],
            'canSeeAll'=>$all,
            'partial'=>$data['partial'],
        ];
    }

    /**
     * La alerta de las ventas ganadas este mes sin monto. Para el vendedor, las suyas; para quien administra, las suyas
     * y las que no tienen responsable, más el resumen del equipo. `escalate` dice si alguna lleva ya los días fijados.
     */
    public function alert(array $connection,array $membership,?int $now=null):array {
        $now??=time();$period=gmdate('Y-m',$now);
        $data=$this->month($connection,$period);
        $all=self::seesAll($membership);$self=$membership['self_id']??null;
        $names=self::names((int)$connection['id']);
        $days=max(0,(int)($connection['won_alert_days']??7));
        $mine=[];$team=[];
        foreach($data['records'] as $r) {
            if($r['value']>0)continue;
            $row=$r+['assignedName'=>$r['assignedTo']!==null?($names[$r['assignedTo']]??null):null,'missing'=>true,'days'=>self::age($r['wonAt'],$now)];
            $own=$self!==null&&$r['assignedTo']===$self;
            if($own||($all&&$r['assignedTo']===null))$mine[]=$row;
            if($all)$team[]=$row;
        }
        usort($mine,fn($a,$b)=>($b['days']??0)<=>($a['days']??0));
        $oldest=$mine?max(array_map(fn($r)=>$r['days']??0,$mine)):0;
        $bySeller=[];
        foreach($team as $r){$k=$r['assignedTo']??'';$bySeller[$k]??=['id'=>$r['assignedTo'],'name'=>$r['assignedName']??($r['assignedTo']===null?'Sin responsable':'Usuario'),'missing'=>0];$bySeller[$k]['missing']++;}
        return [
            'month'=>$period,
            'count'=>count($mine),
            'items'=>array_slice($mine,0,self::ALERT_LIMIT),
            'oldestDays'=>$oldest,
            'days'=>$days,
            'escalate'=>$days>0&&$oldest>=$days,
            'canEdit'=>($membership['capabilities']['editOpportunities']??false)===true,
            'team'=>$all?['missing'=>count($team),'bySeller'=>array_values($bySeller)]:null,
        ];
    }
}
