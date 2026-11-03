<?php
declare(strict_types=1);

/**
 * Los cinco roles de las pantallas nuevas, y quién tiene cuál.
 *
 * Quien entra con correo y clave (la cuenta propietaria y las que ella crea) trae su rol guardado.
 * Quien entra con el correo de su subcuenta no lo trae: se deduce de lo que esa subcuenta le permite,
 * así que cambia donde se cambia —en la cuenta de origen— y entra por la sincronización.
 */
final class Roles
{
    public const ALL = ['SUPERADMIN', 'ADMIN', 'SUPERVISOR', 'EJECUTIVO', 'CONSULTA'];
    private const NATIVE = ['administrator' => 'SUPERADMIN', 'admin' => 'ADMIN', 'supervisor' => 'SUPERVISOR', 'seller' => 'EJECUTIVO', 'viewer' => 'CONSULTA'];
    public const LABELS = ['SUPERADMIN' => 'Propietario', 'ADMIN' => 'Administración', 'SUPERVISOR' => 'Supervisión', 'EJECUTIVO' => 'Ejecutivo', 'CONSULTA' => 'Consulta'];
    /** Lo que abre cada permiso (la misma matriz que enseña la pantalla de Usuarios y roles). */
    private const MATRIX = [
        'resumen' => ['SUPERADMIN'],
        'branding' => ['SUPERADMIN'],
                'auditoria' => ['SUPERADMIN'],
        'equipo' => ['SUPERADMIN', 'ADMIN', 'SUPERVISOR'],
        'tareas' => ['SUPERADMIN', 'ADMIN', 'SUPERVISOR', 'EJECUTIVO'],
    ];

    /** De rol guardado (enum de la base) a rol de las pantallas, y al revés. */
    public static function fromStored(string $stored): string { return self::NATIVE[$stored] ?? 'CONSULTA'; }
    public static function toStored(string $role): ?string { $found = array_search($role, self::NATIVE, true); return $found === false ? null : (string)$found; }

    public static function of(array $actor, ?array $membership = null): string
    {
        if (($actor['identity_type'] ?? 'native') === 'native') return self::fromStored((string)($actor['role'] ?? ''));
        $m = $membership ?? [];
        if (($m['can_assign'] ?? false) === true) return 'ADMIN';
        $caps = $m['capabilities'] ?? ($actor['capabilities'] ?? []);
        if (($caps['sendMessages'] ?? false) !== true) return 'CONSULTA';
        return ($caps['assignedDataOnly'] ?? false) === true ? 'EJECUTIVO' : 'SUPERVISOR';
    }

    public static function can(string $role, string $permission): bool { return in_array($role, self::MATRIX[$permission] ?? [], true); }
}
