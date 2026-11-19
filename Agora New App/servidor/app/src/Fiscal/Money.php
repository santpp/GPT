<?php
declare(strict_types=1);

namespace Agora\Fiscal;

use Agora\Fiscal\Base\HttpError;

/**
 * Montos en céntimos (enteros) y tasas en millonésimas: sin decimales flotantes en las cuentas fiscales.
 * Redondeo: mitad hacia arriba (lo usual en la facturación venezolana).
 */
final class Money
{
    /** Divide redondeando la mitad hacia arriba (a ≥ 0, b > 0) */
    public static function divRound(int $a, int $b): int
    {
        if ($a < 0) {
            return -self::divRound(-$a, $b);
        }
        return intdiv($a + intdiv($b, 2), $b);
    }

    /**
     * Monto estricto: número JSON o texto con punto decimal ("1234.56"). Nada de separadores de miles,
     * para que "120.500" no se lea como 120 mil.
     */
    public static function parse(mixed $v, string $label, int $decimals = 2, int $max = 10_000_000_000): int
    {
        $scale = 10 ** $decimals;
        if (is_int($v)) {
            $n = $v * $scale;
        } elseif (is_float($v) && is_finite($v)) {
            $n = (int) round($v * $scale);
        } elseif (is_string($v) && preg_match('/^\s*(\d{1,15})(?:\.(\d{1,' . $decimals . '}))?\s*$/', $v, $m)) {
            $n = (int) $m[1] * $scale + (int) str_pad($m[2] ?? '', $decimals, '0');
        } else {
            throw HttpError::badRequest("{$label}: escribe un número válido (con punto para los decimales).");
        }
        if ($n < 0 || $n > $max) {
            throw HttpError::badRequest("{$label}: el valor está fuera del rango permitido.");
        }
        return $n;
    }

    /** Tasa (Bs por unidad) a millonésimas: "197.1234" → 197123400 */
    public static function rateMicro(string|float|int $rate): int
    {
        $s = is_string($rate) ? trim($rate) : sprintf('%.6F', (float) $rate);
        if (!preg_match('/^(\d{1,9})(?:\.(\d{1,12}))?$/', $s, $m)) {
            throw new \InvalidArgumentException('Tasa no válida: ' . $s);
        }
        $frac = substr(str_pad($m[2] ?? '', 7, '0'), 0, 7); // 7 cifras para redondear a 6
        $micro = (int) $m[1] * 1_000_000 + intdiv((int) $frac + 5, 10);
        return $micro;
    }

    /** céntimos × tasa → céntimos (sin desbordar: se separa la parte entera de la tasa) */
    public static function mulRate(int $cents, int $rateMicro): int
    {
        $neg = $cents < 0;
        $c = abs($cents);
        $int = intdiv($rateMicro, 1_000_000);
        $frac = $rateMicro % 1_000_000;
        $r = $c * $int + self::divRound($c * $frac, 1_000_000);
        return $neg ? -$r : $r;
    }

    /** céntimos ÷ tasa → céntimos (bolívares a dólares) */
    public static function divRate(int $cents, int $rateMicro): int
    {
        if ($rateMicro <= 0) {
            throw new \InvalidArgumentException('Tasa en cero');
        }
        $neg = $cents < 0;
        $r = self::divRound(abs($cents) * 1_000_000, $rateMicro);
        return $neg ? -$r : $r;
    }

    /** 123456 → "1.234,56" (formato venezolano) */
    public static function fmt(int $cents): string
    {
        $neg = $cents < 0;
        $c = abs($cents);
        return ($neg ? '-' : '') . number_format(intdiv($c, 100), 0, ',', '.') . ',' . str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function fmtRate(int $micro, int $decimals = 4): string
    {
        $int = intdiv($micro, 1_000_000);
        $frac = $micro % 1_000_000;
        $fr = str_pad((string) $frac, 6, '0', STR_PAD_LEFT);
        $fr = substr($fr, 0, $decimals);
        return number_format($int, 0, ',', '.') . ($decimals > 0 ? ',' . $fr : '');
    }

    public static function fmtQty(int $milli): string
    {
        $int = intdiv($milli, 1000);
        $f = $milli % 1000;
        if ($f === 0) {
            return number_format($int, 0, ',', '.');
        }
        return number_format($int, 0, ',', '.') . ',' . rtrim(str_pad((string) $f, 3, '0', STR_PAD_LEFT), '0');
    }

    public static function toFloat(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
