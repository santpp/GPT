<?php
declare(strict_types=1);
require __DIR__.'/../src/PermissionPolicy.php';
function policyCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
$native=PermissionPolicy::capabilities(['identity_type'=>'native','role'=>'administrator']);
policyCheck($native['configureConnections']&&$native['sendMessages'],'native administrator configures connections');
$external=PermissionPolicy::capabilities(['identity_type'=>'external','permissions'=>['conversationsEnabled'=>true,'contactsEnabled'=>true,'opportunitiesEnabled'=>false,'appointmentsEnabled'=>true,'assignedDataOnly'=>true],'scopes'=>['conversations.readonly','conversations/message.readonly','contacts.readonly','calendars/events.readonly'],'assigned_data_only'=>1]);
policyCheck(!$external['configureConnections']&&$external['viewConversations']&&!$external['sendMessages'],'imported read-only user cannot configure or send');
policyCheck($external['viewContacts']&&!$external['viewOpportunities']&&$external['viewCalendars'],'feature permissions hide disabled modules');
policyCheck($external['assignedDataOnly'],'assigned-only restriction is preserved');
$writer=PermissionPolicy::capabilities(['identity_type'=>'external','permissions'=>json_encode(['conversationsEnabled'=>true]),'scopes'=>json_encode(['conversations/message.write'])]);
policyCheck($writer['sendMessages'],'message write scope enables replies');
$disabled=PermissionPolicy::capabilities(['identity_type'=>'external','permissions'=>['conversationsEnabled'=>false],'scopes'=>['conversations/message.write']]);
policyCheck(!$disabled['viewConversations']&&!$disabled['sendMessages'],'disabled feature overrides write scope');
// Opportunities: seeing them and changing them are different scopes, and the
// board must never offer a write to a user whose key cannot perform it.
$opportunityReader=PermissionPolicy::capabilities(['identity_type'=>'external','permissions'=>['opportunitiesEnabled'=>true],'scopes'=>['opportunities.readonly']]);
policyCheck($opportunityReader['viewOpportunities']&&!$opportunityReader['editOpportunities'],'read scope shows opportunities without offering to change them');
$opportunityWriter=PermissionPolicy::capabilities(['identity_type'=>'external','permissions'=>['opportunitiesEnabled'=>true],'scopes'=>['opportunities.write']]);
policyCheck($opportunityWriter['viewOpportunities']&&$opportunityWriter['editOpportunities'],'write scope allows opening and editing opportunities');
$opportunityOff=PermissionPolicy::capabilities(['identity_type'=>'external','permissions'=>['opportunitiesEnabled'=>false],'scopes'=>['opportunities.write']]);
policyCheck(!$opportunityOff['viewOpportunities']&&!$opportunityOff['editOpportunities'],'a disabled module overrides the opportunity write scope');
policyCheck($native['editOpportunities'],'the owner can change opportunities');

// Administrador de la subcuenta: todo, salvo lo que se le desactive expresamente.
$subAdmin=PermissionPolicy::capabilities(['identity_type'=>'external','role_name'=>'admin','permissions'=>[],'scopes'=>['campaigns.readonly']]);
policyCheck($subAdmin['viewConversations']&&$subAdmin['sendMessages']&&$subAdmin['viewContacts']&&$subAdmin['editContacts']&&$subAdmin['viewOpportunities']&&$subAdmin['editOpportunities']&&!$subAdmin['configureConnections']&&!$subAdmin['assignedDataOnly'],'a sub-account administrator sees everything even when the account lists no permissions, and never configures connections');
$agency=PermissionPolicy::capabilities(['identity_type'=>'external','role_type'=>'agency','permissions'=>'{}','scopes'=>'[]']);
policyCheck($agency['viewConversations']&&$agency['sendMessages'],'an agency user is treated as an administrator too');
$adminOff=PermissionPolicy::capabilities(['identity_type'=>'external','role_name'=>'admin','permissions'=>['conversationsEnabled'=>false]]);
policyCheck(!$adminOff['viewConversations']&&!$adminOff['sendMessages'],'a module switched off for an administrator stays off');
$plain=PermissionPolicy::capabilities(['identity_type'=>'external','role_name'=>'user','permissions'=>[],'scopes'=>[]]);
policyCheck(!$plain['viewConversations'],'an ordinary user with no listed permissions still sees nothing');
