<?php
declare(strict_types=1);
require_once __DIR__.'/WorkspaceTasks.php';

/**
 * Recordatorio de seguimiento: se elige cuándo volver a escribirle a alguien. Deja una tarea a nombre de quien lo
 * puso y la conversación se va de primera en su bandeja, hasta que manda un mensaje (entonces se cierra sola).
 */
final class Reminders
{
    public static function set(int $tenant, int $connection, string $conversationId, string $createdBy, string $assignee, array $in): array
    {
        $at = is_string($in['at'] ?? null) && preg_match('/^(\d{4}-\d{2}-\d{2})T([01]\d|2[0-3]):([0-5]\d)$/D', $in['at'], $m) && strtotime($m[1]) !== false ? $m[1].' '.$m[2].':'.$m[3].':00' : null;
        if ($at === null) throw new InvalidArgumentException('Elige un día y una hora válidos.');
        $note = isset($in['nota']) && is_string($in['nota']) ? mb_substr(trim($in['nota']), 0, 140) : '';
        $contactId = is_string($in['contactId'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $in['contactId']) ? $in['contactId'] : null;
        $contactName = is_string($in['contactName'] ?? null) ? mb_substr(trim($in['contactName']), 0, 190) : null;
        $existing = query('SELECT task_id FROM conversation_reminders WHERE connection_id=? AND conversation_id=?', [$connection, $conversationId])->fetch();
        $title = 'Escribirle a '.($contactName !== null && $contactName !== '' ? $contactName : 'la persona');
        $taskFields = ['titulo' => $title, 'detalle' => $note, 'vence' => substr($at, 0, 10), 'hora' => substr($at, 11, 5), 'responsable' => $assignee, 'origen' => 'recordatorio', 'contactId' => $contactId, 'conversationId' => $conversationId, 'contacto' => $contactName];
        $taskId = $existing && $existing['task_id'] ? (int)$existing['task_id'] : null;
        if ($taskId && query('SELECT 1 FROM workspace_tasks WHERE id=? AND tenant_id=?', [$taskId, $tenant])->fetchColumn()) {
            query("UPDATE workspace_tasks SET title=?,detail=?,due_at=?,assignee_name=?,contact_id=?,contact_name=?,state='pendiente',done_at=NULL WHERE id=?", [$title, $note !== '' ? $note : null, $at, $assignee !== '' ? $assignee : null, $contactId, $contactName, $taskId]);
        } else {
            $created = WorkspaceTasks::create($tenant, $createdBy, $taskFields, $connection);
            $taskId = (int)substr($created['id'], 3);
        }
        query('INSERT INTO conversation_reminders(connection_id,conversation_id,remind_at,note,contact_id,contact_name,task_id,created_by) VALUES(?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE remind_at=VALUES(remind_at),note=VALUES(note),contact_id=VALUES(contact_id),contact_name=VALUES(contact_name),task_id=VALUES(task_id),created_by=VALUES(created_by)',
            [$connection, $conversationId, $at, $note !== '' ? $note : null, $contactId, $contactName, $taskId, $createdBy]);
        return ['at' => str_replace(' ', 'T', substr($at, 0, 16))];
    }

    /** Quitar un recordatorio quita también la tarea que había dejado. */
    public static function remove(int $tenant, int $connection, string $conversationId): void
    {
        $row = query('SELECT task_id FROM conversation_reminders WHERE connection_id=? AND conversation_id=?', [$connection, $conversationId])->fetch();
        if (!$row) return;
        if ($row['task_id']) query("DELETE FROM workspace_tasks WHERE id=? AND tenant_id=? AND origin='recordatorio'", [$row['task_id'], $tenant]);
        query('DELETE FROM conversation_reminders WHERE connection_id=? AND conversation_id=?', [$connection, $conversationId]);
    }

    /** Se mandó un mensaje: el recordatorio se cumplió. La tarea queda hecha y el recordatorio se va de la lista. */
    public static function fulfil(int $tenant, int $connection, string $conversationId): void
    {
        try {
            $row = query('SELECT task_id FROM conversation_reminders WHERE connection_id=? AND conversation_id=?', [$connection, $conversationId])->fetch();
            if (!$row) return;
            if ($row['task_id']) query("UPDATE workspace_tasks SET state='hecha',done_at=? WHERE id=? AND tenant_id=?", [gmdate('Y-m-d H:i:s'), $row['task_id'], $tenant]);
            query('DELETE FROM conversation_reminders WHERE connection_id=? AND conversation_id=?', [$connection, $conversationId]);
        } catch (Throwable $e) { error_log('Reminder fulfil failure: '.get_class($e)); }
    }

    /** Los recordatorios de quien mira, para que la bandeja ponga esas conversaciones de primeras. */
    public static function forInbox(int $connection, string $createdBy): array
    {
        $out = [];
        foreach (query('SELECT conversation_id,remind_at,note FROM conversation_reminders WHERE connection_id=? AND created_by=?', [$connection, $createdBy])->fetchAll() as $r)
            $out[$r['conversation_id']] = ['at' => str_replace(' ', 'T', substr($r['remind_at'], 0, 16)), 'nota' => (string)($r['note'] ?? '')];
        return $out;
    }
}
