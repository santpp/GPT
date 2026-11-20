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

    /** Una sola petición del listado de transacciones: POST con cuerpo JSON y Version v3 (la que contesta tu cuenta). */
    public function transactions(string $month, ?string $location, int $skip = 0, int $limit = 1000): array
    {
        [, $from, $to] = self::month($month);
        $path = '/saas/companies/'.rawurlencode($this->company()).'/wallet-transactions';
        $filters = ['settlementTime' => ['from' => $from, 'to' => $to]];
        if ($location) $filters['locationId'] = $location;
        return $this->call('POST', $path, 'v3', ['skip' => $skip, 'limit' => $limit, 'timezone' => 'UTC', 'filters' => $filters]);
    }

    /** La cuenta contesta 200 o 201 según el endpoint: cualquier 2xx es éxito. */
    public static function ok(array $r): bool { return $r['status'] >= 200 && $r['status'] < 300; }

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
    /** Ágora Mobile 1.10.33 · La transacción aplanada (ruta => valor), para atribuirla y para las pistas. */
    public static function flat(array $tx, string $pre = '', int $depth = 0): array
    {
        $out = [];
        foreach ($tx as $k => $v) {
            $key = $pre === '' ? (string)$k : $pre.'.'.$k;
            if (is_array($v)) { if ($depth < 3) $out += self::flat($v, $key, $depth + 1); }
            elseif (is_scalar($v) && (string)$v !== '') $out[$key] = (string)$v;
        }
        return $out;
    }

    /** Ágora Mobile 1.10.33 · Nombre del tipo de cargo en palabras («whatsapp_marketing_message» → «WhatsApp · marketing»). */
    public static function label(string $kind): string
    {
        $k = strtolower(trim($kind));
        $map = ['whatsapp_marketing_message' => 'WhatsApp · marketing', 'whatsapp_utility_message' => 'WhatsApp · avisos', 'whatsapp_service_message' => 'WhatsApp · atención',
            'whatsapp_authentication_message' => 'WhatsApp · códigos de acceso', 'whatsapp_authentication_international_message' => 'WhatsApp · códigos de acceso',
            'sms' => 'SMS', 'mms' => 'MMS', 'email' => 'Correos', 'call' => 'Llamadas', 'calls' => 'Llamadas', 'phone' => 'Teléfono', 'recharge' => 'Recarga'];
        if (isset($map[$k])) return $map[$k];
        if (str_starts_with($k, 'whatsapp')) { $r = trim(str_replace('_', ' ', preg_replace('/^whatsapp[_ ]?|[_ ]?message$/', '', $k))); return $r === '' ? 'WhatsApp' : 'WhatsApp · '.$r; }
        if ($k === '' || !preg_match('/^[a-z0-9_ .-]+$/', $k)) return $kind;
        return ucfirst(str_replace(['_', '-'], ' ', $k));
    }

    /** @param array<string,true> $known identificadores de las subcuentas conectadas */
    public static function normalize(array $tx, array $known = []): array
    {
        $loc = self::pick($tx, ['locationId', 'location_id', 'altId', 'subAccountId', 'subaccountId']);
        // Ágora Mobile 1.10.33: si no vino en un campo conocido, se busca el identificador de una subcuenta conectada en cualquier dato del movimiento.
        $flat = $loc === null ? self::flat($tx) : [];
        if ($loc === null && $known) foreach ($flat as $v) if (isset($known[$v])) { $loc = $v; break; }
        $name = self::pick($tx, ['locationName', 'location_name', 'subAccountName']);
        $amount = self::pick($tx, ['amount', 'total', 'value', 'cost', 'price']);
        $kind = strtolower((string)(self::pick($tx, ['chargeType', 'type', 'transactionType', 'category', 'product', 'productType', 'usageType']) ?? ''));
        $desc = (string)(self::pick($tx, ['description', 'title', 'name', 'memo']) ?? '');
        $ref = null;
        if (preg_match('/^(.*?)\s*\bref:\s*(\S+)\s*$/is', $desc, $mm)) { $desc = trim($mm[1]); $ref = $mm[2]; }
        $when = self::pick($tx, ['settlementTime', 'createdAt', 'date', 'timestamp', 'updatedAt']);
        $time = is_numeric($when) ? (int)((float)$when > 1e11 ? (float)$when / 1000 : $when) : (is_string($when) ? strtotime($when) : false);
        $hay = $kind.' '.strtolower($desc);
        $recharge = (bool)preg_match('/recharge|top.?up|topup|deposit|payment|refund|credit(?!\s*card)|reload|add.?fund|auto.?recharge|complimentary/', $hay);
        $n = is_numeric($amount) ? (float)$amount : null;
        $direction = $recharge ? 'recarga' : ($n !== null && ($n < 0 || preg_match('/usage|charge|debit|deduct|sms|email|call|phone|whatsapp|ai|conversation|rebill|verified|lookup|domain|premium/', $hay)) ? 'gasto' : ($n === null ? 'unknown' : 'gasto'));
        $tipo = $kind !== '' ? $kind : ($desc !== '' ? $desc : 'sin tipo');
        if (strtolower($desc) === strtolower($tipo)) $desc = '';
        // Datos sueltos de lo que no tiene subcuenta: sirven para las pistas y las reglas de la agencia (sin montos, fechas ni textos largos).
        $campos = [];
        if ($loc === null) {
            foreach ($flat as $k => $v) if (!preg_match('/amount|total|value|cost|price|balance|time|date|createdAt|updatedAt|description|title|memo|^_?id$|\.id$/i', $k) && mb_strlen($v) <= 80) $campos[$k] = $v;
            if ($ref !== null) $campos['ref'] = mb_substr($ref, 0, 80);
            $campos = array_slice($campos, 0, 25, true);
        }
        return ['locationId' => $loc !== null ? (string)$loc : null, 'locationName' => $name !== null ? (string)$name : null, 'amount' => $n === null ? null : abs($n), 'rawAmount' => $n,
            'kind' => self::label($tipo), 'description' => $desc, 'direction' => $direction, 'at' => $time ? gmdate('c', (int)$time) : null, 'campos' => $campos];
    }

    /**
     * Todo el mes, con paginación (de 1000 en 1000).
     * @return array{rows:list<array<string,mixed>>,pages:int,partial:bool,error:?array}
     */
    public function monthRows(string $month, ?string $location, array $known = []): array
    {
        $rows = []; $skip = 0; $pages = 0;
        do {
            $r = $this->transactions($month, $location, $skip, self::PAGE);
            $pages++;
            if (!self::ok($r)) return ['rows' => [], 'pages' => 0, 'partial' => false, 'error' => ['status' => $r['status'], 'message' => $this->redact(mb_substr($r['body'], 0, 300)) ?: ($r['error'] ?? 'Sin respuesta')]];
            $data = json_decode($r['body'], true);
            $got = is_array($data) ? self::rows($data) : [];
            foreach ($got as $tx) $rows[] = self::normalize($tx, $known);
            $skip += self::PAGE;
        } while (count($got) === self::PAGE && $pages < self::MAX_PAGES);
        return ['rows' => $rows, 'pages' => $pages, 'partial' => $pages >= self::MAX_PAGES, 'error' => null];
    }

    /** @param list<array<string,mixed>> $rows */
    public static function summarize(array $rows, int $keep = 50): array
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
            $e['ultimos'] = array_slice($e['ultimos'], 0, $keep);
        }
        unset($e);
        return array_values($by);
    }

    /** El plan activo de una subcuenta. Cada pieza falla por separado y dice por qué. */
    public function planOf(string $location): array
    {
        $out = ['plan' => null, 'estado' => null, 'precio' => null, 'errores' => []];
        $sub = $this->subscription($location);
        if (self::ok($sub) && is_array($d = json_decode($sub['body'], true))) {
            $d = isset($d['data']) && is_array($d['data']) ? $d['data'] : $d;
            $out['estado'] = $d['subscriptionStatus'] ?? null;
            if (!empty($d['saasPlanId'])) {
                $p = $this->plan((string)$d['saasPlanId']);
                if (self::ok($p) && is_array($pd = json_decode($p['body'], true))) {
                    $pd = isset($pd['data']) && is_array($pd['data']) ? $pd['data'] : $pd;
                    $out['plan'] = $pd['title'] ?? (string)$d['saasPlanId'];
                    $price = is_array($pd['prices'] ?? null) ? ($pd['prices'][0] ?? null) : null;
                    if (is_array($price)) $out['precio'] = ['monto' => $price['amount'] ?? null, 'moneda' => $price['currency'] ?? null, 'cada' => $price['recurringInterval'] ?? ($price['interval'] ?? null)];
                } else $out['errores'][] = 'plan: HTTP '.$p['status'];
            }
        } else $out['errores'][] = 'suscripción: HTTP '.$sub['status'];
        return $out;
    }

    /**
     * Ágora Mobile 1.10.33 · Reglas de la agencia: «los cargos con este dato son de esta subcuenta».
     * @param list<array{campo:string,valor:string,locationId:string}> $rules
     */
    public static function applyRules(array $rows, array $rules): array
    {
        if (!$rules) return $rows;
        foreach ($rows as &$r) {
            if ($r['locationId'] !== null) continue;
            foreach ($rules as $x) if (($r['campos'][$x['campo']] ?? null) === $x['valor']) { $r['locationId'] = $x['locationId']; $r['porRegla'] = true; break; }
        }
        unset($r);
        return $rows;
    }

    /** Ágora Mobile 1.10.33 · Pistas: qué datos traen los cargos sin subcuenta y cuántas veces se repite cada valor. */
    public static function hints(array $rows, int $max = 10): array
    {
        $gastos = array_values(array_filter($rows, fn($r) => $r['locationId'] === null && $r['direction'] === 'gasto'));
        $n = count($gastos); if (!$n) return ['movimientos' => 0, 'monto' => 0.0, 'campos' => [], 'unicos' => []];
        $acc = [];
        foreach ($gastos as $r) foreach ($r['campos'] as $k => $v) { $acc[$k][$v][0] = ($acc[$k][$v][0] ?? 0) + 1; $acc[$k][$v][1] = ($acc[$k][$v][1] ?? 0.0) + (float)$r['amount']; }
        $campos = []; $unicos = [];
        foreach ($acc as $k => $vals) {
            $con = array_sum(array_column($vals, 0)); $dist = count($vals);
            if ($dist > 20 && $dist >= 0.5 * $con) { $unicos[] = (string)$k; continue; } // distinto en cada cargo (como la referencia del mensaje): no sirve para agrupar
            uasort($vals, fn($a, $b) => $b[1] <=> $a[1]);
            $campos[] = ['campo' => (string)$k, 'movimientos' => $con, 'distintos' => $dist,
                'valores' => array_map(fn($v, $x) => ['valor' => (string)$v, 'texto' => preg_match('/type|category|product|usage/i', (string)$k) ? self::label((string)$v) : (string)$v, 'movimientos' => $x[0], 'monto' => round($x[1], 4)], array_keys(array_slice($vals, 0, 8, true)), array_slice($vals, 0, 8, true))];
        }
        // Primero los datos que reparten el gasto en pocos grupos (como un número de WhatsApp por subcuenta).
        usort($campos, fn($a, $b) => [($a['distintos'] > 1 ? 0 : 1), $a['distintos'], -$a['movimientos']] <=> [($b['distintos'] > 1 ? 0 : 1), $b['distintos'], -$b['movimientos']]);
        return ['movimientos' => $n, 'monto' => round(array_sum(array_column($gastos, 'amount')), 4), 'campos' => array_slice($campos, 0, $max), 'unicos' => array_slice($unicos, 0, 10)];
    }
}
