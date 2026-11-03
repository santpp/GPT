<?php
declare(strict_types=1);
require_once __DIR__.'/Roles.php';

/**
 * Auditoría: quién tocó qué, cuándo y desde dónde. Sólo se agrega: no hay ninguna función que
 * edite o borre una fila. Un fallo al anotar nunca estropea lo que la persona estaba haciendo.
 */
final class Audit
{
    public const ACTIONS = ['creo', 'edito', 'elimino', 'envio', 'firmo', 'entro', 'salio', 'asigno', 'exporto', 'bloqueo'];
    public const MODULES = ['contacto', 'conversacion', 'oportunidad', 'convenio', 'usuario', 'ajuste', 'sesion'];

    /** Quién es y con qué rol, para anotar. */
    public static function actor(array $u, ?array $membership = null): array
    {
        $native = ($u['identity_type'] ?? 'native') === 'native';
        $name = $native ? (string)($u['email'] ?? 'Cuenta') : ((string)($membership['display_name'] ?? '') !== '' ? (string)$membership['display_name'] : (string)($u['email'] ?? 'Persona'));
        return ['id' => ($native ? 'n' : 'e').(string)($u['id'] ?? ''), 'name' => $name, 'role' => Roles::of($u, $membership)];
    }

    public static function device(): string
    {
        $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $browser = str_contains($agent, 'Edg/') ? 'Edge' : (str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') ? 'Opera' : (str_contains($agent, 'Firefox/') ? 'Firefox' : (str_contains($agent, 'Chrome/') ? 'Chrome' : (str_contains($agent, 'Safari/') ? 'Safari' : 'Navegador'))));
        $system = str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Android') ? 'Android' : (preg_match('/iPhone|iPad|iOS/', $agent) ? 'iOS' : (str_contains($agent, 'Mac OS') ? 'macOS' : (str_contains($agent, 'Linux') ? 'Linux' : ''))));
        return $system !== '' ? $browser.' · '.$system : $browser;
    }

    /**
     * @param array{action:string,text:string,module:string,object?:?string,contactId?:?string,contactName?:?string,connectionId?:?int,before?:?array,after?:?array,origin?:string} $entry
     */
    public static function record(array $u, ?array $membership, array $entry): void
    {
        try {
            if (!in_array($entry['action'], self::ACTIONS, true) || !in_array($entry['module'], self::MODULES, true)) return;
            $who = self::actor($u, $membership);
            $json = static fn(?array $v): ?string => $v === null || $v === [] ? null : json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            query('INSERT INTO audit_log(tenant_id,connection_id,actor_id,actor_name,actor_role,action,action_text,module,object_label,contact_id,contact_name,device,ip,origin,before_json,after_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                (int)$u['tenant_id'], $entry['connectionId'] ?? null, $who['id'], mb_substr($who['name'], 0, 190), $who['role'],
                $entry['action'], mb_substr($entry['text'], 0, 120), $entry['module'], isset($entry['object']) ? mb_substr((string)$entry['object'], 0, 120) : null,
                $entry['contactId'] ?? null, isset($entry['contactName']) ? mb_substr((string)$entry['contactName'], 0, 190) : null,
                self::device(), mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, $entry['origin'] ?? 'sistema', $json($entry['before'] ?? null), $json($entry['after'] ?? null),
            ]);
        } catch (Throwable $e) { error_log('Audit failure: '.get_class($e)); }
    }

    /** Lo que cambió entre la ficha de antes y lo que se envió: sólo campos sencillos, sin claves ni textos largos. */
    public static function changes(array $before, array $sent): array
    {
        $b = $a = [];
        foreach ($sent as $key => $value) {
            if (!is_string($key) || !is_scalar($value) || mb_strlen((string)$value) > 120 || !array_key_exists($key, $before) || !is_scalar($before[$key] ?? null)) continue;
            if ((string)$before[$key] !== (string)$value) { $b[$key] = $before[$key]; $a[$key] = $value; }
        }
        return ['before' => $b, 'after' => $a];
    }

    /**
     * La lista para la pantalla. Quien administra la cuenta ve todo; quien supervisa una subcuenta, sólo lo de las suyas.
     * @param list<int>|null $connections null = sin restricción
     */
    public static function list(int $tenant, string $period, ?array $connections, array $filters): array
    {
        $start = $period.'-01 00:00:00';
        $end = (new DateTimeImmutable($period.'-01 00:00:00', new DateTimeZone('UTC')))->modify('+1 month')->format('Y-m-d H:i:s');
        $where = ['tenant_id=?', 'at>=?', 'at<?'];
        $args = [$tenant, $start, $end];
        if ($connections !== null) {
            if ($connections === []) return ['items' => [], 'resumen' => ['total' => 0]];
            $where[] = 'connection_id IN ('.implode(',', array_map('intval', $connections)).')';
        }
        if (in_array($filters['modulo'] ?? '', self::MODULES, true)) { $where[] = 'module=?'; $args[] = $filters['modulo']; }
        if (($filters['usuario'] ?? '') !== '') { $where[] = 'actor_name=?'; $args[] = $filters['usuario']; }
        if (($filters['contacto'] ?? '') !== '') { $where[] = '(contact_name LIKE ? OR contact_id=?)'; $args[] = '%'.addcslashes((string)$filters['contacto'], '%_\\').'%'; $args[] = $filters['contacto']; }
        $rows = query('SELECT * FROM audit_log WHERE '.implode(' AND ', $where).' ORDER BY at DESC, id DESC LIMIT 500', $args)->fetchAll();
        $items = array_map(static function (array $r): array {
            $item = [
                'id' => 'a'.$r['id'], 'at' => gmdate('c', (int)strtotime($r['at'].' UTC')),
                'actor' => $r['actor_name'], 'rol' => Roles::LABELS[$r['actor_role']] ?? (string)$r['actor_role'],
                'accion' => $r['action'], 'accionTexto' => $r['action_text'], 'modulo' => $r['module'], 'objeto' => $r['object_label'],
                'contacto' => $r['contact_name'], 'contactId' => $r['contact_id'], 'dispositivo' => $r['device'], 'ip' => $r['ip'], 'origen' => $r['origin'],
            ];
            foreach (['antes' => 'before_json', 'despues' => 'after_json'] as $key => $col) {
                $decoded = is_string($r[$col] ?? null) ? json_decode($r[$col], true) : null;
                if (is_array($decoded)) $item[$key] = $decoded;
            }
            return $item;
        }, $rows);
        return ['items' => $items, 'resumen' => ['total' => count($items)]];
    }

    /**
     * Borra los registros de hace seis meses o más (y sólo esos: la fecha de corte la fija el servidor, no quien pide).
     * Es lo único que borra la auditoría; el borrado queda anotado y esa anotación tampoco puede borrarse hasta pasados seis meses.
     * @return array{borrados:int,hasta:string}
     */
    public static function purgeOld(int $tenant): array
    {
        $cut = gmdate('Y-m-d H:i:s', (int)strtotime('-6 months'));
        $stmt = query('DELETE FROM audit_log WHERE tenant_id=? AND at < ?', [$tenant, $cut]);
        return ['borrados' => $stmt->rowCount(), 'hasta' => gmdate('c', (int)strtotime($cut.' UTC'))];
    }
}
