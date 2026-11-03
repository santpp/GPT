<?php
declare(strict_types=1);
require_once __DIR__.'/GhlClient.php';

/**
 * Subcuenta de demostración: «Academia Horizonte», con personas, conversaciones, oportunidades y ventas ficticias.
 * Es una conexión marcada `is_demo`: la pasarela le entrega un cliente cuyo transporte responde desde aquí, así que
 * NUNCA se llama a la cuenta de origen, no gasta cuota y se puede enseñar sin riesgo. Todo es inventado; los números
 * de teléfono son de ficción. Lo que se hace en ella (mandar un mensaje, editar una ficha, cerrar una venta) se
 * recuerda, para que la demostración se sienta viva.
 */
final class DemoSpace
{
    public const LOCATION = 'demo-academia-horizonte';
    private const STATE = 'demo:state';
    /** La dirección de la conexión que se está sirviendo (todo lo que se devuelve debe llevarla). */
    private static string $loc = self::LOCATION;

    /** Crea la subcuenta (una sola vez) para el primer espacio. Idempotente: se llama al terminar las migraciones. */
    public static function ensure(?int $forTenant = null): void
    {
        try {
            $tenant = $forTenant ?? query('SELECT id FROM tenants ORDER BY id LIMIT 1')->fetchColumn();
            if (!$tenant) return;
            if (query('SELECT 1 FROM ghl_connections WHERE tenant_id=? AND is_demo=TRUE', [(int)$tenant])->fetchColumn()) return;
            query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status,currency,is_demo) VALUES(?,?,?,?,'connected','USD',TRUE)", [(int)$tenant, 'Demo · Academia Horizonte', $forTenant ? self::LOCATION.'-'.$forTenant : self::LOCATION, 'demo']);
            $id = (int)db()->lastInsertId();
            foreach (self::users() as $u)
                query("INSERT IGNORE INTO external_users(connection_id,external_user_id,email,display_name,role_type,role_name,permissions,scopes,assigned_data_only,active) VALUES(?,?,?,?,?,?,'{}','[]',?,TRUE)",
                    [$id, $u['id'], $u['email'], $u['name'], $u['roles']['type'], $u['roles']['role'], $u['roles']['role'] === 'admin' ? 0 : 1]);
        } catch (Throwable $e) { error_log('Demo space not created: '.get_class($e)); }
    }

    // ---------------------------------------------------------------------------------------------
    // Los datos
    // ---------------------------------------------------------------------------------------------
    public static function users(): array
    {
        return [
            ['id' => 'du1', 'name' => 'Rosa Natalia Ortega', 'email' => 'rosa@demo.agora.test', 'roles' => ['type' => 'account', 'role' => 'admin']],
            ['id' => 'du2', 'name' => 'Valeria Soto', 'email' => 'valeria@demo.agora.test', 'roles' => ['type' => 'account', 'role' => 'user']],
            ['id' => 'du3', 'name' => 'Andrés Molina', 'email' => 'andres@demo.agora.test', 'roles' => ['type' => 'account', 'role' => 'user']],
            ['id' => 'du4', 'name' => 'Camila Reyes', 'email' => 'camila@demo.agora.test', 'roles' => ['type' => 'account', 'role' => 'user']],
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function people(): array
    {
        $now = time(); $h = 3600; $d = 86400;
        // id, nombre, teléfono, correo, fuente, responsable, etiquetas, hace cuánto entró, canal, último mensaje, quién escribió, hace cuánto, sin leer
        $rows = [
            ['dc1', 'Mariana Pérez Alvarado', '+58 412 555 0101', 'mariana.perez@ejemplo.test', 'Instagram', 'du2', ['interesado', 'diplomado'], 2 * $h, 'TYPE_INSTAGRAM', '¡Hola! Vi la publicación del diplomado y quiero más información.', 'inbound', 3 * 60, 2],
            ['dc2', 'Luis Alberto Ramírez', '+58 414 555 0102', 'luis.ramirez@ejemplo.test', 'Facebook', 'du3', ['convenio'], 5 * $h, 'TYPE_WHATSAPP', '¿El pago se puede hacer en dos partes? Me interesa empezar en enero.', 'inbound', 18 * 60, 1],
            ['dc3', 'Carla Sofía Montes', '+58 416 555 0103', 'carla.montes@ejemplo.test', 'Referido', 'du2', ['seguimiento'], 1 * $d, 'TYPE_WHATSAPP', 'Listo, ya hice la transferencia. Les envío el comprobante.', 'inbound', 70 * 60, 3],
            ['dc4', 'José Gregorio Díaz', '+58 424 555 0104', 'jose.diaz@ejemplo.test', 'Sitio web', 'du4', ['frío'], 2 * $d, 'TYPE_SMS', 'Perfecto, quedo atento al enlace del convenio.', 'outbound', 3 * $h, 0],
            ['dc5', 'Andreína Castillo', '+58 412 555 0105', 'andreina.castillo@ejemplo.test', 'Instagram', 'du3', ['interesado'], 3 * $d, 'TYPE_INSTAGRAM', '¿Las clases son en línea o presenciales los sábados?', 'inbound', 40 * 60, 1],
            ['dc6', 'Pedro Luis Hernández', '+58 414 555 0106', 'pedro.hernandez@ejemplo.test', 'Facebook', 'du4', ['convenio', 'firmado'], 4 * $d, 'TYPE_FACEBOOK', 'Buenos días, ¿me pueden reenviar el enlace del convenio?', 'inbound', 55 * 60, 1],
            ['dc7', 'Yoselin Guerrero', '+58 416 555 0107', 'yoselin.guerrero@ejemplo.test', 'Referido', 'du2', ['referido'], 6 * $d, 'TYPE_EMAIL', 'Gracias por la información. La reviso con mi familia y les confirmo.', 'outbound', 2 * $h, 0],
            ['dc8', 'Rafael Ángel Sánchez', '+58 424 555 0108', 'rafael.sanchez@ejemplo.test', 'Sitio web', 'du3', ['interesado'], 9 * $d, 'TYPE_WHATSAPP', 'Muchas gracias, quedo pendiente de la fecha de inicio.', 'outbound', 2 * $d, 0],
        ];
        $out = [];
        foreach ($rows as [$id, $name, $phone, $email, $source, $to, $tags, $ago, $type, $body, $dir, $last, $unread])
            $out[] = ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => $email, 'source' => $source, 'assignedTo' => $to, 'tags' => $tags, 'dateAdded' => $now - $ago, 'type' => $type, 'body' => $body, 'dir' => $dir, 'last' => $now - $last, 'unread' => $unread];
        return $out;
    }

    private static function script(string $name): array
    {
        $map = [
            'dc1' => [['inbound', '¡Hola! Vi la publicación del diplomado y quiero más información.'], ['outbound', '¡Hola, Mariana! Con gusto. El diplomado dura 12 meses, con clases los sábados. La cuota mensual es de 150 USD.'], ['inbound', '¿Y la inscripción cuánto es?']],
            'dc2' => [['inbound', 'Buenas tardes, ¿tienen cupo para el diplomado de enero?'], ['outbound', '¡Hola, Luis! Sí, quedan cupos. ¿Te envío el plan de pagos?'], ['inbound', '¿El pago se puede hacer en dos partes? Me interesa empezar en enero.']],
            'dc3' => [['outbound', 'Carla, te dejo los datos para la inscripción.'], ['inbound', 'Gracias. Ya voy a hacer la transferencia.'], ['inbound', 'Listo, ya hice la transferencia. Les envío el comprobante.']],
            'dc4' => [['inbound', 'Buenas, quisiera el enlace del convenio.'], ['outbound', 'Perfecto, quedo atento al enlace del convenio.']],
            'dc5' => [['inbound', 'Hola, ¿cuándo empieza el próximo grupo?'], ['outbound', 'Hola, Andreína. El 18 de enero. ¿Quieres que te reserve un cupo?'], ['inbound', '¿Las clases son en línea o presenciales los sábados?']],
            'dc6' => [['outbound', 'Pedro, te enviamos el convenio por correo.'], ['inbound', 'Buenos días, ¿me pueden reenviar el enlace del convenio?']],
            'dc7' => [['outbound', 'Yoselin, adjunto la información del programa.'], ['inbound', 'Gracias por la información. La reviso con mi familia y les confirmo.']],
            'dc8' => [['inbound', '¿Hay descuento por pago de contado?'], ['outbound', 'Sí, 10 % hasta fin de mes.'], ['outbound', 'Muchas gracias, quedo pendiente de la fecha de inicio.']],
        ];
        return $map[$name] ?? [];
    }

    private static function state(): array
    {
        $raw = query('SELECT value FROM app_state WHERE name=?', [self::STATE])->fetchColumn();
        $d = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($d) ? $d + ['messages' => [], 'contacts' => [], 'opps' => [], 'tasks' => [], 'notes' => [], 'read' => []] : ['messages' => [], 'contacts' => [], 'opps' => [], 'tasks' => [], 'notes' => [], 'read' => []];
    }

    private static function save(array $s): void
    {
        query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)', [self::STATE, json_encode($s, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    /** Vuelve la demostración a como estaba al empezar: lo que se hizo en ella y lo que Ágora guardó de ella. */
    public static function reset(): void
    {
        query('DELETE FROM app_state WHERE name=?', [self::STATE]);
        foreach (['won_sales', 'conversation_message_cache', 'directory_cache', 'ghl_inbox_snapshots', 'conversation_index', 'conversation_reminders', 'workspace_tasks', 'message_send_attempts', 'opportunity_action_attempts', 'contact_action_attempts', 'contact_activity_cache', 'daily_metrics'] as $table) {
            try { query("DELETE FROM $table WHERE connection_id IN (SELECT id FROM ghl_connections WHERE is_demo=TRUE)"); }
            catch (PDOException $e) { /* esa tabla no existe en esta instalación */ }
        }
    }

    private static function opportunities(array $state): array
    {
        $now = time(); $d = 86400; $month = strtotime(gmdate('Y-m-01 00:00:00').' UTC');
        $stages = ['ds1', 'ds2', 'ds3', 'ds4'];
        $mk = fn($id, $name, $contact, $to, $value, $status, $stage, $createdAgo, $changedAgo) => ['id' => $id, 'name' => $name, 'monetaryValue' => $value, 'status' => $status, 'pipelineId' => 'dp1', 'pipelineStageId' => $stage, 'assignedTo' => $to,
            'contactId' => $contact, 'contact' => ['id' => $contact, 'name' => self::contactName($contact)], 'locationId' => self::$loc, 'createdAt' => gmdate('c', $now - $createdAgo), 'dateAdded' => gmdate('c', $now - $createdAgo),
            'lastStatusChangeAt' => gmdate('c', max($now - $changedAgo, $status === 'won' ? $month + 3600 : 0)), 'updatedAt' => gmdate('c', $now - $changedAgo)];
        $list = [
            $mk('do1', 'Diplomado · Mariana Pérez', 'dc1', 'du2', 1860, 'open', $stages[0], 2 * $d, 2 * $d),
            $mk('do2', 'Diplomado · Luis Alberto', 'dc2', 'du3', 1860, 'open', $stages[1], 5 * $d, 1 * $d),
            $mk('do3', 'Diplomado · Carla Sofía', 'dc3', 'du2', 1440, 'open', $stages[2], 6 * $d, 1 * $d),
            $mk('do4', 'Diplomado · Andreína', 'dc5', 'du3', 1860, 'open', $stages[0], 3 * $d, 3 * $d),
            $mk('do5', 'Diplomado · Pedro Luis', 'dc6', 'du4', 1320, 'open', $stages[2], 8 * $d, 2 * $d),
            $mk('do6', 'Diplomado · Rafael Ángel', 'dc8', 'du3', 1860, 'open', $stages[1], 9 * $d, 2 * $d),
            $mk('dw1', 'Curso intensivo · Camila Torres', 'dc3', 'du2', 480, 'won', $stages[3], 14 * $d, 4 * $d),
            $mk('dw2', 'Asesoría mensual · Raúl Prieto', 'dc7', 'du3', 1250.5, 'won', $stages[3], 20 * $d, 3 * $d),
            $mk('dw3', 'Pack inicial · Elena Cruz', 'dc4', 'du4', 300, 'won', $stages[3], 12 * $d, 2 * $d),
            $mk('dw4', 'Plan anual · Pedro Navarro', 'dc6', 'du2', 0, 'won', $stages[3], 10 * $d, 1 * $d),
            $mk('dw5', 'Curso intensivo · María Vidal', 'dc1', 'du3', 0, 'won', $stages[3], 8 * $d, 1 * $d),
        ];
        foreach ($list as &$o) if (isset($state['opps'][$o['id']])) $o = array_replace_recursive($o, $state['opps'][$o['id']]);
        unset($o);
        foreach ($state['opps'] as $id => $o) if (str_starts_with($id, 'dn')) $list[] = $o;
        return $list;
    }

    private static function contactName(string $id): string
    {
        foreach (self::people() as $p) if ($p['id'] === $id) return $p['name'];
        return 'Contacto';
    }

    private static function contactRecord(array $p, array $state): array
    {
        $o = $state['contacts'][$p['id']] ?? [];
        $parts = explode(' ', $o['name'] ?? $p['name'], 2);
        return array_replace($o, []) + [
            'id' => $p['id'], 'locationId' => self::$loc, 'name' => $p['name'], 'firstName' => $parts[0], 'lastName' => $parts[1] ?? '', 'email' => $p['email'], 'phone' => $p['phone'],
            'source' => $p['source'], 'assignedTo' => $p['assignedTo'], 'tags' => $p['tags'], 'dateAdded' => gmdate('c', $p['dateAdded']), 'companyName' => '', 'country' => 'VE', 'city' => 'Caracas',
            'customFields' => [['id' => 'df1', 'value' => 'Diplomado en Gestión Comercial'], ['id' => 'df2', 'value' => $p['source']]],
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // El transporte: responde como respondería la cuenta de origen
    // ---------------------------------------------------------------------------------------------
    public static function transport(string $location = self::LOCATION): Closure
    {
        self::$loc = $location;
        return function (string $path, string $version, string $method, ?array $payload): array {
            $url = parse_url($path); $route = $url['path'] ?? ''; parse_str($url['query'] ?? '', $q);
            $ok = static fn(array $body, int $status = 200): array => ['status' => $status, 'body' => json_encode($body, JSON_UNESCAPED_UNICODE)];
            $state = self::state(); $people = self::people();
            $byId = []; foreach ($people as $p) $byId[$p['id']] = $p;

            if (preg_match('~^/locations/([^/]+)$~', $route, $m)) return $ok(['location' => ['id' => $m[1], 'name' => 'Academia Horizonte (demostración)']]);
            if ($route === '/users/' || $route === '/users') return $ok(['users' => self::users()]);
            if (preg_match('~^/locations/[^/]+/tags$~', $route)) return $ok(['tags' => array_map(fn($t) => ['id' => 'tag-'.$t, 'name' => $t], ['interesado', 'diplomado', 'convenio', 'firmado', 'seguimiento', 'referido', 'frío'])]);
            if (preg_match('~^/locations/[^/]+/customFields$~', $route)) return $ok(['customFields' => [['id' => 'df1', 'name' => 'Programa de interés', 'fieldKey' => 'contact.programa', 'dataType' => 'TEXT', 'parentId' => 'dg1'], ['id' => 'df2', 'name' => 'Cómo nos conoció', 'fieldKey' => 'contact.como_nos_conocio', 'dataType' => 'TEXT', 'parentId' => 'dg1']]]);
            if (preg_match('~^/custom-fields/([^/]+)$~', $route, $m)) return $ok(['customField' => ['id' => $m[1], 'name' => 'Datos del programa', 'dataType' => 'TEXT']]);

            // ---- conversaciones
            if ($route === '/conversations/search') {
                $items = [];
                foreach ($people as $p) {
                    $conv = 'dv'.substr($p['id'], 2);
                    $extra = $state['messages'][$conv] ?? [];
                    $lastAt = $p['last']; $body = $p['body']; $dir = $p['dir']; $unread = $state['read'][$conv] ?? false ? 0 : $p['unread'];
                    if ($extra) { $l = end($extra); $lastAt = (int)$l['at']; $body = $l['body']; $dir = 'outbound'; $unread = 0; }
                    $items[] = ['id' => $conv, 'locationId' => self::$loc, 'contactId' => $p['id'], 'contactName' => $p['name'], 'fullName' => $p['name'], 'phone' => $p['phone'], 'email' => $p['email'],
                        'lastMessageType' => $p['type'], 'lastMessageBody' => $body, 'lastMessageDirection' => $dir, 'lastMessageDate' => $lastAt * 1000, 'unreadCount' => $unread, 'assignedTo' => ($state['contacts'][$p['id']]['assignedTo'] ?? $p['assignedTo']), 'type' => 'TYPE_PHONE'];
                }
                usort($items, fn($a, $b) => $b['lastMessageDate'] <=> $a['lastMessageDate']);
                if (($q['assignedTo'] ?? '') !== '') $items = array_values(array_filter($items, fn($c) => $c['assignedTo'] === $q['assignedTo']));
                if (($q['status'] ?? '') === 'unread') $items = array_values(array_filter($items, fn($c) => $c['unreadCount'] > 0));
                if (($q['lastMessageDirection'] ?? '') === 'inbound') $items = array_values(array_filter($items, fn($c) => $c['lastMessageDirection'] === 'inbound'));
                if (($q['startAfterDate'] ?? '') !== '') $items = array_values(array_filter($items, fn($c) => $c['lastMessageDate'] < (float)$q['startAfterDate']));
                $total = count($items);
                return $ok(['conversations' => array_slice($items, 0, max(1, (int)($q['limit'] ?? 20))), 'total' => $total]);
            }
            if ($route === '/conversations/' && $method === 'POST') return $ok(['conversation' => ['id' => 'dv'.substr((string)($payload['contactId'] ?? 'dc0'), 2), 'contactId' => $payload['contactId'] ?? '', 'locationId' => self::$loc]]);
            if (preg_match('~^/conversations/([^/]+)/messages$~', $route, $m)) {
                $conv = $m[1]; $p = $byId['dc'.substr($conv, 2)] ?? null;
                if (!$p) return $ok(['messages' => ['messages' => [], 'nextPage' => false, 'lastMessageId' => null]]);
                $messages = []; $script = self::script($p['id']); $n = count($script);
                foreach ($script as $i => [$dir, $text]) {
                    $at = $p['last'] - ($n - 1 - $i) * 420;
                    $messages[] = ['id' => $conv.'-m'.($i + 1), 'conversationId' => $conv, 'locationId' => self::$loc, 'direction' => $dir, 'body' => $text, 'dateAdded' => gmdate('c', $at), 'messageType' => $p['type'], 'status' => $dir === 'outbound' ? 'delivered' : 'received', 'contactId' => $p['id']];
                }
                foreach ($state['messages'][$conv] ?? [] as $i => $x) $messages[] = ['id' => $conv.'-s'.($i + 1), 'conversationId' => $conv, 'locationId' => self::$loc, 'direction' => 'outbound', 'body' => $x['body'], 'dateAdded' => gmdate('c', (int)$x['at']), 'messageType' => $x['type'] ?? $p['type'], 'status' => 'delivered', 'contactId' => $p['id']];
                $newest = array_reverse($messages);
                return $ok(['messages' => ['messages' => $newest, 'nextPage' => false, 'lastMessageId' => $newest[count($newest) - 1]['id'] ?? null]]);
            }
            if (preg_match('~^/conversations/([^/]+)$~', $route, $m) && $method === 'PUT') { $state['read'][$m[1]] = true; self::save($state); return $ok(['success' => true]); }
            if ($route === '/conversations/messages' && $method === 'POST') {
                $conv = 'dv'.substr((string)($payload['contactId'] ?? 'dc0'), 2);
                $state['messages'][$conv][] = ['body' => (string)($payload['message'] ?? $payload['html'] ?? ''), 'at' => time(), 'type' => $payload['type'] ?? 'WhatsApp'];
                self::save($state);
                return $ok(['messageId' => 'dsent'.bin2hex(random_bytes(6)), 'conversationId' => $conv, 'status' => 'delivered']);
            }

            // ---- contactos
            if ($route === '/contacts/search') {
                $range = null; $assigned = null;
                foreach ($payload['filters'] ?? [] as $f) {
                    if (($f['field'] ?? '') === 'dateAdded' && is_array($f['value'] ?? null)) $range = [strtotime((string)($f['value']['gt'] ?? $f['value']['gte'] ?? '1970-01-01')), strtotime((string)($f['value']['lt'] ?? $f['value']['lte'] ?? '2100-01-01'))];
                    if (($f['field'] ?? '') === 'assignedTo') $assigned = is_array($f['value']) ? ($f['value'][0] ?? null) : $f['value'];
                }
                $n = 0;
                foreach ($people as $p) { if ($range && ($p['dateAdded'] < $range[0] || $p['dateAdded'] >= $range[1])) continue; if ($assigned !== null && $p['assignedTo'] !== $assigned) continue; $n++; }
                return $ok(['total' => $n, 'contacts' => []]);
            }
            if (($route === '/contacts/' || $route === '/contacts') && $method === 'GET') {
                $term = strtolower((string)($q['query'] ?? ''));
                $list = [];
                foreach ($people as $p) { $c = self::contactRecord($p, $state); if ($term !== '' && !str_contains(strtolower($c['name'].' '.$c['email'].' '.$c['phone']), $term)) continue; $c['dateAdded'] = gmdate('c', $p['dateAdded']); $list[] = $c; }
                return $ok(['contacts' => $list, 'meta' => ['total' => count($list)]]);
            }
            if ($route === '/contacts/upsert') return $ok(['contact' => ['id' => 'dc1']]);
            if ($route === '/contacts/search/duplicate') return $ok(['contact' => null]);
            if (preg_match('~^/contacts/([^/]+)/tags$~', $route, $m)) {
                $tags = $state['contacts'][$m[1]]['tags'] ?? ($byId[$m[1]]['tags'] ?? []);
                $given = array_map('strval', $payload['tags'] ?? []);
                $state['contacts'][$m[1]]['tags'] = array_values($method === 'DELETE' ? array_diff($tags, $given) : array_unique(array_merge($tags, $given)));
                self::save($state); return $ok(['tags' => $state['contacts'][$m[1]]['tags']]);
            }
            if (preg_match('~^/contacts/([^/]+)/tasks/([^/]+)/completed$~', $route, $m)) { $state['tasks'][$m[2]] = ['completed' => (bool)($payload['completed'] ?? true)]; self::save($state); return $ok(['task' => ['id' => $m[2], 'completed' => (bool)($payload['completed'] ?? true)]]); }
            if (preg_match('~^/contacts/([^/]+)/tasks$~', $route, $m)) {
                if ($method === 'POST') { $id = 'dt'.bin2hex(random_bytes(4)); $state['tasks'][$id] = ['id' => $id, 'contactId' => $m[1]] + $payload + ['completed' => false]; self::save($state); return $ok(['task' => ['id' => $id] + $payload]); }
                $out = []; foreach ($state['tasks'] as $id => $t) if (($t['contactId'] ?? null) === $m[1]) $out[] = ['id' => $id, 'title' => $t['title'] ?? 'Tarea', 'body' => $t['body'] ?? '', 'dueDate' => $t['dueDate'] ?? gmdate('c', time() + 86400), 'completed' => (bool)($t['completed'] ?? false), 'assignedTo' => $t['assignedTo'] ?? null, 'contactId' => $m[1]];
                return $ok(['tasks' => $out]);
            }
            if (preg_match('~^/contacts/([^/]+)/notes$~', $route, $m)) {
                if ($method === 'POST') { $id = 'dn'.bin2hex(random_bytes(4)); $state['notes'][$id] = ['id' => $id, 'contactId' => $m[1], 'body' => (string)($payload['body'] ?? ''), 'userId' => $payload['userId'] ?? null, 'dateAdded' => gmdate('c')]; self::save($state); return $ok(['note' => $state['notes'][$id]]); }
                $out = []; foreach ($state['notes'] as $n) if (($n['contactId'] ?? null) === $m[1]) $out[] = $n;
                return $ok(['notes' => $out]);
            }
            if (preg_match('~^/contacts/([^/]+)/appointments$~', $route)) return $ok(['events' => []]);
            if (preg_match('~^/contacts/([^/]+)$~', $route, $m)) {
                $p = $byId[$m[1]] ?? null;
                if (!$p) return $ok(['message' => 'No encontrado'], 404);
                if ($method === 'PUT') { $state['contacts'][$m[1]] = array_replace($state['contacts'][$m[1]] ?? [], array_diff_key($payload ?? [], ['id' => 1, 'locationId' => 1])); self::save($state); }
                return $ok(['contact' => self::contactRecord($p, self::state())]);
            }

            // ---- oportunidades
            if ($route === '/opportunities/pipelines') return $ok(['pipelines' => [['id' => 'dp1', 'name' => 'Diplomado 2026', 'locationId' => self::$loc, 'stages' => [['id' => 'ds1', 'name' => 'Nuevo interesado'], ['id' => 'ds2', 'name' => 'En conversación'], ['id' => 'ds3', 'name' => 'Convenio enviado'], ['id' => 'ds4', 'name' => 'Cierre']]]]]);
            if ($route === '/opportunities/search') {
                $list = self::opportunities($state);
                $status = (string)($q['status'] ?? 'all');
                if ($status !== 'all') $list = array_values(array_filter($list, fn($o) => $o['status'] === $status));
                if (($q['contact_id'] ?? '') !== '') $list = array_values(array_filter($list, fn($o) => $o['contactId'] === $q['contact_id']));
                if (($q['pipeline_id'] ?? '') !== '') $list = array_values(array_filter($list, fn($o) => $o['pipelineId'] === $q['pipeline_id']));
                $limit = max(1, (int)($q['limit'] ?? 100)); $page = max(1, (int)($q['page'] ?? 1));
                return $ok(['opportunities' => array_slice($list, ($page - 1) * $limit, $limit), 'meta' => ['total' => count($list)]]);
            }
            if ($route === '/opportunities/' && $method === 'POST') {
                $id = 'dn'.bin2hex(random_bytes(4));
                $o = ['id' => $id, 'name' => (string)($payload['name'] ?? 'Nueva oportunidad'), 'monetaryValue' => (float)($payload['monetaryValue'] ?? 0), 'status' => 'open', 'pipelineId' => $payload['pipelineId'] ?? 'dp1', 'pipelineStageId' => $payload['pipelineStageId'] ?? 'ds1', 'assignedTo' => $payload['assignedTo'] ?? null,
                    'contactId' => $payload['contactId'] ?? '', 'contact' => ['id' => $payload['contactId'] ?? '', 'name' => self::contactName((string)($payload['contactId'] ?? ''))], 'locationId' => self::$loc, 'createdAt' => gmdate('c'), 'dateAdded' => gmdate('c'), 'lastStatusChangeAt' => gmdate('c')];
                $state['opps'][$id] = $o; self::save($state); return $ok(['opportunity' => $o]);
            }
            if (preg_match('~^/opportunities/([^/]+)/status$~', $route, $m)) {
                $st = (string)($payload['status'] ?? 'open');
                $state['opps'][$m[1]] = array_replace($state['opps'][$m[1]] ?? [], ['status' => $st, 'lastStatusChangeAt' => gmdate('c')] + (isset($payload['monetaryValue']) ? ['monetaryValue' => (float)$payload['monetaryValue']] : []));
                self::save($state); return $ok(['opportunity' => ['id' => $m[1], 'status' => $st]]);
            }
            if (preg_match('~^/opportunities/([^/]+)$~', $route, $m)) {
                if ($method === 'PUT') { $state['opps'][$m[1]] = array_replace($state['opps'][$m[1]] ?? [], array_diff_key($payload ?? [], ['id' => 1, 'locationId' => 1])); self::save($state); }
                foreach (self::opportunities(self::state()) as $o) if ($o['id'] === $m[1]) return $ok(['opportunity' => $o]);
                return $ok(['message' => 'No encontrada'], 404);
            }

            // ---- anuncios y plantillas
            if (str_starts_with($route, '/ad-publishing/')) {
                $from = (string)($q['startDate'] ?? ''); $to = (string)($q['endDate'] ?? '');
                $days = max(1, (int)((strtotime($to ?: 'today') - strtotime($from ?: 'today')) / 86400) + 1);
                if (($q['type'] ?? '') !== 'INTEGRATION') return $ok(['rows' => []]);
                return $ok(['rows' => [['spend' => round(13.8 * $days, 2), 'impressions' => 900 * $days, 'clicks' => 22 * $days, 'conversions' => max(0, (int)round(0.7 * $days))]]]);
            }
            if (str_contains($route, '/templates')) return $ok(['templates' => [], 'total' => 0]);
            return $ok(['message' => 'No disponible en la demostración'], 404);
        };
    }
}
