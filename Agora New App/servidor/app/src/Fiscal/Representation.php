<?php
declare(strict_types=1);

namespace Agora\Fiscal;

/**
 * Representación imprimible de un documento fiscal (HTML sin scripts, A4 vertical de 794 px).
 * Contenido según la Providencia SNAT/2011/00071 (art. 13) y, para notas, el art. 23:
 * denominación, número, control, fecha, emisor y adquirente con RIF y domicilio, descripción, bases por
 * alícuota, IVA, total, montos en Bs a la tasa BCV cuando se opera en divisas, y datos de la imprenta.
 */
final class Representation
{
    private const TITLES = ['FAC' => 'Factura', 'NC' => 'Nota de crédito', 'ND' => 'Nota de débito', 'SIM' => 'Factura simple'];

    /**
     * @param array<string,mixed> $snap foto fiscal del documento
     * @param array<string,mixed> $o estado (BORRADOR|EMITIDA|ANULADA), control, control_asignado, anulada_en, motivo_anulacion, copia (bool)
     */
    public static function render(array $snap, array $o = []): string
    {
        $e = fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $st = (string) ($o['estado'] ?? 'EMITIDA');
        $tipo = (string) ($snap['tipo'] ?? 'FAC');
        $cur = (string) ($snap['moneda'] ?? 'USD');
        $sym = $cur === 'VES' ? 'Bs.' : ($cur === 'EUR' ? '€' : 'US$');
        $rate = !empty($snap['tasa_bcv']) ? Money::rateMicro((string) $snap['tasa_bcv']) : null;
        $t = $snap['totales'] ?? [];
        $tb = $snap['totales_bs'] ?? [];
        $em = $snap['emisor'] ?? [];
        $cl = $snap['cliente'] ?? [];
        $pr = $snap['imprenta'] ?? [];
        $control = $o['control'] ?? ($snap['control'] ?? null);
        $digital = ($pr['modo'] ?? '') === 'IMPRENTA_DIGITAL';
        $dmy = fn (?string $d): string => $d ? implode('-', array_reverse(explode('-', $d))) : '';
        $m = fn ($c): string => Money::fmt((int) $c);
        $alic = $snap['alicuotas'] ?? ['G' => 1600, 'R' => 800, 'A' => 3100];
        $pct = fn (int $bp): string => rtrim(rtrim(number_format($bp / 100, 2, ',', '.'), '0'), ',') . ' %';

        $simple = $tipo === 'SIM';
        $mark = '';
        if ($st === 'BORRADOR') {
            $mark = $simple ? 'BORRADOR' : 'BORRADOR · SIN VALIDEZ FISCAL';
        } elseif ($st === 'ANULADA') {
            $mark = 'ANULADA';
        } elseif (!$simple && !$control && empty($snap['legado'])) {
            $mark = 'PENDIENTE DE NÚMERO DE CONTROL';
        }

        $rows = '';
        foreach ($snap['lineas'] ?? [] as $l) {
            $ex = ($l['impuesto'] ?? 'G') === 'E' ? ' (E)' : '';
            $tag = ['G' => 'G', 'R' => 'R', 'A' => 'A', 'E' => 'E'][$l['impuesto'] ?? 'G'];
            $rows .= '<tr><td class="c">' . $e($l['codigo'] ?? '') . '</td><td>' . $e($l['descripcion'] ?? '') . $ex . '</td><td class="n">' . $e(Money::fmtQty((int) ($l['cantidad_milli'] ?? 1000))) . '</td><td class="n">' . $m($l['precio_cents'] ?? 0)
                . '</td><td class="n">' . ((int) ($l['descuento_cents'] ?? 0) > 0 ? $m($l['descuento_cents']) : '—') . '</td><td class="c">' . $tag . '</td><td class="n">' . $m($l['base_cents'] ?? 0) . '</td></tr>';
        }

        $tot = function (string $label, string $k, bool $strong = false) use ($t, $tb, $m, $cur, $e): string {
            $v = (int) ($t[$k] ?? 0);
            $bs = $cur !== 'VES' ? '<td class="n bs">' . $m($tb[$k] ?? 0) . '</td>' : '';
            return '<tr' . ($strong ? ' class="strong"' : '') . '><td>' . $e($label) . '</td><td class="n">' . $m($v) . '</td>' . $bs . '</tr>';
        };
        $totRows = $tot('Subtotal (base imponible + exento)', 'subtotal_cents');
        if ((int) ($t['discount_cents'] ?? 0) > 0) {
            $totRows .= $tot('Descuentos incluidos', 'discount_cents');
        }
        if ((int) ($t['exempt_cents'] ?? 0) > 0) {
            $totRows .= $tot('Monto exento (E)', 'exempt_cents');
        }
        foreach ([['G', 'base_general_cents', 'iva_general_cents', 'alícuota general'], ['R', 'base_reduced_cents', 'iva_reduced_cents', 'alícuota reducida'], ['A', 'base_luxury_cents', 'iva_luxury_cents', 'alícuota general + adicional']] as [$code, $bk, $ik, $name]) {
            if ((int) ($t[$bk] ?? 0) > 0 || (int) ($t[$ik] ?? 0) > 0) {
                $totRows .= $tot('Base imponible ' . $name . ' (' . $pct((int) $alic[$code]) . ')', $bk);
                $totRows .= $tot('IVA ' . $pct((int) $alic[$code]), $ik);
            }
        }
        $totRows .= $tot('Total ' . (in_array($tipo, ['FAC', 'SIM'], true) ? 'de la factura' : 'de la nota'), 'total_cents', true);
        if ((int) ($t['igtf_cents'] ?? 0) > 0) {
            $totRows .= $tot('IGTF ' . $pct((int) ($snap['igtf_bp'] ?? 300)) . ' (pago en divisas)', 'igtf_cents');
            $tg = (int) $t['total_cents'] + (int) $t['igtf_cents'];
            $tgb = (int) ($tb['total_cents'] ?? 0) + (int) ($tb['igtf_cents'] ?? 0);
            $totRows .= '<tr class="strong"><td>Total a pagar con IGTF</td><td class="n">' . $m($tg) . '</td>' . ($cur !== 'VES' ? '<td class="n bs">' . $m($tgb) . '</td>' : '') . '</tr>';
        }

        $rateLine = '';
        if ($cur !== 'VES' && (!$simple || $rate)) {
            $rateLine = '<p class="rate">Operación en moneda extranjera (' . $e($cur) . '). Equivalente en bolívares a la tasa oficial del Banco Central de Venezuela (BCV) del '
                . $e($dmy($snap['fecha_tasa'] ?? $snap['fecha'] ?? '')) . ': <b>Bs. ' . ($rate ? $e(Money::fmtRate($rate, 4)) : '—') . ' por ' . $e($sym) . ' 1</b>.</p>';
        }

        $aff = '';
        if (!empty($snap['afecta'])) {
            $a = $snap['afecta'];
            $aff = '<div class="box aff"><b>Documento afectado:</b> Factura N.° ' . $e($a['numero'] ?? '') . ' de fecha ' . $e($dmy($a['fecha'] ?? '')) . ', por un total de ' . $e($sym) . ' ' . $m($a['total_cents'] ?? 0)
                . (!empty($a['control']) ? ' (N.° de control ' . $e($a['control']) . ')' : '') . '.<br><b>Motivo:</b> ' . $e($snap['motivo'] ?? '') . '</div>';
        }

        $printer = '';
        if ($simple) {
            $printer = '';
        } elseif ($digital) {
            $printer = 'Documento emitido por medios digitales. N.° de control asignado por la imprenta digital autorizada ' . $e($pr['nombre'] ?? '') . ($pr['rif'] ? ', RIF ' . $e($pr['rif']) : '')
                . ($pr['providencia'] ? ', providencia N.° ' . $e($pr['providencia']) . ($pr['fecha'] ? ' del ' . $e($dmy($pr['fecha'])) : '') : '')
                . (!empty($o['control_asignado']) ? ' · asignado el ' . $e(self::digitalStamp((string) $o['control_asignado'])) : '') . '.';
        } elseif ($pr) {
            $printer = 'Imprenta autorizada: ' . $e($pr['nombre'] ?? '') . ($pr['rif'] ? ', RIF ' . $e($pr['rif']) : '') . ($pr['providencia'] ? ' · Providencia N.° ' . $e($pr['providencia']) . ($pr['fecha'] ? ' del ' . $e($dmy($pr['fecha'])) : '') : '')
                . (!empty($pr['rango']) ? ' · N.° de control desde el ' . $e($pr['rango']['desde'] ?? '') . ' hasta el ' . $e($pr['rango']['hasta'] ?? '') : '') . '.';
        }

        $legends = '';
        foreach ($snap['leyendas'] ?? [] as $lg) {
            $legends .= '<p>' . $e($lg) . '</p>';
        }
        $copy = $simple ? 'DOCUMENTO SIN VALIDEZ FISCAL' : (!empty($o['copia']) ? 'COPIA · SIN DERECHO A CRÉDITO FISCAL' : 'ORIGINAL');
        $annul = $st === 'ANULADA' ? '<div class="box annul"><b>Documento anulado</b>' . (!empty($o['anulada_en']) ? ' el ' . $e($dmy(substr((string) $o['anulada_en'], 0, 10))) : '') . '. Motivo: ' . $e($o['motivo_anulacion'] ?? '') . '</div>' : '';
        $num = $e($snap['numero_texto'] ?? '');
        $serie = !empty($snap['serie']) ? 'Serie ' . $e($snap['serie']) . ' · ' : '';

        $bsHead = $cur !== 'VES' ? '<th class="n">Bs.</th>' : '';
        if ($simple) $legends .= '<p><b>Documento sin validez fiscal.</b> No da derecho a crédito fiscal.</p>';
        $title = self::TITLES[$tipo] ?? 'Documento';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . $e($title . ' ' . ($snap['numero_texto'] ?? 'borrador')) . '</title><style>'
            . '*{box-sizing:border-box}body{margin:0;background:#E5E5EA;font:12px/1.4 -apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#1D1D1F}'
            . '#page{position:relative;width:794px;min-height:1123px;margin:0 auto;background:#fff;padding:40px 44px 36px;overflow:hidden}'
            . '.top{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #282E49;padding-bottom:14px}'
            . '.iss h1{margin:0 0 4px;font-size:18px;letter-spacing:-.01em}.iss p{margin:1px 0;color:#3A3A3C}'
            . '.doc{min-width:250px;text-align:right}.doc .t{font-size:20px;font-weight:700;color:#AD103C;text-transform:uppercase;letter-spacing:.02em}'
            . '.doc table{margin-left:auto;border-collapse:collapse}.doc td{padding:1px 0 1px 10px}.doc td:first-child{color:#6E6E73}.doc td b{font-variant-numeric:tabular-nums}'
            . '.orig{display:inline-block;margin-top:6px;font-size:10px;font-weight:700;letter-spacing:.08em;color:#282E49;border:1px solid #282E49;border-radius:4px;padding:2px 6px}'
            . '.box{border:1px solid #D2D2D7;border-radius:10px;padding:10px 12px;margin-top:14px}.cli{display:grid;grid-template-columns:1fr 1fr;gap:2px 18px}.cli p{margin:0}.cli span{color:#6E6E73}'
            . 'table.items{width:100%;border-collapse:collapse;margin-top:14px}table.items th{background:#282E49;color:#fff;font-weight:600;font-size:11px;padding:6px 6px;text-align:left}'
            . 'table.items td{border-bottom:1px solid #E5E5EA;padding:6px;vertical-align:top}.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.c{text-align:center}'
            . '.sum{display:flex;justify-content:flex-end;margin-top:12px}.sum table{border-collapse:collapse;min-width:400px}.sum td{padding:4px 8px;border-bottom:1px solid #E5E5EA}.sum th{font-size:11px;color:#6E6E73;text-align:right;padding:0 8px 4px;font-weight:600}'
            . '.sum tr.strong td{font-weight:700;border-top:1.5px solid #1D1D1F;font-size:13px}.bs{color:#3A3A3C}'
            . '.rate{margin:12px 0 0;color:#3A3A3C}.aff{background:#F5F5F7}.annul{border-color:#AD103C;color:#AD103C}'
            . '.foot{margin-top:18px;border-top:1px solid #D2D2D7;padding-top:10px;color:#6E6E73;font-size:10.5px}.foot p{margin:2px 0}'
            . '.wm{position:absolute;top:40%;left:-10%;width:120%;text-align:center;transform:rotate(-24deg);font-size:54px;font-weight:800;color:rgba(173,16,60,.13);letter-spacing:.04em;pointer-events:none}'
            . '@media print{body{background:#fff}#page{margin:0}}'
            . '</style></head><body><div id="page">'
            . ($mark ? '<div class="wm">' . $e($mark) . '</div>' : '')
            . '<div class="top"><div class="iss"><h1>' . $e($em['razon_social'] ?? '') . '</h1>' . (!empty($em['rif']) ? '<p><b>RIF ' . $e($em['rif']) . '</b>' . (!empty($em['contribuyente_especial']) ? ' · Contribuyente especial' : '') . '</p>' : '') . (!empty($em['domicilio']) ? '<p>Domicilio fiscal: ' . $e($em['domicilio']) . '</p>' : '')
            . (!empty($em['telefono']) || !empty($em['correo']) ? '<p>' . $e(trim(($em['telefono'] ?? '') . ' · ' . ($em['correo'] ?? ''), ' ·')) . '</p>' : '') . '</div>'
            . '<div class="doc"><div class="t">' . $e($title) . '</div><table>'
            . '<tr><td>' . $serie . 'N.°</td><td><b>' . ($num !== '' ? $num : '—') . '</b></td></tr>'
            . ($simple ? '' : '<tr><td>N.° de control</td><td><b>' . ($control ? $e($control) : ($st === 'BORRADOR' ? '—' : 'Pendiente')) . '</b></td></tr>')
            . '<tr><td>Fecha de emisión</td><td><b>' . $e($dmy($snap['fecha'] ?? '')) . '</b></td></tr>'
            . (!empty($snap['hora']) ? '<tr><td>Hora</td><td>' . $e($snap['hora']) . '</td></tr>' : '')
            . (!empty($snap['vence']) && in_array($tipo, ['FAC', 'SIM'], true) ? '<tr><td>Vence</td><td>' . $e($dmy($snap['vence'])) . (!empty($snap['vence_hora']) ? ' · ' . $e((string) $snap['vence_hora']) : '') . '</td></tr>' : '')
            . (!empty($snap['condiciones']) ? '<tr><td>Condiciones</td><td>' . $e($snap['condiciones']) . '</td></tr>' : '')
            . '</table><span class="orig">' . $e($copy) . '</span></div></div>'
            . '<div class="box cli"><p><span>Nombre o razón social:</span> <b>' . $e($cl['nombre'] ?? '') . '</b></p>' . (!$simple || !empty($cl['rif']) ? '<p><span>RIF / C.I.:</span> <b>' . $e($cl['rif'] ?? '') . '</b></p>' : '')
            . (!$simple || !empty($cl['domicilio']) ? '<p style="grid-column:1/-1"><span>Domicilio fiscal:</span> ' . $e($cl['domicilio'] ?? '') . '</p>' : '')
            . (!empty($cl['telefono']) || !empty($cl['correo']) ? '<p><span>Teléfono:</span> ' . $e($cl['telefono'] ?? '') . '</p><p><span>Correo:</span> ' . $e($cl['correo'] ?? '') . '</p>' : '') . '</div>'
            . $aff
            . '<table class="items"><thead><tr><th class="c" style="width:70px">Código</th><th>Descripción</th><th class="n" style="width:60px">Cant.</th><th class="n" style="width:92px">Precio (' . $e($sym) . ')</th><th class="n" style="width:78px">Descuento</th><th class="c" style="width:34px">Imp.</th><th class="n" style="width:100px">Monto (' . $e($sym) . ')</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<div class="sum"><table><tr><th></th><th>' . $e($sym) . '</th>' . $bsHead . '</tr>' . $totRows . '</table></div>'
            . $rateLine
            . (is_array($snap['referencia'] ?? null) ? '<div class="box"><b>Precios calculados en ' . ($snap['referencia']['moneda'] === 'EUR' ? 'euros' : 'dólares') . ':</b> tasa ' . $e((string) ($snap['referencia']['nombre'] ?? '')) . ' del ' . $e($dmy((string) ($snap['referencia']['fecha'] ?? ''))) . ', ' . $e(number_format((float) ($snap['referencia']['tasa'] ?? 0), 4, ',', '.')) . ' Bs. por ' . ($snap['referencia']['moneda'] === 'EUR' ? '€' : 'US$') . '.</div>' : '')
            . ((string) ($snap['notas'] ?? '') !== '' ? '<div class="box"><b>Observaciones:</b> ' . nl2br($e($snap['notas'])) . '</div>' : '')
            . $annul
            . '<div class="foot"><p>Imp.: G = alícuota general · R = reducida · A = general + adicional (bienes de lujo) · E = exento.</p>' . $legends . ($printer ? '<p>' . $printer . '</p>' : '') . '</div>'
            . '</div></body></html>';
    }

    /** Fecha y hora de asignación del control en el formato de la Providencia 102: DDMMAAAA HH.MM.SS (hora de Venezuela) */
    public static function digitalStamp(string $utcTs): string
    {
        $d = (new \DateTimeImmutable(substr($utcTs, 0, 19), new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('America/Caracas'));
        return $d->format('dmY H.i.s');
    }
}
