<?php
declare(strict_types=1);
/** 1.10.27 · «Nuevo contacto» se escribe en la cuenta: validación, duplicados, asignación, permisos y sin dobles.
 *  1.10.30 · «Asignármelo» solo asigna a quien la pantalla mostró como «tú», y se informa a quién quedó asignado en la cuenta. */
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/ContactActivity.php';
require __DIR__.'/../src/ContactActions.php';

$tenants=[];$suffix=bin2hex(random_bytes(6));$checks=0;
function createCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function key16():string{return bin2hex(random_bytes(12));}
function problem(callable $f):?ActionProblem{try{$f();}catch(ActionProblem $e){return $e;}return null;}

final class FakeCreateApi {
    public array $calls=[];public array $existing=[];public int $created=0;public ?int $failCreate=null;public ?string $rule=null;
    public function gateway():GhlGateway {
        return new GhlGateway(fn():GhlClient=>new GhlClient('fake',function(string $path,string $version,string $method,?array $payload):array {
            $this->calls[]=[$method,$path,$payload];
            if($method==='GET'&&str_starts_with($path,'/contacts/search/duplicate')){
                parse_str((string)parse_url($path,PHP_URL_QUERY),$q);
                foreach($this->existing as $c)if((($q['email']??null)!==null&&$c['email']===$q['email'])||(($q['number']??null)!==null&&$c['phone']===$q['number']))return ['status'=>200,'body'=>json_encode(['contact'=>$c])];
                return ['status'=>200,'body'=>json_encode(['contact'=>null])];
            }
            if($method==='POST'&&$path==='/contacts/'){
                if($this->failCreate)throw new GhlApiException($this->failCreate);
                $this->created++;return ['status'=>201,'body'=>json_encode(['contact'=>['id'=>'new-'.$this->created]+($this->rule!==null?['assignedTo'=>$this->rule]:[])+$payload])];
            }
            throw new GhlApiException(404);
        }));
    }
    public function creates():array{return array_values(array_filter($this->calls,fn($c)=>$c[0]==='POST'));}
}
function clearBudget(int $connection):void{query('UPDATE ghl_sync_state SET request_not_before=0,requests_today=0,rate_window_start=0,rate_window_count=0,failures=0 WHERE connection_id=?',[$connection]);}

