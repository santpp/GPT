<?php
declare(strict_types=1);
require_once __DIR__.'/PermissionPolicy.php';

/**
 * «Entrar como»: la persona propietaria ve Ágora como un usuario de una subcuenta,
 * con sus mismos permisos y sólo lo que ese usuario tiene asignado.
 *
 * Es sólo para mirar: mientras dura no se puede escribir nada (ni enviar, ni editar,
 * ni marcar como leído), así nada queda hecho en nombre de otra persona. Cada vez
 * que empieza y termina queda anotado en view_as_log.
 */
final class ViewAs {
    /** Sólo la persona propietaria (usuario nativo administrador) puede usarlo. */
    public static function allowed(array $actor):bool {
        return ($actor['identity_type']??'native')==='native'&&($actor['role']??null)==='administrator';
    }

    /** Los usuarios activos de cada subcuenta de la organización, para elegir. */
    public static function candidates(array $owner):array {
        $rows=query('SELECT eu.id,eu.connection_id,eu.external_user_id,eu.email,eu.display_name,eu.role_name,eu.role_type,c.name AS connection_name
            FROM external_users eu JOIN ghl_connections c ON c.id=eu.connection_id
            WHERE c.tenant_id=? AND eu.active=TRUE ORDER BY c.name,eu.display_name',[$owner['tenant_id']])->fetchAll();
        $out=[];
        foreach($rows as $r){
            $key=(int)$r['connection_id'];
            $out[$key]??=['id'=>$key,'name'=>$r['connection_name'],'users'=>[]];
            $admin=($r['role_name']??null)==='admin'||($r['role_type']??null)==='agency';
            $out[$key]['users'][]=['id'=>(int)$r['id'],'name'=>$r['display_name']?:($r['email']?:'Usuario'),'email'=>$r['email'],'admin'=>$admin];
        }
        return array_values($out);
    }

    /** Empieza a ver como el usuario elegido. Devuelve lo que se guarda en la sesión. */
    public static function start(array $owner,int $rowId):array {
        $row=self::row((int)$owner['tenant_id'],$rowId);
        if(!$row)throw new InvalidArgumentException('view_as_unknown');
        query('INSERT INTO view_as_log(tenant_id,user_id,connection_id,external_user_id,started_at) VALUES(?,?,?,?,?)',
            [(int)$owner['tenant_id'],(int)$owner['id'],(int)$row['connection_id'],$row['external_user_id'],time()]);
        return ['row'=>(int)$row['id'],'log'=>(int)db()->lastInsertId()];
    }

    public static function stop(array $state):void {
        if(isset($state['log']))query('UPDATE view_as_log SET ended_at=? WHERE id=? AND ended_at IS NULL',[time(),(int)$state['log']]);
    }

    /**
     * El usuario como el que se mira, con la forma de un usuario de equipo.
     * Null si ya no existe o dejó de estar activo: entonces se vuelve a la vista propia.
     */
    public static function actor(array $owner,array $state):?array {
        $row=self::row((int)$owner['tenant_id'],(int)($state['row']??0));
        if(!$row)return null;
        $capabilities=PermissionPolicy::capabilities($row+['identity_type'=>'external']);
        return [
            'id'=>-(int)$row['id'],
            'tenant_id'=>(int)$owner['tenant_id'],
            'email'=>$row['email'],
            'role'=>'team',
            'identity_type'=>'external',
            'capabilities'=>$capabilities,
            'view_as'=>$row+['capabilities'=>$capabilities],
        ];
    }

    /** Lo que la subcuenta permite a ese usuario: igual que si hubiera entrado él. */
    public static function membership(array $actor,int $connectionId):?array {
        $row=$actor['view_as']??null;
        if(!is_array($row)||(int)$row['connection_id']!==$connectionId)return null;
        $row['can_assign']=($row['role_name']??null)==='admin'||($row['role_type']??null)==='agency';
        return $row;
    }

    /** Lo que la pantalla necesita para el aviso de arriba. */
    public static function describe(array $actor):?array {
        $row=$actor['view_as']??null;
        if(!is_array($row))return null;
        return ['name'=>$row['display_name']?:($row['email']?:'Usuario'),'email'=>$row['email'],'connectionId'=>(int)$row['connection_id'],'connection'=>$row['connection_name']];
    }

    private static function row(int $tenant,int $rowId):?array {
        if($rowId<=0)return null;
        $row=query('SELECT eu.*,c.name AS connection_name FROM external_users eu JOIN ghl_connections c ON c.id=eu.connection_id
            WHERE eu.id=? AND c.tenant_id=? AND eu.active=TRUE',[$rowId,$tenant])->fetch();
        return $row?:null;
    }
}
