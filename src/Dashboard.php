<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/PipelineDirectory.php';

/**
 * A reading of the account as it stands: what is waiting to be answered and
 * what is open commercially. Every figure comes from a read Ágora already
 * knows how to make, so the board needs no permission the rest does not.
 *
 * A member restricted to assigned data gets their own figures, and the board
 * says so, instead of showing the account total as if it were theirs.
 */
final class Dashboard {
    private const SAMPLE=100;

    public function __construct(private readonly GhlGateway $gateway=new GhlGateway(),private readonly ?PipelineDirectory $pipelines=null) {}

    public function get(array $connection,array $membership,string $zone='UTC'):array {
        $restricted=($membership['capabilities']['assignedDataOnly']??false)===true;
        $assignee=$restricted?($membership['external_user_id']??null):null;
        if($restricted&&$assignee===null)return self::empty(true);
        $canSeeOpportunities=($membership['capabilities']['viewOpportunities']??false)===true;
        // The published contact list takes no assignee, so a restricted member is
        // shown no contact figure rather than the whole account's as if it were theirs.
        $canSeeContacts=($membership['capabilities']['viewContacts']??false)===true&&$assignee===null;
        $directory=$this->pipelines??new PipelineDirectory();
        $names=$directory->cached($connection);

        // One slot for the whole board: each figure is a small read.
        // Leads: los que entraron hoy (en la zona del dispositivo) y los asignados
        // a quien mira. Quien sólo ve lo suyo, ve sus leads de hoy.
        $self=$membership['self_id']??null;
        $canSeeLeads=($membership['capabilities']['viewContacts']??false)===true;
        $today=(new DateTimeImmutable('today',new DateTimeZone($zone)))->setTimezone(new DateTimeZone('UTC'));
        $todayFilter=[['field'=>'dateAdded','operator'=>'range','value'=>['gt'=>$today->format('Y-m-d\TH:i:s\Z'),'lt'=>$today->modify('+1 day')->format('Y-m-d\TH:i:s\Z')]]];
        if($assignee!==null)$todayFilter[]=['field'=>'assignedTo','operator'=>'eq','value'=>$assignee];
        [$conversations,$unread,$contacts,$opportunities,$names,$leadsToday,$leadsMine]=$this->gateway->execute($connection,
            function(GhlClient $client)use($connection,$assignee,$canSeeOpportunities,$canSeeContacts,$canSeeLeads,$todayFilter,$self,$directory,$names):array {
                $scope=$assignee!==null?['assignedTo'=>$assignee]:[];
                $all=self::total($client->conversations($connection['location_id'],1,$scope));
                $pending=self::total($client->conversations($connection['location_id'],1,$scope+['status'=>'unread']));
                $people=$canSeeContacts?self::people($client,$connection['location_id']):null;
                $deals=$canSeeOpportunities?$client->locationOpportunities($connection['location_id'],null,self::SAMPLE):null;
                if($names===null&&$canSeeOpportunities) {
                    try {$names=$directory->fromClient($client,$connection);}
                    catch(Throwable $e){$names=[];}
                }
                $today=$canSeeLeads?self::leadsToday($client,$connection['location_id'],$todayFilter,$assignee):null;
                $mine=null;
                if($canSeeLeads&&$self!==null)foreach([['field'=>'assignedTo','operator'=>'eq','value'=>$self],['field'=>'assignedTo','operator'=>'eq','value'=>[$self]]] as $variant)
                    if(($mine=self::people($client,$connection['location_id'],[$variant],true))!==null)break;
                return [$all,$pending,$people,$deals,$names??[],$today,$mine];
            },false);

        $open=$this->opportunities($opportunities,$connection,$assignee,$names);
        return [
            'restricted'=>$restricted,
            'conversations'=>['total'=>$conversations,'unread'=>$unread],
            'contacts'=>$contacts,
            'leads'=>['today'=>$leadsToday,'mine'=>$leadsMine,'linked'=>$self!==null],
            'opportunities'=>$open,
            'readAt'=>gmdate('c'),
        ];
    }