try {
 query('INSERT INTO tenants(name,location_id) VALUES(?,?)',['Create test','cc-'.$suffix]);$tenants[]=$tenant=(int)db()->lastInsertId();
 $location='loc-'.$suffix;
 query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,'connected')",[$tenant,'Cuenta',$location,'not-used']);
 $connectionId=(int)db()->lastInsertId();
 $connection=query('SELECT * FROM ghl_connections WHERE id=?',[$connectionId])->fetch();
 foreach([['owner-1','Dueña'],['seller-1','Lucía'],['seller-9','Carla']] as [$eid,$name])query("INSERT INTO external_users(connection_id,external_user_id,display_name,permissions,scopes) VALUES(?,?,?,'{}','[]')",[$connectionId,$eid,$name]);
 $admin=['external_user_id'=>null,'self_id'=>'owner-1','capabilities'=>['viewContacts'=>true,'editContacts'=>true,'assignedDataOnly'=>false]];
 $seller=['external_user_id'=>'seller-1','capabilities'=>['viewContacts'=>true,'editContacts'=>true,'assignedDataOnly'=>true]];
 $unlinked=['external_user_id'=>null,'capabilities'=>['viewContacts'=>true,'editContacts'=>true,'assignedDataOnly'=>true]];
 $readOnly=['external_user_id'=>'seller-2','capabilities'=>['viewContacts'=>true,'editContacts'=>false,'assignedDataOnly'=>true]];
 $api=new FakeCreateApi();$actions=new ContactActions($api->gateway());
 query("INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?)",[$connectionId,'contacts:abc','[]']);

 // Validación: nada llega a la cuenta.
 createCheck(problem(fn()=>$actions->create($connection,$admin,['firstName'=>'','phone'=>'+584141234567','requestId'=>key16()]))?->reason==='invalid_name','sin nombre no se crea');
 createCheck(problem(fn()=>$actions->create($connection,$admin,['firstName'=>'Ana','requestId'=>key16()]))?->reason==='missing_contact_data','hace falta teléfono o correo');
 createCheck(problem(fn()=>$actions->create($connection,$admin,['firstName'=>'Ana','phone'=>'04141234567','requestId'=>key16()]))?->reason==='invalid_phone','el teléfono lleva el código del país');
 createCheck(problem(fn()=>$actions->create($connection,$admin,['firstName'=>'Ana','email'=>'no-es-correo','requestId'=>key16()]))?->reason==='invalid_email','un correo inválido se rechaza');
 createCheck(problem(fn()=>$actions->create($connection,$readOnly,['firstName'=>'Ana','email'=>'a@b.test','requestId'=>key16()]))?->httpStatus===403,'quien no puede editar fichas no crea contactos');
 createCheck(problem(fn()=>$actions->create($connection,$unlinked,['firstName'=>'Ana','email'=>'a@b.test','requestId'=>key16()]))?->reason==='not_linked','un vendedor sin enlace con la cuenta no crea contactos');
 createCheck($api->calls===[],'ninguna de esas pruebas llegó a la cuenta');

 // Creación correcta: lo que se manda a la cuenta.
 $key=key16();
 $r=$actions->create($connection,$seller,['firstName'=>'  Lucía ','lastName'=>'Pérez','phone'=>'+58 414 123-4567','email'=>'Lucia@Ejemplo.test','requestId'=>$key]);
 $sent=$api->creates()[0]??null;
 createCheck($r['state']==='accepted'&&$r['id']==='new-1'&&$r['name']==='Lucía Pérez','el contacto se crea en la cuenta y vuelve su identificador');
 createCheck($sent&&$sent[1]==='/contacts/'&&$sent[2]['locationId']===$location&&$sent[2]['firstName']==='Lucía'&&$sent[2]['lastName']==='Pérez'&&$sent[2]['phone']==='+584141234567'&&$sent[2]['email']==='lucia@ejemplo.test'&&$sent[2]['source']==='Ágora','se manda a la subcuenta correcta, con el teléfono limpio, el correo en minúsculas y origen «Ágora»');
 createCheck(($sent[2]['assignedTo']??null)==='seller-1','el vendedor lo crea asignado a sí mismo');
 createCheck(!query("SELECT 1 FROM directory_cache WHERE connection_id=? AND cache_key LIKE 'contacts:%'",[$connectionId])->fetchColumn(),'la lista se refresca para que el nuevo aparezca enseguida');
 $dups=array_values(array_filter($api->calls,fn($c)=>$c[0]==='GET'));
 createCheck(count($dups)===2&&str_contains($dups[0][1],'email=lucia')&&str_contains($dups[1][1],'number=%2B584141234567'),'antes de crear se pregunta a la cuenta si ya existe ese correo y ese teléfono');

 // Pulsar dos veces (mismo identificador) no crea dos.
 clearBudget($connectionId);$api->existing=[];
 $again=$actions->create($connection,$seller,['firstName'=>'  Lucía ','lastName'=>'Pérez','phone'=>'+58 414 123-4567','email'=>'Lucia@Ejemplo.test','requestId'=>$key]);
 createCheck($again['id']==='new-1'&&count($api->creates())===1,'pulsar dos veces no crea dos contactos');

 // Duplicados: no se crea otro y se ofrece abrir el existente (solo si es visible para quien lo pide).
 clearBudget($connectionId);
 $api->existing=[['id'=>'k-ana','email'=>'ana@ejemplo.test','phone'=>'+584140000001','assignedTo'=>'seller-1'],['id'=>'k-luis','email'=>'luis@ejemplo.test','phone'=>'+584140000002','assignedTo'=>'seller-9']];
 $e=problem(fn()=>$actions->create($connection,$seller,['firstName'=>'Ana','email'=>'ana@ejemplo.test','requestId'=>key16()]));
 createCheck($e?->reason==='contact_exists'&&$e->httpStatus===409&&$e->contactId==='k-ana'&&str_contains($e->getMessage(),'Ya existe un contacto con ese correo'),'si ya existe con ese correo no se crea otro y se ofrece abrirlo');
 clearBudget($connectionId);
 $e=problem(fn()=>$actions->create($connection,$seller,['firstName'=>'Luis','phone'=>'+584140000002','requestId'=>key16()]));
 createCheck($e?->reason==='contact_exists'&&$e->contactId===null&&str_contains($e->getMessage(),'asignado a otra persona'),'si el existente es de otra persona, el vendedor no lo ve ni lo abre');
 clearBudget($connectionId);
 $e=problem(fn()=>$actions->create($connection,$admin,['firstName'=>'Luis','phone'=>'+584140000002','requestId'=>key16()]));
 createCheck($e?->contactId==='k-luis','quien ve todo puede abrir el existente');
 createCheck(count($api->creates())===1,'ningún duplicado llegó a crearse');

 // Administración: sin asignar o «asignármelo».
 clearBudget($connectionId);$api->existing=[];
 $actions->create($connection,$admin,['firstName'=>'Sin','email'=>'sin@ejemplo.test','requestId'=>key16()]);
 createCheck(!array_key_exists('assignedTo',end($api->calls)[2]),'quien ve todo lo deja sin asignar por defecto');
 clearBudget($connectionId);
 $actions->create($connection,$admin,['firstName'=>'Mío','email'=>'mio@ejemplo.test','assignToMe'=>true,'requestId'=>key16()]);
 createCheck((end($api->calls)[2]['assignedTo']??null)==='owner-1','«Asignármelo» lo asigna a quien lo crea');
 clearBudget($connectionId);
 $r=$actions->create($connection,$admin,['firstName'=>'Mío2','email'=>'mio2@ejemplo.test','assignToMe'=>true,'selfId'=>'owner-1','requestId'=>key16()]);
 createCheck($r['assignedTo']==='owner-1'&&$r['assignedName']==='Dueña'&&$r['assignedAsAsked']===true,'1.10.30 · la respuesta dice a quién quedó asignado, con su nombre');
 // Si la pantalla mostró a otra persona como «tú», no se crea nada.
 clearBudget($connectionId);$before=count($api->creates());
 $e=problem(fn()=>$actions->create($connection,$admin,['firstName'=>'Otra','email'=>'otra@ejemplo.test','assignToMe'=>true,'selfId'=>'seller-9','requestId'=>key16()]));
 createCheck($e?->reason==='self_changed'&&count($api->creates())===$before,'1.10.30 · si «tú» no es quien mostró la pantalla, no se crea ni se asigna a otra persona');
 // Sin saber quién eres, «Asignármelo» no se aplica en silencio.
 $nobody=['external_user_id'=>null,'self_id'=>null,'capabilities'=>$admin['capabilities']];
 $e=problem(fn()=>$actions->create($connection,$nobody,['firstName'=>'Nadie','email'=>'nadie@ejemplo.test','assignToMe'=>true,'requestId'=>key16()]));
 createCheck($e?->reason==='self_unknown'&&str_contains($e->getMessage(),'¿Quién eres en esta subcuenta?'),'1.10.30 · si Ágora no sabe quién eres, lo dice en vez de crear sin avisar');
 // Una regla automática de la cuenta lo reasigna: se informa.
 clearBudget($connectionId);$api->rule='seller-9';
 $r=$actions->create($connection,$admin,['firstName'=>'Regla','email'=>'regla@ejemplo.test','assignToMe'=>true,'selfId'=>'owner-1','requestId'=>key16()]);
 createCheck($r['assignedTo']==='seller-9'&&$r['assignedName']==='Carla'&&$r['assignedAsAsked']===false,'1.10.30 · si la cuenta lo asigna a otra persona, la respuesta lo dice');
 $api->rule=null;

 // La cuenta rechaza: se explica y no queda nada a medias.
 clearBudget($connectionId);$api->failCreate=422;
 $e=problem(fn()=>$actions->create($connection,$admin,['firstName'=>'Rechazo','email'=>'r@ejemplo.test','requestId'=>key16()]));
 createCheck($e?->reason==='action_rejected'&&!str_contains($e->getMessage(),'GHL'),'si la cuenta rechaza el contacto, se explica sin nombres técnicos');
 echo "$checks comprobaciones".PHP_EOL;
} finally {
 foreach($tenants as $id){query('DELETE FROM external_users WHERE connection_id IN (SELECT id FROM ghl_connections WHERE tenant_id=?)',[$id]);query('DELETE FROM ghl_connections WHERE tenant_id=?',[$id]);query('DELETE FROM tenants WHERE id=?',[$id]);}
}
