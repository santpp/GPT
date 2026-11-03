<?php
declare(strict_types=1);

/**
 * El pulso del negocio: lo que se cobra por el servicio. Sólo propietario.
 * Los espacios y las personas propietarias salen de Ágora; los cobros, los egresos y los planes viven en el sistema
 * de contratos y, mientras no estén conectados aquí, se devuelven en cero.
 */
final class BusinessSummary
{
    public static function get(int $tenant, string $period): array
    {
        $spaces = [];
        foreach (query('SELECT id,name,status FROM ghl_connections WHERE tenant_id=? ORDER BY id', [$tenant])->fetchAll() as $c)
            $spaces[] = ['id' => (int)$c['id'], 'nombre' => $c['name'], 'logo' => null, 'plan' => '', 'mrr' => 0, 'estado' => $c['status'] === 'connected' ? 'activo' : 'pausa'];
        $owners = [];
        foreach (query("SELECT id,email,display_name FROM users WHERE tenant_id=? AND role='administrator' AND active=TRUE ORDER BY id", [$tenant])->fetchAll() as $u)
            $owners[] = ['id' => (int)$u['id'], 'nombre' => trim((string)$u['display_name']) !== '' ? $u['display_name'] : $u['email'], 'correo' => $u['email']];
        $start = new DateTimeImmutable($period.'-01', new DateTimeZone('UTC'));
        $series = [];
        for ($i = 5; $i >= 0; $i--) $series[] = ['ym' => $start->modify("-$i months")->format('Y-m'), 'v' => 0];
        return [
            'mrr' => 0, 'mrrAnterior' => 0, 'clientesActivos' => count(array_filter($spaces, fn($s) => $s['estado'] === 'activo')), 'enPausa' => count(array_filter($spaces, fn($s) => $s['estado'] === 'pausa')),
            'recaudado' => 0, 'arr' => 0, 'caja' => 0, 'churn' => 0, 'churnPct' => 0, 'egresos' => 0, 'inversion' => 0,
            'serie' => $series, 'espacios' => $spaces, 'superadmins' => $owners, 'porVencer' => [],
            // Lo que falta conectar: así la pantalla puede decirlo en vez de enseñar ceros como si fueran cifras.
            'origenDeCobros' => 'sin_conectar',
        ];
    }
}
