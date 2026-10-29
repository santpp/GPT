<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';

final class ExternalUserDirectory {
    private const PERMISSIONS=['campaignsEnabled','campaignsReadOnly','contactsEnabled','workflowsEnabled','workflowsReadOnly','triggersEnabled','funnelsEnabled','websitesEnabled','opportunitiesEnabled','dashboardStatsEnabled','bulkRequestsEnabled','appointmentsEnabled','reviewsEnabled','onlineListingsEnabled','phoneCallEnabled','conversationsEnabled','assignedDataOnly','adwordsReportingEnabled','membershipEnabled','facebookAdsReportingEnabled','attributionsReportingEnabled','settingsEnabled','tagsEnabled','leadValueEnabled','marketingEnabled','agentReportingEnabled','botService','socialPlanner','bloggingEnabled','invoiceEnabled','affiliateManagerEnabled','contentAiEnabled','refundsEnabled','recordPaymentEnabled','cancelSubscriptionEnabled','paymentsEnabled','communitiesEnabled','exportPaymentsEnabled'];
    public function __construct(private readonly GhlGateway $gateway=new GhlGateway()) {}

    public function sync(array $connection):array {
        if($connection['status']!=='connected')throw new InvalidArgumentException('Connection must be verified first');
        $data=$this->gateway->execute($connection,fn(GhlClient $client)=>$client->locationUsers($connection['location_id']));
        $users=$data['users']??null;
        if(!is_array($users)||!array_is_list($users)||count($users)>1000)throw new RuntimeException('Invalid users response');
        $normalized=[];
        foreach($users as $user)$normalized[]=$this->normalize($user);
        $id=(int)$connection['id'];
        db()->beginTransaction();
        try {
            query('UPDATE external_users SET active=FALSE WHERE connection_id=?',[$id]);
            foreach($normalized as $user)query('INSERT INTO external_users(connection_id,external_user_id,email,display_name,role_type,role_name,permissions,scopes,assigned_data_only,active) VALUES(?,?,?,?,?,?,?,?,?,TRUE) ON DUPLICATE KEY UPDATE email=VALUES(email),display_name=VALUES(display_name),role_type=VALUES(role_type),role_name=VALUES(role_name),permissions=VALUES(permissions),scopes=VALUES(scopes),assigned_data_only=VALUES(assigned_data_only),active=TRUE,synced_at=CURRENT_TIMESTAMP',[$id,$user['id'],$user['email'],$user['name'],$user['roleType'],$user['roleName'],json_encode($user['permissions'],JSON_THROW_ON_ERROR),json_encode($user['scopes'],JSON_THROW_ON_ERROR),(int)$user['assignedDataOnly']]);
            query('INSERT IGNORE INTO external_identity_memberships(identity_id,external_user_row_id) SELECT i.id,eu.id FROM external_users eu JOIN ghl_connections c ON c.id=eu.connection_id JOIN external_identities i ON i.tenant_id=c.tenant_id AND i.email=eu.email WHERE eu.connection_id=? AND eu.email IS NOT NULL',[$id]);
            db()->commit();
            query('UPDATE ghl_sync_state SET users_next_sync_at=?,users_last_success_at=CURRENT_TIMESTAMP,users_last_error=NULL WHERE connection_id=?',[time()+3600,$id]);
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        return ['count'=>count($normalized),'active'=>count($normalized)];
    }

    private function normalize(mixed $user):array {
        if(!is_array($user)||!is_string($user['id']??null)||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$user['id']))throw new RuntimeException('Invalid user id');
        $email=$user['email']??null;
        if($email!==null&&(!is_string($email)||mb_strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)))$email=null;
        $name=trim((string)($user['name']??trim((string)($user['firstName']??'').' '.(string)($user['lastName']??''))));
        if($name===''||mb_strlen($name)>190)$name=$email?:'Usuario';
        $role=is_array($user['roles']??null)?$user['roles']:[];
        $permissions=[];$rawPermissions=is_array($user['permissions']??null)?$user['permissions']:[];
        foreach(self::PERMISSIONS as $key)if(is_bool($rawPermissions[$key]??null))$permissions[$key]=$rawPermissions[$key];
        $rawScopes=$user['scopes']??[];if(is_string($rawScopes))$rawScopes=[$rawScopes];
        $scopes=[];if(is_array($rawScopes))foreach($rawScopes as $scope)if(is_string($scope)&&preg_match('~^[A-Za-z0-9/_-]+(?:\.[A-Za-z0-9/_-]+)*$~D',$scope)&&strlen($scope)<=120)$scopes[]=$scope;
        return ['id'=>$user['id'],'email'=>$email?strtolower($email):null,'name'=>$name,'roleType'=>$this->short($role['type']??null),'roleName'=>$this->short($role['role']??null),'permissions'=>$permissions,'scopes'=>array_values(array_unique($scopes)),'assignedDataOnly'=>($permissions['assignedDataOnly']??false)===true];
    }
    private function short(mixed $value):?string{return is_string($value)&&preg_match('/^[A-Za-z0-9_-]{1,40}$/D',$value)?$value:null;}
}
