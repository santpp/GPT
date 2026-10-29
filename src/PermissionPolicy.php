<?php
declare(strict_types=1);

final class PermissionPolicy {
    public static function capabilities(array $actor):array {
        if(($actor['identity_type']??'native')==='native') {
            $admin=($actor['role']??null)==='administrator';
            return ['configureConnections'=>$admin,'viewConversations'=>$admin,'sendMessages'=>$admin,'viewContacts'=>$admin,'editContacts'=>$admin,'viewOpportunities'=>$admin,'viewCalendars'=>$admin,'assignedDataOnly'=>false];
        }
        $permissions=self::object($actor['permissions']??[]);
        $scopes=self::list($actor['scopes']??[]);
        $enabled=fn(string $name):bool=>($permissions[$name]??false)===true;
        $scope=fn(array $required):bool=>$scopes===[]||array_intersect($required,$scopes)!==[];
        $conversations=$enabled('conversationsEnabled')&&$scope(['conversations.readonly','conversations.write','conversations/message.readonly','conversations/message.write']);
        return [
            'configureConnections'=>false,
            'viewConversations'=>$conversations,
            'sendMessages'=>$conversations&&$scope(['conversations.write','conversations/message.write']),
            'viewContacts'=>$enabled('contactsEnabled')&&$scope(['contacts.readonly','contacts.write']),
            'editContacts'=>$enabled('contactsEnabled')&&$scope(['contacts.write']),
            'viewOpportunities'=>$enabled('opportunitiesEnabled')&&$scope(['opportunities.readonly','opportunities.write']),
            'viewCalendars'=>$enabled('appointmentsEnabled')&&$scope(['calendars.readonly','calendars.write','calendars/events.readonly','calendars/events.write']),
            'assignedDataOnly'=>($actor['assigned_data_only']??false)===true||(int)($actor['assigned_data_only']??0)===1,
        ];
    }
    private static function object(mixed $value):array {
        if(is_string($value))$value=json_decode($value,true);
        return is_array($value)&&!array_is_list($value)?$value:[];
    }
    private static function list(mixed $value):array {
        if(is_string($value))$value=json_decode($value,true);
        if(is_string($value))$value=[$value];
        return is_array($value)?array_values(array_filter($value,'is_string')):[];
    }
}
