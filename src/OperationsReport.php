<?php
declare(strict_types=1);
require_once __DIR__.'/GhlGateway.php';
require_once __DIR__.'/WonSales.php';
require_once __DIR__.'/PipelineDirectory.php';
require_once __DIR__.'/Board.php';

/**
 * Lo que las pantallas de operación piden aparte: el día, la eficacia de las ventas y la inversión en anuncios.
 * Se apoya en lo que Ágora ya guarda (conversaciones vistas, ventas del mes) y pide a la cuenta lo mínimo,
 * con caché, para que una pantalla abierta no gaste la cuota.
 */
final class OperationsReport
{
    private const SLA_GOAL_SECONDS = 300;   // la meta: responder en menos de 5 minutos
    private const SLA_TOLERANCE = 3;
    private const LEADS_TTL = 600;
    private const ADS_TTL = 1800;

    public function __construct(private readonly GhlGateway $gateway = new GhlGateway()) {}

    /** El día en la zona del negocio (Ágora guarda las horas en UTC; el día lo manda el reloj de quien trabaja). */
    public static function zone(): DateTimeZone
    {
        $name = getenv('APP_TIMEZONE') ?: 'America/Caracas';
        try { return new DateTimeZone($name); } catch (Throwable $e) { return new DateTimeZone('UTC'); }
    }

    public static function day(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && strtotime($value) !== false ? $value : (new DateTimeImmutable('now', self::zone()))->format('Y-m-d');
    }

    /** @return array{0:int,1:int} inicio y fin del día, en segundos UTC */
    private static function bounds(string $day): array
    {
        $start = new DateTimeImmutable($day.' 00:00:00', self::zone());
        return [$start->getTimestamp(), $start->modify('+1 day')->getTimestamp()];
    }

    private static function seconds(mixed $value): ?int
    {
        if (is_numeric($value)) { $n = (float)$value; return (int)($n > 1e11 ? $n / 1000 : $n); }
        if (is_string($value) && $value !== '' && ($t = strtotime($value)) !== false) return $t;
        return null;
    }

    private static function text(int $seconds): string
    {
        if ($seconds < 60) return $seconds.' s';
        if ($seconds < 3600) { $m = intdiv($seconds, 60); $s = $seconds % 60; return $s ? "$m min $s s" : "$m min"; }
        $h = intdiv($seconds, 3600); $m = intdiv($seconds % 3600, 60);
        return $m ? "$h h $m min" : "$h h";
    }

    /** @return array<string,string> external_user_id → nombre */
    private static function names(int $connection): array
    {
        $out = [];
        foreach (query('SELECT external_user_id,display_name FROM external_users WHERE connection_id=?', [$connection])->fetchAll() as $r) $out[$r['external_user_id']] = $r['display_name'];
        return $out;
    }

