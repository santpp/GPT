<?php
declare(strict_types=1);

namespace Agora\Fiscal\Base;



/** Validación y limpieza de entradas. Todo lo que llega del navegador pasa por aquí antes de la base de datos. */
final class Validator
{
    /** Texto: quita caracteres de control, normaliza Unicode (NFC) y recorta. */
    public static function clean(mixed $v, int $max = 500, bool $multiline = false): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s;
        }
        $s = preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $s) ?? '';
        $s = trim($s);
        return mb_substr($s, 0, $max);
    }

    public static function str(array $d, string $key, string $label, int $min = 1, int $max = 200, bool $required = true): ?string
    {
        $v = self::clean($d[$key] ?? '', $max + 1);
        if ($v === '') {
            if ($required) {
                throw HttpError::badRequest("Falta {$label}.", ['campo' => $key]);
            }
            return null;
        }
        if (mb_strlen($v) < $min) {
            throw HttpError::badRequest("{$label}: mínimo {$min} caracteres.", ['campo' => $key]);
        }
        if (mb_strlen($v) > $max) {
            throw HttpError::badRequest("{$label}: máximo {$max} caracteres.", ['campo' => $key]);
        }
        return $v;
    }

    public static function email(mixed $v): ?string
    {
        $s = strtolower(self::clean($v, 191));
        return ($s !== '' && strlen($s) <= 190 && filter_var($s, FILTER_VALIDATE_EMAIL)) ? $s : null;
    }

    /** RIF o cédula: J-12345678-9, V-12345678, E-…, P-…, G-…, C-… (se normaliza con guiones) */
    public static function rif(mixed $v): ?string
    {
        $s = strtoupper(preg_replace('/[\s.]/', '', self::clean($v, 30)) ?? '');
        if (!preg_match('/^([VEJPGC])-?(\d{5,9})-?(\d)?$/', $s, $m)) {
            return null;
        }
        $num = $m[2];
        if (($m[3] ?? '') === '' ) {
            return strlen($num) <= 9 ? $m[1] . '-' . $num : null;
        }
        return $m[1] . '-' . str_pad($num, 8, '0', STR_PAD_LEFT) . '-' . $m[3];
    }

    public static function date(mixed $v): ?string
    {
        $s = self::clean($v, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $s));
        return checkdate($m, $d, $y) ? $s : null;
    }

    public static function period(mixed $v): ?string
    {
        $s = self::clean($v, 7);
        return preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $s) ? $s : null;
    }

    /** Monto a céntimos (entero). Acepta 1234.5, "1234.50" o "1.234,50". */
    public static function cents(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v * 100;
        }
        if (is_float($v)) {
            return is_finite($v) && abs($v) < 1e13 ? (int) round($v * 100) : null;
        }
        $s = self::clean($v, 30);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s) || preg_match('/^-?\d+,\d+$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }
        $f = (float) $s;
        return abs($f) < 1e13 ? (int) round($f * 100) : null;
    }

    public static function rate(mixed $v): ?float
    {
        $c = is_numeric($v) ? (float) $v : null;
        if ($c === null && is_string($v)) {
            $s = str_replace(['.', ','], ['', '.'], $v);
            $c = is_numeric($s) ? (float) $s : null;
        }
        return ($c !== null && $c > 0 && $c < 1e9) ? round($c, 6) : null;
    }

    /** @param list<string> $allowed */
    public static function enum(mixed $v, array $allowed, string $label): string
    {
        $s = strtoupper(self::clean($v, 40));
        if (!in_array($s, $allowed, true)) {
            throw HttpError::badRequest("{$label} no es válido.");
        }
        return $s;
    }
}
