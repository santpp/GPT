<?php
declare(strict_types=1);

final class PermissionPolicy {
    /** Módulos que la cuenta da por activados cuando no los menciona en los permisos de un usuario. */
    private const DEFAULT_ON=['conversationsEnabled'=>true,'contactsEnabled'=>true,'opportunitiesEnabled'=>true,'appointmentsEnabled'=>true];
    public static function capabilities(array $actor):array {
        if(($actor['identity_type']??'native')==='native') {
            $admin=($actor['role']??null)==='administrator';
            return ['configureConnections'=>$admin,'viewConversations'=>$admin,'sendMessages'=>$admin,'viewContacts'=>$admin,'editContacts'=>$admin,'viewOpportunities'=>$admin,'editOpportunities'=>$admin,'viewCalendars'=>$admin,'assignedDataOnly'=>false];
        }
        $permissions=self::object($actor['permissions']??[]);
        $scopes=self::list($actor['scopes']??[]);
        // Quien administra la subcuenta tiene todo salvo lo que se le desactive expresamente: la cuenta
        // no siempre lista los permisos de un administrador, y su lista de alcances no lo limita.
        $admin=($actor['role_name']??null)==='admin'||($actor['role_type']??null)==='agency';
        // Un permiso que la cuenta no lista vale lo que su referencia publica como valor por defecto (activado en los módulos de
        // conversaciones, contactos, oportunidades y citas); uno que se desactiva sale explícitamente en falso y manda siempre.
        $enabled=fn(string $name):bool=>$admin?($permissions[$name]??true)!==false:($permissions[$name]??(self::DEFAULT_ON[$name]??false))===true;
        $scope=fn(array $required):bool=>$admin||$scopes===[]||array_intersect($required,$scopes)!==[];
        $conversations=$enabled('conversationsEnabled')&&$scope(['conversations.readonly','conversations.write','conversations/message.readonly','conversations/message.write']);
        return [
            'configureConnections'=>false,
            'viewConversations'=>$conversations,
            'sendMessages'=>$conversations&&$scope(['conversations.write','conversations/message.write']),
            'viewContacts'=>$enabled('contactsEnabled')&&$scope(['contacts.readonly','contacts.write']),
            'editContacts'=>$enabled('contactsEnabled')&&$scope(['contacts.write']),
            'viewOpportunities'=>$enabled('opportunitiesEnabled')&&$scope(['opportunities.readonly','opportunities.write']),
            'editOpportunities'=>$enabled('opportunitiesEnabled')&&$scope(['opportunities.write']),
            'viewCalendars'=>$enabled('appointmentsEnabled')&&$scope(['calendars.readonly','calendars.write','calendars/events.readonly','calendars/events.write']),
            'assignedDataOnly'=>!$admin&&(($actor['assigned_data_only']??false)===true||(int)($actor['assigned_data_only']??0)===1),
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
