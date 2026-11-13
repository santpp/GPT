<?php
declare(strict_types=1);
namespace Agora\Security;

/** Usar solo con identidad y asignaciones obtenidas de DB por el servidor. */
final class TenantPolicy {
    public static function isMaster(array $actor): bool {
        return !empty($actor['active']) && ($actor['role'] ?? '') === 'MASTER';
    }
    public static function assertTenant(array $actor, array $tenant, array $assignedTenantIds): void {
        if (empty($actor['active'])) throw new \RuntimeException('Sesión no autorizada', 403);
        if (self::isMaster($actor)) return;
        if ((int)$actor['agency_id'] !== (int)$tenant['agency_id']) throw new \RuntimeException('Agencia ajena', 403);
        $role = $actor['role'] ?? '';
        if ($role === 'SUPERADMIN' && in_array((int)$tenant['id'], array_map('intval', $assignedTenantIds), true)) return;
        if (in_array($role, ['ADMIN','USUARIO'], true) && (int)($actor['local_tenant_id'] ?? 0) === (int)$tenant['id']) return;
        throw new \RuntimeException('Subcuenta no asignada', 403);
    }
    public static function assertMaster(array $actor): void {
        if (!self::isMaster($actor)) throw new \RuntimeException('Solo el dueño Master', 403);
    }
    public static function assertOwnProfile(array $actor, int $targetUserId, array $body): void {
        if (empty($actor['active']) || (int)$actor['id'] !== $targetUserId) throw new \RuntimeException('Perfil ajeno',403);
        if (array_diff(array_keys($body), ['nombre','correo','telefono','foto'])) throw new \InvalidArgumentException('Campo no permitido',422);
    }
    public static function assertRoleAssignable(array $actor, string $role): void {
        if (!in_array($role,['SUPERADMIN','ADMIN','USUARIO'],true)) throw new \RuntimeException('Rol no asignable',403);
        if ($role==='SUPERADMIN') { self::assertMaster($actor); return; }
        if (empty($actor['active']) || !in_array($actor['role']??'', ['MASTER','SUPERADMIN'],true)) throw new \RuntimeException('Solo Agencia',403);
    }
}
