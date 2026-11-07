<?php
declare(strict_types=1);
require_once __DIR__.'/TokenVault.php';

/**
 * «Consumos»: lo que cada subcuenta gasta en la cuenta de origen y su plan activo, leído con el token de AGENCIA.
 * El token lo escribe el propietario en la pestaña, se guarda cifrado y no vuelve nunca al navegador (sólo sus
 * últimos cuatro caracteres). Todo lo que se muestra para depurar sale con el token tachado.
 */
final class AgencyWallet
{
    private const BASE = 'https://services.leadconnectorhq.com';
    private const SETTINGS = 'agency:settings';
    private const TOKEN = 'agency:token';
    public const PAGE = 1000;
    public const MAX_PAGES = 20;

    /** @param ?Closure(string,string,array,?string):array $transport (método, url, cabeceras, cuerpo) → [status,headers,body] */
    public function __construct(private readonly int $tenant, private readonly ?Closure $transport = null) {}

    // ---- ajustes -----------------------------------------------------------------------------
    private static function load(string $name): ?string
    {
        $v = query('SELECT value FROM app_state WHERE name=?', [$name])->fetchColumn();
        $d = is_string($v) ? json_decode($v, true) : null;
        return is_string($d) ? $d : null;   // app_state guarda JSON: aquí, un texto
    }

