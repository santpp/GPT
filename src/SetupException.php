<?php
declare(strict_types=1);
final class SetupException extends RuntimeException {
    public const MESSAGES=[
        'encryption_module_missing'=>'El servidor necesita habilitar el módulo de cifrado para guardar conexiones.',
        'encryption_key_missing'=>'Falta preparar la clave de cifrado del servidor. Completa la instalación antes de guardar conexiones.',
        'encryption_key_unreadable'=>'El servidor no puede leer su clave de cifrado. El administrador debe revisar los permisos del archivo privado.',
        'encryption_key_unwritable'=>'No se puede preparar la protección. Revisa el propietario y los permisos de la carpeta privada desde el alojamiento.',
        'encryption_restore_required'=>'Ya hay conexiones guardadas. Es necesario restaurar la clave original; no se creará una nueva.',
        'encryption_key_unsafe_path'=>'La clave de protección debe estar fuera de la carpeta pública del sitio.',
        'encryption_key_invalid'=>'La clave de cifrado del servidor no tiene un formato válido. Revisa la instalación sin sustituir una clave que ya proteja datos.',
        'schema_pending'=>'Falta aplicar una actualización de la base de datos. El administrador debe ejecutar las migraciones pendientes.',
        'database_unavailable'=>'No se ha podido comprobar la base de datos. Revisa su configuración y permisos de acceso.',
        'runtime_missing'=>'El servidor necesita PHP 8.3 y los módulos requeridos por la aplicación.',
        'origin_unconfigured'=>'El dominio seguro de la aplicación no está configurado correctamente.',
    ];
    public function __construct(public readonly string $issue) {parent::__construct(self::MESSAGES[$issue]??'La instalación necesita revisión.');}
}
