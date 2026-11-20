<?php
declare(strict_types=1);
namespace Agora\Fiscal;

use Agora\Fiscal\Base\Clock;
use Agora\Fiscal\Base\Db;
use Agora\Fiscal\Base\HttpError;

/**
 * Agora New App · tasa BCV del día. La trae la tarea programada (servidor/bin/worker.php) desde la fuente pública
 * (con la página del BCV como respaldo) y es la misma para todas las subcuentas. Si no llega, cada subcuenta puede escribirla a mano.
 * La factura fiscal exige una tasa de los últimos 5 días (Documents::bcvRate).
 */
final class Tasas
{
    public function __construct(private Db $db) {}

    /** 2.12.0 · Fuentes de tasa: código => [moneda, nombre]. BCV (dólar) es la que exige la factura fiscal. */
    public const FUENTES = ['BCV' => ['USD', 'BCV dólar'], 'BCV_EUR' => ['EUR', 'BCV euro'], 'USDT' => ['USD', 'USDT Binance']];

    /** Trae las tres tasas del día. @return array<string,array{ok:bool,tasa:?float,fecha:?string,origen:string,error:?string}> */
    public function actualizarTodas(): array
    {
        $out = ['BCV' => $this->actualizar()];
        foreach (['BCV_EUR' => fn () => FxSources::bcvEur(), 'USDT' => fn () => FxSources::usdt()] as $k => $f) {
            try { $r = $f(); } catch (\Throwable) { $r = ['ok' => false, 'rate' => null, 'date' => null, 'source' => strtolower($k), 'error' => 'sin respuesta']; }
            if (!$r['ok'] || !$r['rate'] || $r['rate'] <= 0) { $out[$k] = ['ok' => false, 'tasa' => null, 'fecha' => null, 'origen' => (string) $r['source'], 'error' => 'No se pudo leer la tasa ' . self::FUENTES[$k][1] . ' en este momento.']; continue; }
            $dia = $r['date'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['date']) ? (string) $r['date'] : Clock::today();
            $this->db->upsert('agora_fiscal_fx', ['day' => $dia, 'source' => $k, 'rate_last' => sprintf('%.6F', (float) $r['rate']), 'origin' => mb_substr((string) $r['source'], 0, 40), 'updated_at' => Clock::ts()],
                ['day', 'source'], ['rate_last', 'origin', 'updated_at']);
            $out[$k] = ['ok' => true, 'tasa' => (float) $r['rate'], 'fecha' => $dia, 'origen' => (string) $r['source'], 'error' => null];
        }
        return $out;
    }

    /**
     * La tasa vigente de una fuente: la más reciente de los últimos 5 días (automática o escrita a mano; la automática gana el mismo día).
     * @return array{tasa:float,fecha:string,origen:string}|null
     */
    public function vigente(int $orgId, string $fuente, ?string $dia = null): ?array
    {
        if (!isset(self::FUENTES[$fuente])) return null;
        $dia = $dia ?? Clock::today();
        $desde = (new \DateTimeImmutable($dia))->modify('-5 days')->format('Y-m-d');
        $auto = $this->db->one('SELECT day, rate_last AS rate FROM agora_fiscal_fx WHERE source = ? AND day <= ? AND day >= ? ORDER BY day DESC LIMIT 1', [$fuente, $dia, $desde]);
        $mano = $fuente === 'BCV'
            ? $this->db->one('SELECT day, rate FROM agora_fiscal_tasas WHERE org_id = ? AND day <= ? AND day >= ? ORDER BY day DESC LIMIT 1', [$orgId, $dia, $desde])
            : $this->db->one('SELECT day, rate FROM agora_fiscal_tasas_ref WHERE org_id = ? AND source = ? AND day <= ? AND day >= ? ORDER BY day DESC LIMIT 1', [$orgId, $fuente, $dia, $desde]);
        $a = $auto && (float) $auto['rate'] > 0 ? ['tasa' => (float) $auto['rate'], 'fecha' => (string) $auto['day'], 'origen' => 'automática'] : null;
        $m = $mano && (float) $mano['rate'] > 0 ? ['tasa' => (float) $mano['rate'], 'fecha' => (string) $mano['day'], 'origen' => 'a mano'] : null;
        if ($a && $m) return strcmp($m['fecha'], $a['fecha']) > 0 ? $m : $a;
        return $a ?? $m;
    }