    private static function store(string $name, string $value): void
    {
        query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)', [$name, json_encode($value, JSON_THROW_ON_ERROR)]);
    }

    private function key(string $base): string { return $base.':'.$this->tenant; }

    /** Lo que la pantalla puede saber: si hay token y cuáles son sus últimos cuatro caracteres, nunca el token. */
    public function settings(): array
    {
        $raw = self::load($this->key(self::SETTINGS));
        $s = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        return ['companyId' => (string)($s['companyId'] ?? ''), 'tokenSaved' => self::load($this->key(self::TOKEN)) !== null, 'tokenHint' => (string)($s['tokenHint'] ?? ''), 'updatedAt' => $s['updatedAt'] ?? null];
    }

    public function save(?string $companyId, ?string $token): array
    {
        $current = $this->settings();
        $company = $companyId === null ? $current['companyId'] : trim($companyId);
        if ($company !== '' && !preg_match('/^[A-Za-z0-9_-]{6,80}$/D', $company)) throw new InvalidArgumentException('El Company ID no es válido (letras, números, guion y guion bajo).');
        $hint = $current['tokenHint'];
        if ($token !== null && trim($token) !== '') {
            $token = trim($token);
            if (!preg_match('/^[\x21-\x7e]{20,2000}$/D', $token)) throw new InvalidArgumentException('El token no tiene el formato esperado (sin espacios y de al menos 20 caracteres).');
            self::store($this->key(self::TOKEN), TokenVault::fromEnvironment()->seal($token, $this->tenant, 'agency'));
            $hint = substr($token, -4);
        }
        self::store($this->key(self::SETTINGS), json_encode(['companyId' => $company, 'tokenHint' => $hint, 'updatedAt' => gmdate('c')], JSON_THROW_ON_ERROR));
        return $this->settings();
    }

    public function forget(): array
    {
        query('DELETE FROM app_state WHERE name=?', [$this->key(self::TOKEN)]);
        self::store($this->key(self::SETTINGS), json_encode(['companyId' => $this->settings()['companyId'], 'tokenHint' => '', 'updatedAt' => gmdate('c')], JSON_THROW_ON_ERROR));
        return $this->settings();
    }

    private function token(): string
    {
        $cipher = self::load($this->key(self::TOKEN));
        if ($cipher === null) throw new RuntimeException('Falta el token de agencia.');
        return TokenVault::fromEnvironment()->open($cipher, $this->tenant, 'agency');
    }

    // ---- llamadas ----------------------------------------------------------------------------
    /** El token tachado en cualquier texto que vaya a la pantalla. */
    public function redact(string $text): string
    {
        try { $t = $this->token(); } catch (Throwable $e) { return $text; }
        return $t === '' ? $text : str_replace($t, '••••'.substr($t, -4), $text);
    }

    /** @return array{status:int,ms:int,headers:array<string,string>,body:string,url:string,method:string,version:string,error:?string} */
    public function call(string $method, string $path, string $version, ?array $json = null, array $query = []): array
    {
        $url = self::BASE.$path.($query ? '?'.http_build_query($query) : '');
        $headers = ['Authorization: Bearer '.$this->token(), 'Version: '.$version, 'Accept: application/json'];
        $body = null;
        if ($json !== null) { $headers[] = 'Content-Type: application/json'; $body = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); }
        $t0 = microtime(true);
        if ($this->transport) { $r = ($this->transport)($method, $url, $headers, $body); }
        else {
            $ch = curl_init($url); $seen = [];
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$seen) { $p = explode(':', $line, 2); if (count($p) === 2) $seen[strtolower(trim($p[0]))] = trim($p[1]); return strlen($line); }]);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $raw = curl_exec($ch); $err = $raw === false ? curl_error($ch) : null; $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
            $r = ['status' => $status, 'headers' => $seen, 'body' => $raw === false ? '' : (string)$raw, 'error' => $err];
        }
        $keep = [];
        foreach (($r['headers'] ?? []) as $k => $v) if (preg_match('/^(x-ratelimit|retry-after|content-type|x-request-id)/i', (string)$k)) $keep[strtolower((string)$k)] = (string)$v;
        return ['status' => (int)$r['status'], 'ms' => (int)round((microtime(true) - $t0) * 1000), 'headers' => $keep, 'body' => (string)$r['body'], 'url' => preg_replace('~(companies/)[^/]+~', '$1{companyId}', $url), 'method' => $method, 'version' => $version, 'error' => $r['error'] ?? null];
    }

    private function company(): string
    {
        $c = $this->settings()['companyId'];
        if ($c === '') throw new RuntimeException('Falta el Company ID.');
        return $c;
    }

    /** Los límites del mes pedido, en UTC. */
    public static function month(mixed $value): array
    {
        $m = is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value) ? $value : gmdate('Y-m');
        $start = new DateTimeImmutable($m.'-01 00:00:00', new DateTimeZone('UTC'));
        return [$m, $start->format('Y-m-d\T00:00:00.000\Z'), $start->modify('last day of this month')->format('Y-m-d\T23:59:59.999\Z')];
    }

    /** Una sola petición del listado de transacciones, por POST (cuerpo JSON) o por GET (parámetros). */
    public function transactions(string $method, string $month, ?string $location, int $skip = 0, int $limit = 1000, ?string $version = null): array
    {
        [, $from, $to] = self::month($month);
        $path = '/saas/companies/'.rawurlencode($this->company()).'/wallet-transactions';
        if ($method === 'POST') {
            $filters = ['settlementTime' => ['from' => $from, 'to' => $to]];
            if ($location) $filters['locationId'] = $location;
            return $this->call('POST', $path, $version ?? 'v3', ['skip' => $skip, 'limit' => $limit, 'timezone' => 'UTC', 'filters' => $filters]);
        }
        $q = ['skip' => $skip, 'limit' => $limit, 'startDate' => $from, 'endDate' => $to];
        if ($location) $q['locationId'] = $location;
        return $this->call('GET', $path, $version ?? '2021-07-28', null, $q);
    }

    public function balance(string $location, ?string $version = null): array
    {
        return $this->call('GET', '/saas-api/public-api/companies/'.rawurlencode($this->company()).'/locations/'.rawurlencode($location).'/wallet-balance', $version ?? 'v3');
    }

    public function subscription(string $location, ?string $version = null): array
    {
        return $this->call('GET', '/saas-api/public-api/get-saas-subscription/'.rawurlencode($location), $version ?? 'v3', null, ['companyId' => $this->company()]);
    }

    public function plan(string $planId, ?string $version = null): array
    {
        return $this->call('GET', '/saas-api/public-api/saas-plan/'.rawurlencode($planId), $version ?? 'v3', null, ['companyId' => $this->company()]);
    }

    // ---- lectura y suma ----------------------------------------------------------------------
    /** @return list<array<string,mixed>> las transacciones de una respuesta, sea cual sea la caja que las traiga */
    public static function rows(array $data): array
    {
        foreach (['transactions', 'data', 'items', 'results', 'walletTransactions'] as $k)
            if (isset($data[$k]) && is_array($data[$k]) && array_is_list($data[$k])) return array_values(array_filter($data[$k], 'is_array'));
        foreach (['data', 'result'] as $k) if (isset($data[$k]) && is_array($data[$k])) { $inner = self::rows($data[$k]); if ($inner) return $inner; }
        return array_is_list($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    private static function pick(array $tx, array $keys): mixed
    {
        foreach ($keys as $k) { if (isset($tx[$k]) && $tx[$k] !== '' && $tx[$k] !== null) return $tx[$k]; }
        return null;
    }

    /** Una transacción en una forma única; lo que no se entiende queda en «unknown» para que la depuración lo muestre. */
    public static function normalize(array $tx): array
    {
        $loc = self::pick($tx, ['locationId', 'location_id', 'altId', 'subAccountId', 'subaccountId']);
        $name = self::pick($tx, ['locationName', 'location_name', 'subAccountName']);
        $amount = self::pick($tx, ['amount', 'total', 'value', 'cost', 'price']);
        $kind = strtolower((string)(self::pick($tx, ['chargeType', 'type', 'transactionType', 'category', 'product', 'productType', 'usageType']) ?? ''));
        $desc = (string)(self::pick($tx, ['description', 'title', 'name', 'memo']) ?? '');
        $when = self::pick($tx, ['settlementTime', 'createdAt', 'date', 'timestamp', 'updatedAt']);
        $time = is_numeric($when) ? (int)((float)$when > 1e11 ? (float)$when / 1000 : $when) : (is_string($when) ? strtotime($when) : false);
        $hay = $kind.' '.strtolower($desc);
        $recharge = (bool)preg_match('/recharge|top.?up|topup|deposit|payment|refund|credit(?!\s*card)|reload|add.?fund|auto.?recharge|complimentary/', $hay);
        $n = is_numeric($amount) ? (float)$amount : null;
        $direction = $recharge ? 'recarga' : ($n !== null && ($n < 0 || preg_match('/usage|charge|debit|deduct|sms|email|call|phone|whatsapp|ai|conversation|rebill|verified|lookup|domain|premium/', $hay)) ? 'gasto' : ($n === null ? 'unknown' : 'gasto'));
        return ['locationId' => $loc !== null ? (string)$loc : null, 'locationName' => $name !== null ? (string)$name : null, 'amount' => $n === null ? null : abs($n), 'rawAmount' => $n,
            'kind' => $kind !== '' ? $kind : ($desc !== '' ? $desc : 'sin tipo'), 'description' => $desc, 'direction' => $direction, 'at' => $time ? gmdate('c', (int)$time) : null];
    }

    /**
     * Todo el mes, con paginación (de 1000 en 1000). Si el método elegido responde 404, 405 o 422 y $fallback es verdadero,
     * se intenta con el otro: la documentación y quien la cita no se ponen de acuerdo en cuál es.
     * @return array{method:string,rows:list<array<string,mixed>>,pages:int,partial:bool,error:?array}
     */
    public function monthRows(string $method, string $month, ?string $location, bool $fallback = true): array
    {
        $order = $fallback ? [$method, $method === 'POST' ? 'GET' : 'POST'] : [$method];
        $last = null;
        foreach ($order as $m) {
            $rows = []; $skip = 0; $pages = 0; $failed = null;
            do {
                $r = $this->transactions($m, $month, $location, $skip, self::PAGE);
                $pages++;
                if ($r['status'] !== 200) { $failed = $r; break; }
                $data = json_decode($r['body'], true);
                $got = is_array($data) ? self::rows($data) : [];
                foreach ($got as $tx) $rows[] = self::normalize($tx);
                $skip += self::PAGE;
            } while (count($got) === self::PAGE && $pages < self::MAX_PAGES);
            if (!$failed) return ['method' => $m, 'rows' => $rows, 'pages' => $pages, 'partial' => $pages >= self::MAX_PAGES, 'error' => null];
            $last = $failed;
            if (!in_array($failed['status'], [404, 405, 422], true)) break;
        }
        return ['method' => $method, 'rows' => [], 'pages' => 0, 'partial' => false, 'error' => ['status' => $last['status'] ?? 0, 'message' => $this->redact(mb_substr($last['body'] ?? '', 0, 300)) ?: ($last['error'] ?? 'Sin respuesta')]];
    }

    /** @param list<array<string,mixed>> $rows */
    public static function summarize(array $rows): array
    {
        $by = [];
        foreach ($rows as $r) {
            $id = $r['locationId'] ?? 'agencia';
            $by[$id] ??= ['locationId' => $id, 'locationName' => $r['locationName'], 'gasto' => 0.0, 'recargas' => 0.0, 'movimientos' => 0, 'sinClasificar' => 0, 'categorias' => [], 'dias' => [], 'ultimos' => []];
            $e = &$by[$id];
            $e['movimientos']++;
            if ($r['locationName'] && !$e['locationName']) $e['locationName'] = $r['locationName'];
            if ($r['direction'] === 'unknown' || $r['amount'] === null) { $e['sinClasificar']++; unset($e); continue; }
            if ($r['direction'] === 'recarga') $e['recargas'] += $r['amount'];
            else {
                $e['gasto'] += $r['amount'];
                $e['categorias'][$r['kind']] = ($e['categorias'][$r['kind']] ?? 0.0) + $r['amount'];
                if ($r['at']) { $d = substr($r['at'], 0, 10); $e['dias'][$d] = ($e['dias'][$d] ?? 0.0) + $r['amount']; }
            }
            $e['ultimos'][] = ['at' => $r['at'], 'tipo' => $r['kind'], 'descripcion' => $r['description'], 'direccion' => $r['direction'], 'monto' => $r['amount']];
            unset($e);
        }
        foreach ($by as &$e) {
            arsort($e['categorias']); ksort($e['dias']);
            $e['gasto'] = round($e['gasto'], 4); $e['recargas'] = round($e['recargas'], 4);
            $e['categorias'] = array_map(fn($k, $v) => ['tipo' => (string)$k, 'monto' => round($v, 4)], array_keys($e['categorias']), $e['categorias']);
            $e['dias'] = array_map(fn($k, $v) => ['dia' => (string)$k, 'monto' => round($v, 4)], array_keys($e['dias']), $e['dias']);
            usort($e['ultimos'], fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
            $e['ultimos'] = array_slice($e['ultimos'], 0, 50);
        }
        unset($e);
        return array_values($by);
    }

    /** El plan activo de una subcuenta y su saldo. Cada pieza falla por separado y dice por qué. */
    public function planOf(string $location): array
    {
        $out = ['plan' => null, 'estado' => null, 'precio' => null, 'saldo' => null, 'creditosDeCortesia' => null, 'errores' => []];
        $sub = $this->subscription($location);
        if ($sub['status'] === 200 && is_array($d = json_decode($sub['body'], true))) {
            $out['estado'] = $d['subscriptionStatus'] ?? null;
            if (!empty($d['saasPlanId'])) {
                $p = $this->plan((string)$d['saasPlanId']);
                if ($p['status'] === 200 && is_array($pd = json_decode($p['body'], true))) {
                    $out['plan'] = $pd['title'] ?? (string)$d['saasPlanId'];
                    $price = is_array($pd['prices'] ?? null) ? ($pd['prices'][0] ?? null) : null;
                    if (is_array($price)) $out['precio'] = ['monto' => $price['amount'] ?? null, 'moneda' => $price['currency'] ?? null, 'cada' => $price['recurringInterval'] ?? ($price['interval'] ?? null)];
                } else $out['errores'][] = 'plan: HTTP '.$p['status'];
            } else $out['plan'] = null;
        } else $out['errores'][] = 'suscripción: HTTP '.$sub['status'];
        $b = $this->balance($location);
        if ($b['status'] === 200 && is_array($bd = json_decode($b['body'], true))) { $out['saldo'] = $bd['balance'] ?? null; $out['creditosDeCortesia'] = $bd['complimentaryCredits'] ?? null; }
        else $out['errores'][] = 'saldo: HTTP '.$b['status'];
        return $out;
    }
}