    private function opportunities(?array $response,array $connection,?string $assignee,array $names):?array {
        if($response===null)return null;
        $items=$response['opportunities']??null;
        if(!is_array($items)||!array_is_list($items))throw new RuntimeException('Invalid opportunities response');
        $count=0;$value=0.0;$stages=[];
        foreach(array_slice($items,0,self::SAMPLE) as $opportunity) {
            if(!is_array($opportunity))continue;
            if(isset($opportunity['locationId'])&&$opportunity['locationId']!==$connection['location_id'])continue;
            if($assignee!==null&&($opportunity['assignedTo']??null)!==$assignee)continue;
            $amount=$opportunity['monetaryValue']??null;
            $amount=is_int($amount)||is_float($amount)?(float)$amount:(is_string($amount)&&is_numeric($amount)?(float)$amount:0.0);
            $count++;$value+=$amount;
            $pipeline=is_string($opportunity['pipelineId']??null)?$opportunity['pipelineId']:null;
            $stage=is_string($opportunity['pipelineStageId']??null)?$opportunity['pipelineStageId']:null;
            $label=$pipeline!==null&&isset($names[$pipeline]['stages'][$stage??''])
                ? $names[$pipeline]['name'].' · '.$names[$pipeline]['stages'][$stage]
                : 'Sin etapa';
            $stages[$label]??=['name'=>$label,'count'=>0,'value'=>0.0];
            $stages[$label]['count']++;$stages[$label]['value']+=$amount;
        }
        usort($stages,fn(array $a,array $b):int=>[$b['value'],$b['count']]<=>[$a['value'],$a['count']]);
        return ['count'=>$count,'value'=>$value,'stages'=>array_values($stages),'sampled'=>count($items)>=self::SAMPLE];
    }

    private static function total(array $response):int {
        $total=$response['total']??null;
        return is_numeric($total)&&$total>=0?(int)$total:0;
    }
    /**
     * Cuántos contactos tiene la cuenta.
     *
     * La lista publica un campo `count` cuyo significado no documenta, y en una
     * cuenta real devuelve el tamaño de la página, no el total: por eso la
     * cifra salía mal. La búsqueda sí publica un total, así que se pregunta
     * ahí, y sólo se acepta un número que no pueda ser el tamaño de la página.
     * Si no llega ninguno utilizable, no se muestra cifra en vez de una falsa.
     */
    /**
     * Leads de hoy. La forma del filtro de fecha no está publicada, así que se
     * prueba la habitual y su variante; si la cuenta no acepta ninguna, se
     * cuentan en la lista de contactos los creados hoy (hasta 100).
     */
    private static function leadsToday(GhlClient $client,string $location,array $filter,?string $assignee):?int {
        $count=self::people($client,$location,$filter,true);
        if($count!==null)return $count;
        $range=$filter[0]['value'];
        $alt=$filter;$alt[0]['value']=['gte'=>$range['gt'],'lte'=>$range['lt']];
        $count=self::people($client,$location,$alt,true);
        if($count!==null)return $count;
        try {$list=$client->locationContacts($location,null,100);}catch(Throwable $e){return null;}
        $items=$list['contacts']??null;
        if(!is_array($items))return null;
        $from=strtotime($range['gt']);$to=strtotime($range['lt']);$n=0;
        foreach($items as $c){
            if(!is_array($c))continue;
            if($assignee!==null&&($c['assignedTo']??null)!==$assignee)continue;
            $at=is_string($c['dateAdded']??null)?strtotime($c['dateAdded']):false;
            if($at!==false&&$at>=$from&&$at<$to)$n++;
        }
        return $n;
    }

    private static function people(GhlClient $client,string $location,array $filters=[],bool $small=false):?int {
        try {$response=$client->countContacts($location,$filters);}
        catch(Throwable $e){return null;}
        // Con filtros el total puede ser 0 o 1 de verdad; sin ellos, un 1 suele
        // ser el tamaño de la página y no se acepta.
        foreach([$response['total']??null,$response['meta']['total']??null,$small?null:($response['count']??null)] as $candidate)
            if(is_numeric($candidate)&&$candidate>=0&&($small||(int)$candidate!==1))return (int)$candidate;
        return null;
    }
    private static function empty(bool $restricted):array {
        return ['restricted'=>$restricted,'conversations'=>['total'=>0,'unread'=>0],'contacts'=>null,'leads'=>['today'=>null,'mine'=>null,'linked'=>false],'opportunities'=>null,'readAt'=>gmdate('c')];
    }
}
