<?php
declare(strict_types=1);
require_once __DIR__.'/Roles.php';
require_once __DIR__.'/PermissionPolicy.php';

/**
 * La lista única de personas: quienes entran con correo y clave (se crean y se editan aquí) y quienes entran con el
 * correo de su subcuenta (vienen sincronizadas: se ven, pero se cambian en su cuenta de origen).
 */
final class TeamDirectory
{
    private const RANK = ['CONSULTA' => 0, 'EJECUTIVO' => 1, 'SUPERVISOR' => 2, 'ADMIN' => 3];

    public static function list(int $tenant, string $meId, ?array $connections = null): array
    {
        $items = [];
        $seen = [];
        // Quien administra una subcuenta ve a las personas de las suyas; las cuentas propias (con clave) son de quien es propietario.
        $natives = $connections === null ? query('SELECT u.id,u.email,u.display_name,u.role,u.active,(SELECT MAX(d.last_used_at) FROM device_sessions d WHERE d.identity_type=\'native\' AND d.actor_id=u.id) AS last_seen FROM users u WHERE u.tenant_id=? ORDER BY u.id', [$tenant])->fetchAll() : [];
        foreach ($natives as $r) {
            $id = 'n'.$r['id'];
            $items[] = ['id' => $id, 'nombre' => self::nameOf($r['display_name'], $r['email']), 'correo' => $r['email'], 'rol' => Roles::fromStored((string)$r['role']),
                'estado' => $r['active'] ? 'activo' : 'inactivo', 'origen' => 'sistema', 'ultimoAcceso' => self::stamp($r['last_seen']), 'yo' => $id === $meId];
            $seen[strtolower((string)$r['email'])] = true;
        }
        $rows = query('SELECT i.id AS identity,i.email,eu.display_name,eu.role_name,eu.role_type,eu.assigned_data_only,eu.permissions,eu.active,(SELECT MAX(d.last_used_at) FROM device_sessions d WHERE d.identity_type=\'external\' AND d.actor_id=i.id) AS last_seen
            FROM external_identities i JOIN external_identity_memberships m ON m.identity_id=i.id JOIN external_users eu ON eu.id=m.external_user_row_id JOIN ghl_connections c ON c.id=eu.connection_id WHERE i.tenant_id=? AND c.tenant_id=?'.($connections !== null ? ' AND eu.connection_id IN ('.($connections ? implode(',', array_map('intval', $connections)) : '0').')' : '').' ORDER BY i.id', [$tenant, $tenant])->fetchAll();
        $people = [];
        foreach ($rows as $r) {
            $row = $r + ['identity_type' => 'external'];
            $caps = PermissionPolicy::capabilities($row);
            $role = ($r['role_name'] ?? null) === 'admin' || ($r['role_type'] ?? null) === 'agency' ? 'ADMIN' : (($caps['sendMessages'] ?? false) !== true ? 'CONSULTA' : (($caps['assignedDataOnly'] ?? false) === true ? 'EJECUTIVO' : 'SUPERVISOR'));
            $key = (int)$r['identity'];
            if (!isset($people[$key])) $people[$key] = ['nombre' => $r['display_name'], 'correo' => $r['email'], 'rol' => $role, 'activo' => (bool)$r['active'], 'seen' => $r['last_seen']];
            else {
                if (self::RANK[$role] > self::RANK[$people[$key]['rol']]) $people[$key]['rol'] = $role;
                $people[$key]['activo'] = $people[$key]['activo'] || (bool)$r['active'];
            }
        }
        foreach ($people as $key => $p) {
            if (isset($seen[strtolower((string)$p['correo'])])) continue;   // la misma persona ya está como cuenta propia
            $items[] = ['id' => 'e'.$key, 'nombre' => self::nameOf($p['nombre'], $p['correo']), 'correo' => $p['correo'], 'rol' => $p['rol'], 'estado' => $p['activo'] ? 'activo' : 'inactivo',
                'origen' => 'ghl', 'ultimoAcceso' => self::stamp($p['seen']), 'yo' => 'e'.$key === $meId];
        }
        return ['items' => $items];
    }

    private static function nameOf(?string $name, ?string $email): string
    {
        $name = trim((string)$name);
        return $name !== '' ? $name : ucfirst((string)strstr((string)$email, '@', true) ?: (string)$email);
    }

    private static function stamp(?string $when): ?string
    {
        return $when ? gmdate('c', (int)strtotime($when.' UTC')) : null;
    }

    public static function create(int $tenant, array $in): array
    {
        $name = trim((string)($in['nombre'] ?? ''));
        $email = strtolower(trim((string)($in['correo'] ?? '')));
        $password = (string)($in['clave'] ?? '');
        $role = (string)($in['rol'] ?? '');
        if ($name === '' || mb_strlen($name) > 190) throw new InvalidArgumentException('Escribe el nombre de la persona.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) throw new InvalidArgumentException('El correo no es válido.');
        if (strlen($password) < 12) throw new InvalidArgumentException('La clave necesita al menos 12 caracteres.');
        $stored = Roles::toStored($role);
        if ($stored === null) throw new InvalidArgumentException('Ese rol no existe.');
        if (query('SELECT 1 FROM users WHERE email=?', [$email])->fetchColumn()) throw new InvalidArgumentException('Ese correo ya tiene una cuenta.');
        query('INSERT INTO users(tenant_id,email,password_hash,role,display_name,active) VALUES(?,?,?,?,?,TRUE)', [$tenant, $email, password_hash($password, PASSWORD_DEFAULT), $stored, $name]);
        return ['id' => 'n'.db()->lastInsertId(), 'nombre' => $name, 'correo' => $email, 'rol' => $role, 'estado' => 'activo', 'origen' => 'sistema'];
    }

    /** @return array{id:int,role:string,email:string,name:string} */
    public static function native(int $tenant, string $id): array
    {
        if (!preg_match('/^n(\d{1,18})$/D', $id, $m)) throw new InvalidArgumentException('Esa persona entra con el correo de su subcuenta: su rol y su estado se cambian en la cuenta de origen y llegan solos con la sincronización.');
        $row = query('SELECT id,role,email,display_name FROM users WHERE id=? AND tenant_id=?', [(int)$m[1], $tenant])->fetch();
        if (!$row) throw new OutOfBoundsException('Esa persona ya no existe.');
        return ['id' => (int)$row['id'], 'role' => Roles::fromStored((string)$row['role']), 'email' => $row['email'], 'name' => self::nameOf($row['display_name'], $row['email'])];
    }

    private static function lastOwner(int $tenant, int $exceptId): bool
    {
        return (int)query("SELECT COUNT(*) FROM users WHERE tenant_id=? AND role='administrator' AND active=TRUE AND id<>?", [$tenant, $exceptId])->fetchColumn() === 0;
    }

    public static function setRole(int $tenant, int $actorId, string $id, string $role): array
    {
        $person = self::native($tenant, $id);
        $stored = Roles::toStored($role);
        if ($stored === null) throw new InvalidArgumentException('Ese rol no existe.');
        if ($person['id'] === $actorId) throw new InvalidArgumentException('Tu propio rol no se cambia desde aquí.');
        if ($person['role'] === 'SUPERADMIN' && $role !== 'SUPERADMIN' && self::lastOwner($tenant, $person['id'])) throw new InvalidArgumentException('Tiene que quedar al menos una persona propietaria.');
        query('UPDATE users SET role=? WHERE id=? AND tenant_id=?', [$stored, $person['id'], $tenant]);
        return ['id' => $id, 'rol' => $role, 'antes' => $person['role'], 'nombre' => $person['name']];
    }

    public static function setState(int $tenant, int $actorId, string $id, string $state): array
    {
        $person = self::native($tenant, $id);
        if (!in_array($state, ['activo', 'inactivo'], true)) throw new InvalidArgumentException('Estado no válido.');
        if ($person['id'] === $actorId && $state === 'inactivo') throw new InvalidArgumentException('No puedes desactivar tu propia cuenta.');
        if ($state === 'inactivo' && $person['role'] === 'SUPERADMIN' && self::lastOwner($tenant, $person['id'])) throw new InvalidArgumentException('Tiene que quedar al menos una persona propietaria activa.');
        query('UPDATE users SET active=? WHERE id=? AND tenant_id=?', [$state === 'activo' ? 1 : 0, $person['id'], $tenant]);
        // Quien se desactiva deja de estar dentro en todos sus dispositivos.
        if ($state === 'inactivo') query("UPDATE device_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE identity_type='native' AND actor_id=? AND revoked_at IS NULL", [$person['id']]);
        return ['id' => $id, 'estado' => $state, 'nombre' => $person['name']];
    }
}
