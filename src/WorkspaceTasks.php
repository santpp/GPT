<?php
declare(strict_types=1);

/** Tareas del equipo y recordatorios de seguimiento. Viven en la base de Ágora, no en la cuenta de origen. */
final class WorkspaceTasks
{
    private const ORIGINS = ['recordatorio', 'manual', 'sistema', 'convenio'];

    private static function when(?string $at): array
    {
        if ($at === null) return [null, null];
        $t = strtotime($at.' UTC');
        return [gmdate('Y-m-d', $t), gmdate('H:i', $t)];
    }

    private static function present(array $r): array
    {
        [$date, $time] = self::when($r['due_at']);
        return [
            'id' => 'tk_'.$r['id'], 'titulo' => $r['title'], 'detalle' => (string)($r['detail'] ?? ''), 'responsable' => (string)($r['assignee_name'] ?? ''),
            'vence' => $date, 'hora' => $time, 'estado' => $r['state'], 'origen' => $r['origin'],
            'contacto' => $r['contact_name'], 'contactId' => $r['contact_id'], 'conversationId' => $r['conversation_id'],
        ];
    }

    /**
     * @param list<string> $mine  cómo se llama quien mira (puede llamarse distinto en cada subcuenta)
     */
    public static function list(int $tenant, string $period, string $whoAmI, array $mine, bool $seesAll, string $user = '', ?array $connections = null): array
    {
        $end = (new DateTimeImmutable($period.'-01 00:00:00', new DateTimeZone('UTC')))->modify('+1 month')->format('Y-m-d H:i:s');
        $start = $period.'-01 00:00:00';
        // Lo del mes, y lo pendiente que ya venció: una tarea atrasada no desaparece al cambiar de mes.
        $where = ["tenant_id=?", "((due_at>=? AND due_at<?) OR due_at IS NULL OR (state='pendiente' AND due_at<?))"];
        $args = [$tenant, $start, $end, $start];
        // Quien supervisa una subcuenta ve las tareas de las suyas, no las de otra empresa del mismo espacio.
        if ($seesAll && $connections !== null) {
            $in = $connections ? implode(',', array_map('intval', $connections)) : '0';
            $where[] = "(connection_id IS NULL OR connection_id IN ($in) OR created_by=?)";
            $args[] = $whoAmI;
        }
        if (!$seesAll) {
            $marks = $mine ? implode(',', array_fill(0, count($mine), '?')) : "''";
            $where[] = "(created_by=? OR assignee_name IN ($marks))";
            $args = array_merge($args, [$whoAmI], $mine);
        }
        if ($user !== '') { $where[] = 'assignee_name=?'; $args[] = $user; }
        $rows = query('SELECT * FROM workspace_tasks WHERE '.implode(' AND ', $where)." ORDER BY state='hecha', due_at IS NULL, due_at ASC, id ASC LIMIT 500", $args)->fetchAll();
        $items = array_map([self::class, 'present'], $rows);
        $pending = count(array_filter($items, fn(array $i): bool => $i['estado'] === 'pendiente'));
        return ['items' => $items, 'resumen' => ['pendientes' => $pending, 'hechas' => count($items) - $pending]];
    }

    /** @return array<string,mixed> la tarea creada, con la forma de la lista */
    public static function create(int $tenant, string $createdBy, array $in, ?int $connection = null): array
    {
        $title = trim((string)($in['titulo'] ?? ''));
        if ($title === '' || mb_strlen($title) > 190) throw new InvalidArgumentException('La tarea necesita un título de hasta 190 letras.');
        $detail = trim((string)($in['detalle'] ?? ''));
        if (mb_strlen($detail) > 500) throw new InvalidArgumentException('El detalle admite hasta 500 letras.');
        $due = self::due($in['vence'] ?? null, $in['hora'] ?? null);
        $origin = in_array($in['origen'] ?? 'manual', self::ORIGINS, true) ? ($in['origen'] ?? 'manual') : 'manual';
        $assignee = trim((string)($in['responsable'] ?? ''));
        if (mb_strlen($assignee) > 190) throw new InvalidArgumentException('El nombre del responsable es demasiado largo.');
        $contact = self::id($in['contactId'] ?? null);
        $conversation = self::id($in['conversationId'] ?? null);
        $name = isset($in['contacto']) && is_string($in['contacto']) ? mb_substr(trim($in['contacto']), 0, 190) : null;
        query('INSERT INTO workspace_tasks(tenant_id,connection_id,title,detail,assignee_name,due_at,origin,contact_id,contact_name,conversation_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
            [$tenant, $connection, $title, $detail !== '' ? $detail : null, $assignee !== '' ? $assignee : null, $due, $origin, $contact, $name, $conversation, $createdBy]);
        $id = (int)db()->lastInsertId();
        return self::present(query('SELECT * FROM workspace_tasks WHERE id=?', [$id])->fetch());
    }

    public static function find(int $tenant, string $id): ?array
    {
        if (!preg_match('/^(?:tk_)?(\d{1,18})$/D', $id, $m)) return null;
        $row = query('SELECT * FROM workspace_tasks WHERE id=? AND tenant_id=?', [(int)$m[1], $tenant])->fetch();
        return $row ?: null;
    }

    public static function setState(array $task, string $state): void
    {
        if (!in_array($state, ['pendiente', 'hecha'], true)) throw new InvalidArgumentException('Estado no válido.');
        query('UPDATE workspace_tasks SET state=?,done_at=? WHERE id=?', [$state, $state === 'hecha' ? gmdate('Y-m-d H:i:s') : null, $task['id']]);
    }

    public static function due(mixed $date, mixed $time): ?string
    {
        if ($date === null || $date === '') return null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || strtotime($date) === false) throw new InvalidArgumentException('La fecha no es válida.');
        $hour = is_string($time) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $time) ? $time : '09:00';
        return $date.' '.$hour.':00';
    }

    private static function id(mixed $v): ?string { return is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $v) ? $v : null; }
}
