<?php
declare(strict_types=1);
require_once __DIR__.'/PermissionPolicy.php';

final class Actor {
    public static function fromSession(array $session):?array {
        if(isset($session['uid'])){
            $user=query('SELECT id,tenant_id,email,role,ghl_user_id FROM users WHERE id=?',[$session['uid']])->fetch();
            return $user?($user+['identity_type'=>'native','capabilities'=>PermissionPolicy::capabilities($user+['identity_type'=>'native'])]):null;
        }
        if(!isset($session['external_identity_id']))return null;
        $identity=query('SELECT i.id,i.tenant_id,i.email FROM external_identities i WHERE i.id=? AND EXISTS(SELECT 1 FROM external_identity_memberships m JOIN external_users eu ON eu.id=m.external_user_row_id WHERE m.identity_id=i.id AND eu.active=TRUE)',[$session['external_identity_id']])->fetch();
        if(!$identity)return null;
        $rows=query('SELECT eu.* FROM external_identity_memberships m JOIN external_users eu ON eu.id=m.external_user_row_id JOIN ghl_connections c ON c.id=eu.connection_id WHERE m.identity_id=? AND eu.active=TRUE AND c.tenant_id=?',[$identity['id'],$identity['tenant_id']])->fetchAll();
        $capabilities=['configureConnections'=>false,'viewConversations'=>false,'sendMessages'=>false,'viewContacts'=>false,'editContacts'=>false,'viewOpportunities'=>false,'viewCalendars'=>false,'assignedDataOnly'=>true];
        foreach($rows as $row){$current=PermissionPolicy::capabilities($row+['identity_type'=>'external']);foreach($capabilities as $key=>$value)$capabilities[$key]=$key==='assignedDataOnly'?($value&&$current[$key]):($value||$current[$key]);}
        return ['id'=>(int)$identity['id'],'tenant_id'=>(int)$identity['tenant_id'],'email'=>$identity['email'],'role'=>'team','identity_type'=>'external','capabilities'=>$capabilities];
    }

    public static function connectionMembership(array $actor,int $connectionId):?array {
        if(($actor['identity_type']??'native')==='native')return $actor['role']==='administrator'?['external_user_id'=>null,'capabilities'=>PermissionPolicy::capabilities($actor),'assigned_data_only'=>false]:null;
        $row=query('SELECT eu.* FROM external_identity_memberships m JOIN external_users eu ON eu.id=m.external_user_row_id JOIN ghl_connections c ON c.id=eu.connection_id WHERE m.identity_id=? AND eu.connection_id=? AND eu.active=TRUE AND c.tenant_id=? LIMIT 1',[$actor['id'],$connectionId,$actor['tenant_id']])->fetch();
        if(!$row)return null;
        $row['capabilities']=PermissionPolicy::capabilities($row+['identity_type'=>'external']);return $row;
    }

    public static function filterConversations(array $items,array $membership):array {
        if(!($membership['capabilities']['assignedDataOnly']??false))return $items;
        $externalId=$membership['external_user_id']??'';
        return array_values(array_filter($items,fn($conversation)=>is_array($conversation)&&($conversation['assignedTo']??null)===$externalId));
    }
}
