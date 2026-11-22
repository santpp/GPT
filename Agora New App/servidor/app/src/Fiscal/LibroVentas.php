<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);

namespace Agora\Fiscal;

use Agora\Fiscal\Base\Db;

/**
 * Libro de ventas del mes (Reglamento de la Ley de IVA, arts. 70 a 72): un renglón por documento emitido
 * en el período, en orden de fecha y número, con montos en bolívares. Las notas de crédito restan y los
 * documentos anulados aparecen con montos en cero (tipo de transacción 03-ANUL).
 */
final class LibroVentas
{
    public const COLUMNS = [
        'n' => 'N.° oper.', 'fecha' => 'Fecha', 'rif' => 'RIF / C.I.', 'nombre' => 'Nombre o razón social', 'tipo' => 'Tipo de documento', 'numero' => 'N.° de documento',
        'control' => 'N.° de control', 'afectada' => 'N.° factura afectada', 'transaccion' => 'Tipo de transacción', 'total_bs' => 'Total ventas con IVA (Bs.)',
        'exento_bs' => 'Ventas exentas (Bs.)', 'base_g_bs' => 'Base imponible alícuota general (Bs.)', 'alic_g' => '% alícuota general', 'iva_g_bs' => 'IVA alícuota general (Bs.)',
        'base_r_bs' => 'Base imponible alícuota reducida (Bs.)', 'alic_r' => '% alícuota reducida', 'iva_r_bs' => 'IVA alícuota reducida (Bs.)',
        'base_a_bs' => 'Base imponible general + adicional (Bs.)', 'alic_a' => '% general + adicional', 'iva_a_bs' => 'IVA general + adicional (Bs.)',
        'igtf_bs' => 'IGTF percibido (Bs.)', 'moneda' => 'Moneda', 'tasa' => 'Tasa BCV', 'total_divisa' => 'Total en la moneda del documento',
    ];
    private const MONEY = ['total_bs', 'exento_bs', 'base_g_bs', 'iva_g_bs', 'base_r_bs', 'iva_r_bs', 'base_a_bs', 'iva_a_bs', 'igtf_bs'];

