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
        return ['hoy' => $hoy, 'vigente' => $vigente, 'historial' => array_values($dias)];
    }

    public function guardarManual(int $orgId, mixed $fecha, mixed $tasa, int $userId): void
    {
        $f = is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha) ? $fecha : null;
        if (!$f || strcmp($f, Clock::today()) > 0 || strcmp($f, (new \DateTimeImmutable(Clock::today()))->modify('-30 days')->format('Y-m-d')) < 0)
            throw HttpError::badRequest('La fecha de la tasa debe ser de hoy o de los últimos 30 días.');
        $v = is_numeric($tasa) ? (float) $tasa : (is_string($tasa) ? (float) str_replace(',', '.', str_replace('.', '', $tasa)) : 0.0);
        if (!($v > 0 && $v < 100000000)) throw HttpError::badRequest('Escribe la tasa BCV en bolívares por dólar, por ejemplo 245,6789.');
        $this->db->upsert('agora_fiscal_tasas', ['org_id' => $orgId, 'day' => $f, 'rate' => sprintf('%.6F', $v), 'created_by' => $userId, 'created_at' => Clock::ts()], ['org_id', 'day'], ['rate', 'created_by', 'created_at']);
    }
}