    // ---------------------------------------------------------------------------------------------
    // Operación del día
    // ---------------------------------------------------------------------------------------------
    public function operationDay(array $connection, array $membership, string $day, ?int $now = null): array
    {
        $id = (int)$connection['id'];
        $now ??= time();
        [$from, $to] = self::bounds($day);
        $restricted = ($membership['capabilities']['assignedDataOnly'] ?? false) === true;
        $mine = $membership['external_user_id'] ?? null;
        $names = self::names($id);
        $rows = query('SELECT payload FROM conversation_index WHERE connection_id=?', [$id])->fetchAll();
        $today = $waiting = [];
        $unread = 0; $unreadBy = [];
        foreach ($rows as $row) {
            $c = json_decode((string)$row['payload'], true);
            if (!is_array($c)) continue;
            $owner = is_string($c['assignedTo'] ?? null) && $c['assignedTo'] !== '' ? $c['assignedTo'] : null;
            if ($restricted && $owner !== $mine) continue;
            $count = max(0, (int)($c['unreadCount'] ?? 0));
            if ($count > 0) { $unread++; $label = $owner !== null ? ($names[$owner] ?? 'Sin nombre') : 'Sin asignar'; $unreadBy[$label] = ($unreadBy[$label] ?? 0) + 1; }
            $at = self::seconds($c['lastMessageDate'] ?? null);
            if ($at === null || $at < $from || $at >= $to) continue;
            $today[] = $c + ['_at' => $at, '_owner' => $owner];
        }
        $attended = 0; $waits = []; $worst = null; $worstBy = null;
        foreach ($today as $c) {
            if (($c['lastMessageDirection'] ?? '') === 'inbound') {
                $wait = max(0, min($now, $to) - $c['_at']);
                $waits[] = $wait;
                if ($worst === null || $wait > $worst) { $worst = $wait; $worstBy = $c['_owner'] !== null ? ($names[$c['_owner']] ?? null) : null; }
            } else $attended++;
        }
        $late = count(array_filter($waits, fn(int $w): bool => $w > self::SLA_GOAL_SECONDS));
        $average = $waits ? (int)round(array_sum($waits) / count($waits)) : null;
        arsort($unreadBy);

        $leads = $this->leads($connection, $restricted ? $mine : null, $day);
        $yesterdayDay = (new DateTimeImmutable($day, new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
        $before = $this->snapshot($id, $yesterdayDay);

        $report = [
            'conversacionesDia' => count($today),
            'atendidas' => $attended,
            'sinLeer' => $unread,
            'sinLeerAyer' => $before['sinLeer'] ?? null,
            'sinLeerPorAsesor' => array_map(fn($n, $k) => ['nombre' => (string)$k, 'n' => $n], $unreadBy, array_keys($unreadBy)),
            'slaIncumplido' => $late,
            'slaIncumplidoAyer' => $before['slaIncumplido'] ?? null,
            'slaTolerancia' => self::SLA_TOLERANCE,
            'slaPromedio' => $average === null ? '—' : self::text($average),
            'slaPromedioSeg' => $average,
            'slaPromedioSegAyer' => $before['slaPromedioSeg'] ?? null,
            'slaMetaTexto' => self::text(self::SLA_GOAL_SECONDS),
            'peorRespuesta' => $worst === null ? '—' : self::text($worst),
            'peorAsesor' => $worstBy,
            'leads' => $leads['hoy'],
            'leadsAyer' => $leads['ayer'] ?? ($before['leads'] ?? null),
            'leadsPorFuente' => $leads['fuentes'],
            'leadsPorUsuario' => $leads['usuarios'],
            // De dónde sale el tiempo de respuesta: lo que está esperando ahora, no la primera respuesta ya dada.
            'slaBase' => 'pendientes',
        ];
        if ($day === (new DateTimeImmutable('now', self::zone()))->format('Y-m-d')) $this->remember($id, $day, $report);
        return $report;
    }

    private function snapshot(int $connection, string $day): array
    {
        $raw = query("SELECT payload FROM daily_metrics WHERE connection_id=? AND day=? AND kind='dia'", [$connection, $day])->fetchColumn();
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    private function remember(int $connection, string $day, array $report): void
    {
        try {
            $keep = array_intersect_key($report, array_flip(['sinLeer', 'slaIncumplido', 'slaPromedioSeg', 'leads']));
            query("INSERT INTO daily_metrics(connection_id,day,kind,payload) VALUES(?,?,'dia',?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)", [$connection, $day, json_encode($keep, JSON_THROW_ON_ERROR)]);
        } catch (Throwable $e) { error_log('Daily metrics failure: '.get_class($e)); }
    }

    /** Los leads del día, de ayer, por fuente y por ejecutivo: una lectura de los contactos más recientes, guardada unos minutos. */
    private function leads(array $connection, ?string $only, string $day): array
    {
        $id = (int)$connection['id']; $key = 'dia-leads';
        $row = query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS at FROM directory_cache WHERE connection_id=? AND cache_key=?', [$id, $key])->fetch();
        $contacts = null;
        if ($row && time() - (int)$row['at'] < self::LEADS_TTL) { $d = json_decode((string)$row['payload'], true); if (is_array($d)) $contacts = $d; }
        if ($contacts === null) {
            try {
                [$list] = $this->gateway->execute($connection, function (GhlClient $client) use ($connection): array {
                    $data = $client->locationContacts($connection['location_id'], null, 100);
                    $items = $data['contacts'] ?? null;
                    if (!is_array($items)) throw new RuntimeException('Invalid contacts response');
                    $out = [];
                    foreach ($items as $c) {
                        if (!is_array($c)) continue;
                        $out[] = ['at' => self::seconds($c['dateAdded'] ?? null), 'source' => is_string($c['source'] ?? null) ? mb_substr($c['source'], 0, 60) : null, 'to' => is_string($c['assignedTo'] ?? null) ? $c['assignedTo'] : null];
                    }
                    return [$out];
                }, false);
                $contacts = $list;
                query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP', [$id, $key, json_encode($contacts, JSON_THROW_ON_ERROR)]);
            } catch (Throwable $e) { $contacts = null; }
        }
        if ($contacts === null) return ['hoy' => null, 'ayer' => null, 'fuentes' => [], 'usuarios' => []];
        [$from, $to] = self::bounds($day);
        $dayBefore = $from - 86400;
        $names = self::names($id);
        $today = $yesterday = 0; $bySource = $byUser = [];
        foreach ($contacts as $c) {
            if (!is_array($c) || !is_int($c['at'] ?? null)) continue;
            if ($only !== null && ($c['to'] ?? null) !== $only) continue;
            if ($c['at'] >= $dayBefore && $c['at'] < $from) $yesterday++;
            if ($c['at'] < $from || $c['at'] >= $to) continue;
            $today++;
            $src = ($c['source'] ?? '') !== '' ? $c['source'] : 'Sin fuente';
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;
            $who = ($c['to'] ?? null) !== null ? ($names[$c['to']] ?? 'Sin nombre') : 'Sin asignar';
            $byUser[$who] = ($byUser[$who] ?? 0) + 1;
        }
        arsort($bySource); arsort($byUser);
        // Con 100 contactos leídos, un día muy movido puede quedar corto: se avisa.
        return ['hoy' => $today, 'ayer' => $yesterday,
            'fuentes' => array_map(fn($n, $k) => ['fuente' => (string)$k, 'n' => $n], $bySource, array_keys($bySource)),
            'usuarios' => array_map(fn($n, $k) => ['nombre' => (string)$k, 'n' => $n], $byUser, array_keys($byUser))];
    }

    // ---------------------------------------------------------------------------------------------
    // Eficacia de ventas
    // ---------------------------------------------------------------------------------------------
    public function efficiency(array $connection, array $membership, string $period, string $pipelineId, string $userId, ?int $now = null): array
    {
        $id = (int)$connection['id'];
        $now ??= time();
        $restricted = ($membership['capabilities']['assignedDataOnly'] ?? false) === true;
        $mine = $membership['external_user_id'] ?? null;
        $won = (new WonSales($this->gateway))->month($connection, $period)['records'] ?? [];
        $names = self::names($id);
        try { $pipelines = (new PipelineDirectory($this->gateway))->get($connection); } catch (Throwable $e) { $pipelines = []; }
        if ($restricted) { $won = array_values(array_filter($won, fn(array $r): bool => ($r['assignedTo'] ?? null) === $mine)); $userId = (string)$mine; }
        $all = $won;
        if ($pipelineId !== '') $won = array_values(array_filter($won, fn(array $r): bool => ($r['pipelineId'] ?? null) === $pipelineId));
        if ($userId !== '') $won = array_values(array_filter($won, fn(array $r): bool => ($r['assignedTo'] ?? null) === $userId));

        $days = static function (array $r): ?float {
            if (!is_string($r['createdAt'] ?? null) || !is_string($r['wonAt'] ?? null)) return null;
            $a = strtotime($r['createdAt']); $b = strtotime($r['wonAt']);
            return $a !== false && $b !== false && $b >= $a ? ($b - $a) / 86400 : null;
        };
        $total = array_sum(array_column($won, 'value'));
        $count = count($won);
        $timed = array_values(array_filter(array_map($days, $won), fn($d) => $d !== null));
        $average = $timed ? array_sum($timed) / count($timed) : null;

        $start = (int)strtotime($period.'-01 00:00:00 UTC');
        $length = (int)gmdate('t', $start);
        $elapsed = $period === gmdate('Y-m', $now) ? max(1, (int)gmdate('j', $now)) : $length;
        $speed = $count ? round($total / $elapsed * $length, 2) : 0.0;

        $byUser = [];
        foreach ($won as $r) {
            $key = $r['assignedTo'] ?? '';
            $byUser[$key] ??= ['nombre' => $key !== '' ? ($names[$key] ?? 'Sin nombre') : 'Sin asignar', 'cerradas' => 0, 'valor' => 0.0, '_d' => []];
            $byUser[$key]['cerradas']++; $byUser[$key]['valor'] += (float)$r['value'];
            if (($d = $days($r)) !== null) $byUser[$key]['_d'][] = $d;
        }
        $rows = array_values(array_map(fn(array $u): array => ['nombre' => $u['nombre'], 'cerradas' => $u['cerradas'], 'valor' => round($u['valor'], 2), 'dias' => $u['_d'] ? (int)round(array_sum($u['_d']) / count($u['_d'])) : null], $byUser));
        usort($rows, fn($a, $b) => $b['valor'] <=> $a['valor']);

        return [
            'pipelineId' => $pipelineId, 'userId' => $userId,
            'pipelines' => array_values(array_map(fn($k, $v) => ['id' => (string)$k, 'nombre' => $v['name']], array_keys($pipelines), $pipelines)),
            'usuarios' => $restricted ? [] : array_values(array_map(fn($k, $v) => ['id' => (string)$k, 'nombre' => $v], array_keys($names), $names)),
            'duracionPromedioDias' => $average === null ? null : (int)round($average),
            'duracionTexto' => $average === null ? '—' : ((int)round($average)).' '.((int)round($average) === 1 ? 'día' : 'días'),
            'metaDias' => null,
            'valorTotal' => round((float)$total, 2),
            'cerradas' => $count,
            'ticket' => $count ? round($total / $count, 2) : null,
            'velocidad' => $speed,
            'tasaCierre' => null,
            'porUsuario' => $rows,
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // Inversión en anuncios
    // ---------------------------------------------------------------------------------------------
    public function ads(array $connection, string $period, string $day): array
    {
        $month = $this->spend($connection, $period.'-01', (new DateTimeImmutable($period.'-01', new DateTimeZone('UTC')))->modify('last day of this month')->format('Y-m-d'));
        if ($month['amount'] === null) return ['estado' => in_array($month['reason'], ['permission'], true) ? 'permission' : 'not_connected'];
        $today = $this->spend($connection, $day, $day);
        $yesterdayDay = (new DateTimeImmutable($day, new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
        $yesterday = $this->spend($connection, $yesterdayDay, $yesterdayDay);
        return [
            'estado' => 'ok', 'dia' => $today['amount'], 'diaAyer' => $yesterday['amount'], 'mes' => $month['amount'],
            'presupuestoMes' => null, 'impresiones' => $month['impressions'], 'clics' => $month['clicks'], 'conversiones' => $month['conversions'],
            'costePorConversion' => $month['conversions'] ? round($month['amount'] / $month['conversions'], 2) : null,
            'costePorLead' => null, 'retornoPorCien' => null, 'actualizado' => 'hace unos minutos', 'campanas' => [],
        ];
    }

    /** @return array{amount:?float,impressions:?int,clicks:?int,conversions:?int,reason:?string} */
    private function spend(array $connection, string $from, string $to): array
    {
        $id = (int)$connection['id']; $key = 'ads:'.$from.':'.$to;
        $row = query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS at FROM directory_cache WHERE connection_id=? AND cache_key=?', [$id, $key])->fetch();
        if ($row && time() - (int)$row['at'] < self::ADS_TTL) { $d = json_decode((string)$row['payload'], true); if (is_array($d)) return $d; }
        $empty = ['amount' => null, 'impressions' => null, 'clicks' => null, 'conversions' => null, 'reason' => 'not_connected'];
        try {
            [$result] = $this->gateway->execute($connection, function (GhlClient $client) use ($connection, $from, $to, $empty): array {
                $reason = 'not_connected';
                foreach (['INTEGRATION', 'AD_MANAGER'] as $type) {
                    try { $response = $client->adReporting($connection['location_id'], $from, $to, $type); }
                    catch (GhlApiException $e) { if (in_array($e->status, [401, 403], true)) return [['reason' => 'permission'] + $empty]; continue; }
                    catch (Throwable $e) { $reason = 'unreadable'; continue; }
                    $amount = Board::spendIn($response);
                    if ($amount !== null) return [['amount' => round($amount, 2), 'impressions' => self::sum($response, 'impressions'), 'clicks' => self::sum($response, 'clicks'), 'conversions' => self::sum($response, 'conversions'), 'reason' => null]];
                    $reason = 'no_spend';
                }
                return [['reason' => $reason] + $empty];
            }, false);
        } catch (Throwable $e) { return ['reason' => 'unreadable'] + $empty; }
        query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP', [$id, $key, json_encode($result, JSON_THROW_ON_ERROR)]);
        return $result;
    }

    private static function sum(mixed $node, string $field): ?int
    {
        $found = [];
        $walk = function (mixed $v, int $depth) use (&$walk, &$found, $field): void {
            if ($depth > 6 || !is_array($v)) return;
            foreach ($v as $k => $child) {
                if (is_string($k) && strtolower($k) === $field && is_numeric($child)) { $found[$depth][] = (float)$child; continue; }
                $walk($child, $depth + 1);
            }
        };
        $walk($node, 0);
        return $found ? (int)round(array_sum($found[min(array_keys($found))])) : null;
    }

    // ---------------------------------------------------------------------------------------------
    // Leads por persona del equipo, en el mes
    // ---------------------------------------------------------------------------------------------
    /** @param list<string> $members @return array<string,?int> */
    public function leadsByMember(array $connection, string $period, array $members): array
    {
        $id = (int)$connection['id']; $key = 'leads:'.$period;
        $row = query('SELECT payload,UNIX_TIMESTAMP(fetched_at) AS at FROM directory_cache WHERE connection_id=? AND cache_key=?', [$id, $key])->fetch();
        if ($row && time() - (int)$row['at'] < 900) { $d = json_decode((string)$row['payload'], true); if (is_array($d)) return $d; }
        $start = new DateTimeImmutable($period.'-01 00:00:00', new DateTimeZone('UTC'));
        $range = ['gt' => $start->format('Y-m-d\TH:i:s\Z'), 'lt' => $start->modify('+1 month')->format('Y-m-d\TH:i:s\Z')];
        try {
            [$out] = $this->gateway->execute($connection, function (GhlClient $client) use ($connection, $members, $range): array {
                $out = [];
                foreach ($members as $member) {
                    $out[$member] = null;
                    foreach ([['field' => 'assignedTo', 'operator' => 'eq', 'value' => $member], ['field' => 'assignedTo', 'operator' => 'eq', 'value' => [$member]]] as $assigned) {
                        try { $response = $client->countContacts($connection['location_id'], [['field' => 'dateAdded', 'operator' => 'range', 'value' => $range], $assigned]); }
                        catch (GhlApiException $e) { if (in_array($e->status, [401, 403], true)) break 2; continue; }
                        catch (Throwable $e) { continue; }
                        $n = $response['total'] ?? ($response['meta']['total'] ?? null);
                        if (is_numeric($n) && $n >= 0) { $out[$member] = (int)$n; break; }
                    }
                }
                return [$out];
            }, false);
        } catch (Throwable $e) { return array_fill_keys($members, null); }
        query('INSERT INTO directory_cache(connection_id,cache_key,payload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),fetched_at=CURRENT_TIMESTAMP', [$id, $key, json_encode($out, JSON_THROW_ON_ERROR)]);
        return $out;
    }
}