    /** @return array{periodo:string,filas:list<array<string,mixed>>,totales:array<string,int>,documentos:int} */
    public static function build(Db $db, int $orgId, string $period): array
    {
        $rows = $db->all(
            "SELECT * FROM agora_fiscal_documents WHERE org_id = ? AND doc_type IN ('FAC','NC','ND') AND status IN ('EMITIDA','ANULADA') AND issue_date LIKE ? ORDER BY issue_date, emitted_at, doc_type, number",
            [$orgId, $period . '%']
        );
        $out = [];
        $tot = array_fill_keys(self::MONEY, 0);
        $n = 0;
        foreach ($rows as $d) {
            $n++;
            $annulled = $d['status'] === 'ANULADA';
            $sign = $d['doc_type'] === 'NC' ? -1 : 1;
            $snap = json_decode((string) $d['fiscal_snapshot'], true) ?: [];
            $rates = $snap['alicuotas'] ?? ['G' => 1600, 'R' => 800, 'A' => 3100];
            $t = [
                'subtotal_cents' => (int) $d['subtotal_cents'], 'discount_cents' => 0, 'exempt_cents' => (int) $d['exempt_cents'],
                'base_general_cents' => (int) $d['base_general_cents'], 'base_reduced_cents' => (int) $d['base_reduced_cents'], 'base_luxury_cents' => (int) $d['base_luxury_cents'],
                'iva_general_cents' => (int) $d['iva_general_cents'], 'iva_reduced_cents' => (int) $d['iva_reduced_cents'], 'iva_luxury_cents' => (int) $d['iva_luxury_cents'],
                'total_cents' => (int) $d['total_cents'], 'igtf_base_cents' => (int) $d['igtf_base_cents'], 'igtf_cents' => (int) $d['igtf_cents'],
            ];
            $micro = $d['rate_bcv'] !== null ? Money::rateMicro(is_string($d['rate_bcv']) ? $d['rate_bcv'] : sprintf('%.6F', (float) $d['rate_bcv'])) : null;
            $bs = isset($snap['totales_bs']) && is_array($snap['totales_bs']) ? $snap['totales_bs'] : Calculator::inBs($t, (string) $d['currency'], $micro);
            $v = fn (string $k): int => $annulled ? 0 : $sign * (int) ($bs[$k] ?? 0);
            $row = [
                'n' => $n, 'fecha' => implode('/', array_reverse(explode('-', (string) $d['issue_date']))), 'rif' => $d['client_rif'], 'nombre' => $d['client_name'],
                'tipo' => ['FAC' => 'Factura', 'NC' => 'Nota de crédito', 'ND' => 'Nota de débito'][$d['doc_type']], 'numero' => ($d['series'] ? $d['series'] . '-' : '') . $d['number_text'],
                'control' => $d['control_number'] ?? ($d['legacy'] ? '' : 'PENDIENTE'), 'afectada' => $d['affected_number'] ?? '',
                'transaccion' => $annulled ? '03-ANUL' : ($d['doc_type'] === 'FAC' ? '01-REG' : '02-COMPL'),
                'total_bs' => $v('total_cents'), 'exento_bs' => $v('exempt_cents'),
                'base_g_bs' => $v('base_general_cents'), 'alic_g' => (int) $d['base_general_cents'] > 0 ? (int) $rates['G'] / 100 : null, 'iva_g_bs' => $v('iva_general_cents'),
                'base_r_bs' => $v('base_reduced_cents'), 'alic_r' => (int) $d['base_reduced_cents'] > 0 ? (int) $rates['R'] / 100 : null, 'iva_r_bs' => $v('iva_reduced_cents'),
                'base_a_bs' => $v('base_luxury_cents'), 'alic_a' => (int) $d['base_luxury_cents'] > 0 ? (int) $rates['A'] / 100 : null, 'iva_a_bs' => $v('iva_luxury_cents'),
                'igtf_bs' => $v('igtf_cents'), 'moneda' => $d['currency'], 'tasa' => $micro !== null && $d['currency'] !== 'VES' ? $micro : null,
                'total_divisa' => $annulled ? 0 : $sign * (int) $d['total_cents'], 'importado' => (bool) $d['legacy'], 'estado' => $d['status'],
            ];
            foreach (self::MONEY as $k) {
                $tot[$k] += (int) $row[$k];
            }
            $out[] = $row;
        }
        return ['periodo' => $period, 'filas' => $out, 'totales' => $tot, 'documentos' => $n];
    }

    /** CSV para Excel (separador ";", coma decimal, UTF-8 con BOM) */
    public static function csv(array $book, array $profile): string
    {
        $esc = function ($v): string {
            $s = (string) $v;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !preg_match('/^-?\d/', $s)) {
                $s = "'" . $s; // evita fórmulas al abrir en Excel (inyección CSV)
            }
            return '"' . str_replace('"', '""', $s) . '"';
        };
        $money = fn (int $c): string => ($c < 0 ? '-' : '') . number_format(intdiv(abs($c), 100), 0, '', '') . ',' . str_pad((string) (abs($c) % 100), 2, '0', STR_PAD_LEFT);
        $lines = [];
        $lines[] = implode(';', [$esc('Libro de ventas'), $esc($profile['legal_name'] ?? ''), $esc('RIF ' . ($profile['rif'] ?? '')), $esc('Período ' . $book['periodo'])]);
        $lines[] = implode(';', array_map($esc, array_values(self::COLUMNS)));
        foreach ($book['filas'] as $r) {
            $cells = [];
            foreach (array_keys(self::COLUMNS) as $k) {
                $v = $r[$k];
                if (in_array($k, self::MONEY, true) || $k === 'total_divisa') {
                    $cells[] = $money((int) $v);
                } elseif ($k === 'tasa') {
                    $cells[] = $v === null ? '' : str_replace('.', ',', rtrim(rtrim(sprintf('%.6F', $v / 1_000_000), '0'), '.'));
                } elseif (in_array($k, ['alic_g', 'alic_r', 'alic_a'], true)) {
                    $cells[] = $v === null ? '' : str_replace('.', ',', (string) $v);
                } else {
                    $cells[] = $esc($v ?? '');
                }
            }
            $lines[] = implode(';', $cells);
        }
        $tot = [];
        foreach (array_keys(self::COLUMNS) as $k) {
            $tot[] = isset($book['totales'][$k]) ? $money((int) $book['totales'][$k]) : ($k === 'nombre' ? $esc('TOTALES') : '');
        }
        $lines[] = implode(';', $tot);
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }
}
