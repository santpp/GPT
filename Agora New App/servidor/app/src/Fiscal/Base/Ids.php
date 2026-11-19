<?php
declare(strict_types=1);

namespace Agora\Fiscal\Base;

/** Identificadores públicos (ULID), tokens aleatorios y huellas. */
final class Ids
{
    private const B32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** ULID: 26 caracteres, ordenable por fecha, sin números consecutivos adivinables */
    public static function ulid(): string
    {
        $ms = Clock::ms();
        $time = '';
        for ($i = 0; $i < 10; $i++) {
            $time = self::B32[$ms % 32] . $time;
            $ms = intdiv($ms, 32);
        }
        $rand = '';
        $bytes = random_bytes(16);
        for ($i = 0; $i < 16; $i++) {
            $rand .= self::B32[ord($bytes[$i]) % 32];
        }
        return $time . $rand;
    }

    /** Token aleatorio en base64url (32 bytes = 256 bits por defecto) */
    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function sha256(string $s): string
    {
        return hash('sha256', $s);
    }

    public static function hmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    /** Código numérico de un solo uso (sin sesgo de módulo) */
    public static function numericCode(int $digits = 6): string
    {
        $max = 10 ** $digits;
        return str_pad((string) random_int(0, $max - 1), $digits, '0', STR_PAD_LEFT);
    }
}
