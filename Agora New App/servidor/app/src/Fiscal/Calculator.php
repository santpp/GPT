<?php
declare(strict_types=1);

namespace Agora\Fiscal;

use Agora\Fiscal\Base\HttpError;
use Agora\Fiscal\Base\Validator;

/**
 * Cálculo fiscal en el servidor (nunca se confía en los totales que manda el navegador).
 *  · Base de cada línea = cantidad × precio − descuento.
 *  · IVA por alícuota sobre la suma de bases (G general, R reducida, A general + adicional de lujo, E exento).
 *  · IGTF (si la empresa lo percibe y el pago es en divisas): sobre el total con IVA, aparte.
 *  · Equivalente en bolívares a la tasa BCV del día de emisión.
 */
final class Calculator
{
    public const CODES = ['G', 'R', 'A', 'E'];

    /**
     * @param list<mixed> $raw líneas tal como llegan de la app
     * @return list<array<string,mixed>> líneas normalizadas
     */
    public static function lines(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw) || !$raw) {
            throw HttpError::badRequest('Agrega al menos una línea (producto o servicio).');
        }
        if (count($raw) > 200) {
            throw HttpError::badRequest('Máximo 200 líneas por documento.');
        }
        $out = [];
        foreach ($raw as $i => $l) {
            if (!is_array($l)) {
                throw HttpError::badRequest('Línea ' . ($i + 1) . ': formato no válido.');
            }
            $n = $i + 1;
            $desc = Validator::clean($l['descripcion'] ?? '', 500);
            if (mb_strlen($desc) < 1) {
                throw HttpError::badRequest("Línea {$n}: escribe la descripción.");
            }
            $qty = Money::parse($l['cantidad'] ?? 1, "Línea {$n}, cantidad", 3, 100_000_000);
            if ($qty <= 0) {
                throw HttpError::badRequest("Línea {$n}: la cantidad debe ser mayor que cero.");
            }
            $price = Money::parse($l['precio'] ?? null, "Línea {$n}, precio", 2, 10_000_000_000);
            $disc = Money::parse($l['descuento'] ?? 0, "Línea {$n}, descuento", 2, 10_000_000_000);
            $tax = strtoupper((string) ($l['impuesto'] ?? 'G'));
            if (!in_array($tax, self::CODES, true)) {
                throw HttpError::badRequest("Línea {$n}: impuesto no válido (G, R, A o E).");
            }
            $gross = Money::divRound($qty * $price, 1000);
            if ($disc > $gross) {
                throw HttpError::badRequest("Línea {$n}: el descuento no puede ser mayor que el monto.");
            }
            $code = isset($l['codigo']) && $l['codigo'] !== '' ? mb_substr(preg_replace('/[^\w.\-]/u', '', (string) $l['codigo']) ?? '', 0, 60) : null;
            $out[] = ['line_no' => $n, 'product_code' => $code ?: null, 'description' => $desc, 'quantity_milli' => $qty, 'unit_price_cents' => $price, 'discount_cents' => $disc, 'tax_code' => $tax, 'recurring' => !empty($l['recurrente']) ? 1 : 0, 'gross_cents' => $gross];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $lines normalizadas (lines())
     * @param array<string,mixed> $profile fila de fiscal_profiles
     * @return array{lines:list<array<string,mixed>>,totals:array<string,int>,rates:array<string,int>}
     */
    public static function compute(array $lines, array $profile, bool $foreignPayment, string $currency): array
    {
        $bp = [
            'G' => (int) ($profile['iva_general_bp'] ?? 1600),
            'R' => (int) ($profile['iva_reduced_bp'] ?? 800),
            'A' => (int) ($profile['iva_general_bp'] ?? 1600) + (int) ($profile['iva_luxury_bp'] ?? 1500),
            'E' => 0,
        ];
        $base = ['G' => 0, 'R' => 0, 'A' => 0, 'E' => 0];
        $disc = 0;
        foreach ($lines as &$l) {
            $b = (int) $l['gross_cents'] - (int) $l['discount_cents'];
            $l['base_cents'] = $b;
            $l['iva_cents'] = Money::divRound($b * $bp[$l['tax_code']], 10000);
            $l['total_cents'] = $b + $l['iva_cents'];
            $base[$l['tax_code']] += $b;
            $disc += (int) $l['discount_cents'];
        }
        unset($l);
        $iva = [];
        foreach (['G', 'R', 'A'] as $k) {
            $iva[$k] = Money::divRound($base[$k] * $bp[$k], 10000);
        }
        $subtotal = array_sum($base);
        $total = $subtotal + array_sum($iva);
        $igtfBase = 0;
        $igtf = 0;
        if ($foreignPayment && $currency !== 'VES' && !empty($profile['igtf_enabled'])) {
            $igtfBase = $total;
            $igtf = Money::divRound($total * (int) ($profile['igtf_bp'] ?? 300), 10000);
        }
        return [
            'lines' => $lines,
            'totals' => [
                'subtotal_cents' => $subtotal, 'discount_cents' => $disc, 'exempt_cents' => $base['E'],
                'base_general_cents' => $base['G'], 'base_reduced_cents' => $base['R'], 'base_luxury_cents' => $base['A'],
                'iva_general_cents' => $iva['G'], 'iva_reduced_cents' => $iva['R'], 'iva_luxury_cents' => $iva['A'],
                'total_cents' => $total, 'igtf_base_cents' => $igtfBase, 'igtf_cents' => $igtf,
            ],
            'rates' => $bp,
        ];
    }

    /**
     * Equivalentes en bolívares a la tasa BCV. Se convierte cada base y cada IVA, y los totales salen de sumarlos,
     * así el total en Bs cuadra al céntimo con sus partes.
     * @return array<string,int>
     */
    public static function inBs(array $totals, string $currency, ?int $rateMicro): array
    {
        $conv = fn (int $v): int => $currency === 'VES' ? $v : ($rateMicro ? Money::mulRate($v, $rateMicro) : 0);
        $o = [];
        foreach (['base_general_cents', 'base_reduced_cents', 'base_luxury_cents', 'exempt_cents', 'iva_general_cents', 'iva_reduced_cents', 'iva_luxury_cents', 'discount_cents', 'igtf_cents'] as $k) {
            $o[$k] = $conv((int) ($totals[$k] ?? 0));
        }
        $o['subtotal_cents'] = $o['base_general_cents'] + $o['base_reduced_cents'] + $o['base_luxury_cents'] + $o['exempt_cents'];
        $o['total_cents'] = $o['subtotal_cents'] + $o['iva_general_cents'] + $o['iva_reduced_cents'] + $o['iva_luxury_cents'];
        $o['igtf_base_cents'] = (int) ($totals['igtf_base_cents'] ?? 0) > 0 ? $o['total_cents'] : 0;
        return $o;
    }
}
