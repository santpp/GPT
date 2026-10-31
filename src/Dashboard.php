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

    public function get(array $connection,array $membership):array {
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
        [$conversations,$unread,$contacts,$opportunities,$names]=$this->gateway->execute($connection,
            function(GhlClient $client)use($connection,$assignee,$canSeeOpportunities,$canSeeContacts,$directory,$names):array {
                $scope=$assignee!==null?['assignedTo'=>$assignee]:[];
                $all=self::total($client->conversations($connection['location_id'],1,$scope));
                $pending=self::total($client->conversations($connection['location_id'],1,$scope+['status'=>'unread']));
                $people=$canSeeContacts?self::people($client,$connection['location_id']):null;
                $deals=$canSeeOpportunities?$client->locationOpportunities($connection['location_id'],null,self::SAMPLE):null;
                if($names===null&&$canSeeOpportunities) {
                    try {$names=$directory->fromClient($client,$connection);}
                    catch(Throwable $e){$names=[];}
                }
                return [$all,$pending,$people,$deals,$names??[]];
            },false);

        $open=$this->opportunities($opportunities,$connection,$assignee,$names);
        return [
            'restricted'=>$restricted,
            'conversations'=>['total'=>$conversations,'unread'=>$unread],
            'contacts'=>$contacts,
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
    private static function people(GhlClient $client,string $location):?int {
        try {$response=$client->countContacts($location);}
        catch(Throwable $e){return null;}
        foreach([$response['total']??null,$response['meta']['total']??null,$response['count']??null] as $candidate)
            if(is_numeric($candidate)&&$candidate>=0&&(int)$candidate!==1)return (int)$candidate;
        return null;
    }
    private static function empty(bool $restricted):array {
        return ['restricted'=>$restricted,'conversations'=>['total'=>0,'unread'=>0],'contacts'=>null,'opportunities'=>null,'readAt'=>gmdate('c')];
    }
}
