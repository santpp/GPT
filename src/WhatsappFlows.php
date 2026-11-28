<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
/**
 * 1.10.36 · Plantillas de WhatsApp por flujos de trabajo.
 * La cuenta no entrega por su API las plantillas aprobadas por Meta; cada plantilla se envía desde un flujo de la cuenta
 * («Enviar WhatsApp» con esa plantilla). El propietario elige en Conexiones qué flujos son plantillas; quien puede escribir en la
 * Bandeja mete al contacto en el flujo y la cuenta envía. Ágora anota cada pedido y luego mira si el mensaje apareció.
 */
final class FlowProblem extends RuntimeException {
    public function __construct(public readonly string $reason,public readonly int $httpStatus,string $message,public readonly int $retryAfter=0){parent::__construct($message);}
}
final class WhatsappFlows {
    public const MAX=40;           // flujos elegidos por subcuenta
    public const REPEAT=600;       // misma plantilla al mismo contacto: no antes de 10 minutos
    public const PER_MINUTE=20;    // pedidos por subcuenta y minuto
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()){}

    /** Los flujos de la cuenta (para que el propietario elija), filtrados por nombre. */
    public function account(array $connection,string $search=''):array {
        try{$data=$this->gateway->execute($connection,fn(GhlClient $c)=>$c->workflows((string)$connection['location_id']),false,true);}
        catch(GhlCooldown $e){throw new FlowProblem('cooldown',429,'La cuenta pidió esperar un momento. Vuelve a intentarlo en unos segundos.',$e->retryAfter);}
        catch(GhlApiException $e){throw new FlowProblem('upstream_'.$e->status,424,in_array($e->status,[401,403],true)?'La conexión de esta subcuenta no tiene permiso para leer los flujos de trabajo. Agrega el permiso de flujos de trabajo (lectura) a su integración.':'La cuenta respondió con un error al pedir los flujos (código '.$e->status.').');}
        $items=[];
        foreach((is_array($data['workflows']??null)?$data['workflows']:[]) as $w){
            if(!is_array($w))continue;$id=(string)($w['id']??'');$name=trim((string)($w['name']??''));
            if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$id)||$name==='')continue;
            $items[]=['id'=>$id,'nombre'=>mb_substr($name,0,200),'estado'=>strtolower((string)($w['status']??''))==='published'?'publicado':'borrador'];
        }
        $search=mb_strtolower(trim($search));
        if($search!=='')$items=array_values(array_filter($items,fn($w)=>str_contains(self::fold($w['nombre']),self::fold($search))));
        usort($items,fn($a,$b)=>[$a['estado']!=='publicado',self::fold($a['nombre'])]<=>[$b['estado']!=='publicado',self::fold($b['nombre'])]);
        return $items;
    }
    private static function fold(string $s):string {return strtr(mb_strtolower($s),['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);}

    /** Los flujos elegidos de una subcuenta, en su orden. */
    public static function chosen(int $connection):array {
        return array_map(fn($r)=>['id'=>$r['workflow_id'],'nombre'=>$r['label'],'nombreCuenta'=>$r['account_name'],'texto'=>$r['preview'],'estado'=>$r['account_status']],
            query('SELECT workflow_id,label,account_name,preview,account_status FROM whatsapp_flows WHERE connection_id=? ORDER BY position,label',[$connection])->fetchAll());
    }

    /** Guarda la elección del propietario: cada flujo se comprueba contra la lista de la cuenta (debe existir y estar publicado). */
    public function save(array $connection,array $input):array {
        if(count($input)>self::MAX)throw new FlowProblem('too_many',422,'Se pueden elegir hasta '.self::MAX.' flujos por subcuenta.');
        $account=[];foreach($this->account($connection) as $w)$account[$w['id']]=$w;
        $rows=[];$seen=[];
        foreach(array_values($input) as $i=>$x){
            if(!is_array($x))throw new FlowProblem('invalid',422,'La lista de flujos no es válida.');
            $id=(string)($x['id']??'');
            if(!isset($account[$id]))throw new FlowProblem('unknown',422,'Uno de los flujos elegidos ya no está en la cuenta. Vuelve a cargar la lista.');
            if($account[$id]['estado']!=='publicado')throw new FlowProblem('draft',422,'«'.$account[$id]['nombre'].'» es un borrador: publícalo en la cuenta antes de elegirlo.');
            if(isset($seen[$id]))continue;$seen[$id]=1;
            $label=trim(preg_replace('/\s+/u',' ',strip_tags((string)($x['nombre']??''))));
            if($label==='')$label=$account[$id]['nombre'];
            if(mb_strlen($label)>120)throw new FlowProblem('label',422,'El nombre visible de una plantilla puede tener hasta 120 caracteres.');
            $text=trim(strip_tags(str_replace("\r",'',(string)($x['texto']??''))));
            if(mb_strlen($text)>1024)throw new FlowProblem('preview',422,'El texto de una plantilla puede tener hasta 1.024 caracteres.');
            $rows[]=[$id,$label,$account[$id]['nombre'],$text===''?null:$text,$i];
        }
        $cid=(int)$connection['id'];
        db()->beginTransaction();
        try{
            query('DELETE FROM whatsapp_flows WHERE connection_id=?',[$cid]);
            foreach($rows as $r)query("INSERT INTO whatsapp_flows(connection_id,workflow_id,label,account_name,preview,position,account_status) VALUES(?,?,?,?,?,?,'published')",[$cid,...$r]);
            db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        return self::chosen($cid);
    }

    /** Lo que la Bandeja ofrece: con la misma forma que una plantilla, para reutilizar el panel de plantillas. */
    public static function forInbox(int $connection):array {
        return array_map(fn($f)=>['id'=>'flujo:'.$f['id'],'name'=>$f['nombre'],'language'=>'','category'=>'','header'=>'','body'=>(string)($f['texto']??''),'footer'=>'',
            'buttons'=>[],'variables'=>['header'=>[],'body'=>[]],'preview'=>($f['texto']??'')!=='','unsupported'=>false,'flow'=>true],
            array_values(array_filter(self::chosen($connection),fn($f)=>$f['estado']==='published')));
    }

    /** Mete al contacto de la conversación en el flujo elegido. Idempotente por requestId. */
    public function send(array $connection,array $conversation,string $workflow,string $request,?array $author):array {
        $cid=(int)$connection['id'];
        $flow=query('SELECT * FROM whatsapp_flows WHERE connection_id=? AND workflow_id=?',[$cid,$workflow])->fetch();
        if(!$flow||$flow['account_status']!=='published')throw new FlowProblem('template_unknown',422,'Esa plantilla ya no está disponible. Actualiza la lista.');
        $contact=(string)($conversation['contactId']??'');$conv=(string)($conversation['id']??'');
        $prev=query('SELECT * FROM whatsapp_flow_sends WHERE connection_id=? AND request_id=?',[$cid,$request])->fetch();
        if($prev){
            if($prev['workflow_id']!==$workflow||$prev['contact_id']!==$contact)throw new FlowProblem('request_conflict',409,'Este identificador corresponde a otro envío.');
            if($prev['state']==='requested')return self::accepted($prev);
            if($prev['state']==='sending')throw new FlowProblem('send_unknown',409,'El envío anterior todavía no tiene respuesta. Espera un momento antes de repetir.');
        }
        $now=time();
        $last=query("SELECT requested_at FROM whatsapp_flow_sends WHERE connection_id=? AND contact_id=? AND workflow_id=? AND state='requested' AND request_id<>? ORDER BY requested_at DESC LIMIT 1",[$cid,$contact,$workflow,$request])->fetchColumn();
        if($last!==false&&$now-(int)$last<self::REPEAT){$m=(int)ceil((self::REPEAT-($now-(int)$last))/60);throw new FlowProblem('repeat',409,'Esta plantilla ya se pidió para este contacto hace poco. Podrás repetirla en '.$m.' '.($m===1?'minuto':'minutos').'.',self::REPEAT-($now-(int)$last));}
        $recent=(int)query('SELECT COUNT(*) FROM whatsapp_flow_sends WHERE connection_id=? AND requested_at>?',[$cid,$now-60])->fetchColumn();
        if($recent>=self::PER_MINUTE)throw new FlowProblem('busy',429,'Se pidieron muchas plantillas en el último minuto. Espera un momento.',30);
        if($connection['status']!=='connected')throw new FlowProblem('connection_unverified',422,'Comprueba la conexión antes de enviar.');
        if(!$prev)query("INSERT INTO whatsapp_flow_sends(connection_id,request_id,workflow_id,conversation_id,contact_id,label,author_name,external_user_id,state,requested_at) VALUES(?,?,?,?,?,?,?,?,'sending',?)",
            [$cid,$request,$workflow,$conv,$contact,$flow['label'],mb_substr((string)($author['nombre']??''),0,120),$author['externo']??null,$now]);
        else query("UPDATE whatsapp_flow_sends SET state='sending',error_code=NULL,requested_at=? WHERE id=?",[$now,$prev['id']]);
        try{$this->gateway->execute($connection,fn(GhlClient $c)=>$c->addContactToWorkflow($contact,$workflow),false,true);}
        catch(GhlCooldown $e){self::mark($cid,$request,'retryable','cooldown');throw new FlowProblem('send_paused',429,'Envío pausado. La plantilla todavía no se pidió.',$e->retryAfter);}
        catch(GhlApiException $e){
            self::mark($cid,$request,'rejected','http_'.$e->status);
            if($e->status===404){query("UPDATE whatsapp_flows SET account_status='missing' WHERE connection_id=? AND workflow_id=?",[$cid,$workflow]);throw new FlowProblem('template_unknown',422,'La cuenta no encuentra el flujo de esta plantilla. El propietario debe revisarlo en Conexiones.');}
            throw new FlowProblem('send_rejected',422,in_array($e->status,[401,403],true)?'La cuenta rechazó el pedido por permisos. Revisa el permiso de escritura de contactos de la conexión.':'La cuenta no aceptó el pedido (código '.$e->status.').');
        }
        catch(Throwable $e){self::mark($cid,$request,'rejected','failed');throw new FlowProblem('send_failed',424,'No se pudo pedir la plantilla a la cuenta.');}
        self::mark($cid,$request,'requested',null);
        return self::accepted(query('SELECT * FROM whatsapp_flow_sends WHERE connection_id=? AND request_id=?',[$cid,$request])->fetch());
    }
    private static function mark(int $cid,string $request,string $state,?string $code):void {query('UPDATE whatsapp_flow_sends SET state=?,error_code=? WHERE connection_id=? AND request_id=?',[$state,$code,$cid,$request]);}
    private static function accepted(array $r):array {return ['state'=>'requested','flow'=>true,'messageId'=>null,'deliveryStatus'=>'requested','requestId'=>$r['request_id'],'requestedAt'=>gmdate('c',(int)$r['requested_at']),'label'=>$r['label']];}

    /** Seguimiento: con los mensajes de la conversación, marca «vista» la plantilla pedida si ya salió un WhatsApp después del pedido. */
    public static function track(int $cid,string $conversation,array $messages):array {
        $open=query("SELECT id,requested_at,label,author_name FROM whatsapp_flow_sends WHERE connection_id=? AND conversation_id=? AND state='requested' AND seen_at IS NULL AND requested_at>? ORDER BY requested_at",[$cid,$conversation,time()-86400])->fetchAll();
        $out=[];$hecho=[];
        foreach($open as $s){
            foreach($messages as $m){
                if(!is_array($m)||($m['direction']??'')!=='outbound')continue;
                $t=strtotime((string)($m['dateAdded']??''))?:0;$mid=(string)($m['id']??'');
                if($t>=(int)$s['requested_at']-5&&$mid!==''&&!query('SELECT 1 FROM whatsapp_flow_sends WHERE connection_id=? AND message_id=?',[$cid,$mid])->fetchColumn()){
                    query('UPDATE whatsapp_flow_sends SET seen_at=?,message_id=? WHERE id=?',[time(),$mid,$s['id']]);
                    $out[]=['label'=>$s['label'],'messageId'=>$mid];$hecho[$s['id']]=1;break;
                }
            }
        }
        $pending=array_map(fn($s)=>['label'=>$s['label'],'requestedAt'=>gmdate('c',(int)$s['requested_at'])],array_values(array_filter($open,fn($s)=>!isset($hecho[$s['id']]))));
        return ['vistas'=>$out,'pendientes'=>$pending];
    }

    /** Para Conexiones: cuántas se pidieron y cuántas aparecieron en un período. */
    public static function summary(int $cid,int $from,int $to):array {
        $r=query("SELECT COUNT(*) pedidas,SUM(seen_at IS NOT NULL) vistas FROM whatsapp_flow_sends WHERE connection_id=? AND state='requested' AND requested_at BETWEEN ? AND ?",[$cid,$from,$to])->fetch();
        return ['pedidas'=>(int)$r['pedidas'],'vistas'=>(int)$r['vistas']];
    }
}
