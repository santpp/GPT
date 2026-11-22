<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);

namespace Agora\Fiscal\Base;

/** Hora del servidor siempre en UTC; la fecha "de negocio" se calcula en la zona de Venezuela. */
final class Clock
{
    private static ?int $fixedMs = null;

    /** Solo para pruebas */
    public static function freeze(?int $unixMs): void
    {
        self::$fixedMs = $unixMs;
    }

    public static function advance(int $seconds): void
    {
        self::$fixedMs = (self::$fixedMs ?? self::realMs()) + $seconds * 1000;
    }

    private static function realMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public static function ms(): int
    {
        return self::$fixedMs ?? self::realMs();
    }

    public static function unix(): int
    {
        return intdiv(self::ms(), 1000);
    }

    public static function now(): \DateTimeImmutable
    {
        $ms = self::ms();
        $dt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%06d', intdiv($ms, 1000), ($ms % 1000) * 1000), new \DateTimeZone('UTC'));
        return $dt ?: new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /** Marca de tiempo para la base de datos: 2026-10-05 21:14:03.512 (UTC) */
    public static function ts(int $plusSeconds = 0): string
    {
        $d = $plusSeconds ? self::now()->modify(($plusSeconds >= 0 ? '+' : '') . $plusSeconds . ' seconds') : self::now();
        return $d->format('Y-m-d H:i:s.v');
    }

    public static function localNow(string $tz = 'America/Caracas'): \DateTimeImmutable
    {
        return self::now()->setTimezone(new \DateTimeZone($tz));
    }

    /** Fecha de hoy en Venezuela (AAAA-MM-DD) */
    public static function today(string $tz = 'America/Caracas'): string
    {
        return self::localNow($tz)->format('Y-m-d');
    }

    public static function isPast(?string $ts): bool
    {
        return $ts !== null && $ts !== '' && strcmp($ts, self::ts()) < 0;
    }
}