    /** Trae la tasa del día. @return array{ok:bool,tasa:?float,fecha:?string,origen:string,error:?string} */
    public function actualizar(): array
    {
        $r = FxSources::bcv();
        if (!$r['ok'] || !$r['rate'] || $r['rate'] <= 0) return ['ok' => false, 'tasa' => null, 'fecha' => null, 'origen' => $r['source'], 'error' => 'No se pudo leer la tasa BCV en este momento.'];
        $dia = $r['date'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['date']) ? (string) $r['date'] : Clock::today();
        $this->db->upsert('agora_fiscal_fx', ['day' => $dia, 'source' => 'BCV', 'rate_last' => sprintf('%.6F', (float) $r['rate']), 'origin' => mb_substr((string) $r['source'], 0, 40), 'updated_at' => Clock::ts()],
            ['day', 'source'], ['rate_last', 'origin', 'updated_at']);
        return ['ok' => true, 'tasa' => (float) $r['rate'], 'fecha' => $dia, 'origen' => (string) $r['source'], 'error' => null];
    }

    /** Lo que ve la pantalla: la tasa vigente para facturar hoy y los últimos 30 días. */
    public function estado(int $orgId): array
    {
        $hoy = Clock::today();
        $desde = (new \DateTimeImmutable($hoy))->modify('-30 days')->format('Y-m-d');
        $auto = $this->db->all("SELECT day, rate_last AS rate FROM agora_fiscal_fx WHERE source = 'BCV' AND day >= ? ORDER BY day DESC", [$desde]);
        $mano = $this->db->all('SELECT day, rate FROM agora_fiscal_tasas WHERE org_id = ? AND day >= ? ORDER BY day DESC', [$orgId, $desde]);
        $dias = [];
        foreach ($mano as $m) $dias[(string) $m['day']] = ['fecha' => (string) $m['day'], 'tasa' => (float) $m['rate'], 'origen' => 'a mano'];
        foreach ($auto as $a) $dias[(string) $a['day']] = ['fecha' => (string) $a['day'], 'tasa' => (float) $a['rate'], 'origen' => 'automática'];
        krsort($dias);
        $vigente = null;
        try {
            [$t, $d] = (new Documents($this->db, new Base\Audit(static function (): void {})))->bcvRate($orgId, $hoy);
            $vigente = ['tasa' => (float) $t, 'fecha' => $d];
        } catch (HttpError) {}
        $fuentes = [];
        foreach (self::FUENTES as $k => [$mon, $nombre]) $fuentes[] = ['fuente' => $k, 'moneda' => $mon, 'nombre' => $nombre, 'vigente' => $this->vigente($orgId, $k, $hoy)];
        return ['hoy' => $hoy, 'vigente' => $vigente, 'historial' => array_values($dias), 'fuentes' => $fuentes];
    }

    public function guardarManual(int $orgId, mixed $fecha, mixed $tasa, int $userId, string $fuente = 'BCV'): void
    {
        if (!isset(self::FUENTES[$fuente])) throw HttpError::badRequest('Elige de qué tasa se trata: BCV dólar, BCV euro o USDT Binance.');
        $f = is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha) ? $fecha : null;
        if (!$f || strcmp($f, Clock::today()) > 0 || strcmp($f, (new \DateTimeImmutable(Clock::today()))->modify('-30 days')->format('Y-m-d')) < 0)
            throw HttpError::badRequest('La fecha de la tasa debe ser de hoy o de los últimos 30 días.');
        $v = is_numeric($tasa) ? (float) $tasa : (is_string($tasa) ? (float) str_replace(',', '.', str_replace('.', '', $tasa)) : 0.0);
        if (!($v > 0 && $v < 100000000)) throw HttpError::badRequest('Escribe la tasa en bolívares por ' . (self::FUENTES[$fuente][0] === 'EUR' ? 'euro' : 'dólar') . ', por ejemplo 245,6789.');
        if ($fuente !== 'BCV') { $this->db->upsert('agora_fiscal_tasas_ref', ['org_id' => $orgId, 'day' => $f, 'source' => $fuente, 'rate' => sprintf('%.6F', $v), 'created_by' => $userId, 'created_at' => Clock::ts()], ['org_id', 'day', 'source'], ['rate', 'created_by', 'created_at']); return; }
        $this->db->upsert('agora_fiscal_tasas', ['org_id' => $orgId, 'day' => $f, 'rate' => sprintf('%.6F', $v), 'created_by' => $userId, 'created_at' => Clock::ts()], ['org_id', 'day'], ['rate', 'created_by', 'created_at']);
    }
}
