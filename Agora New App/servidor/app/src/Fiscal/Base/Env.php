<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Fiscal\Base;
/** Agora New App · claves del módulo fiscal: salen de la app_key del ERP (nunca se guardan aparte). */
final class Env
{
    private static string $key = '';
    public static function setKey(string $appKey): void { self::$key = $appKey; }
    public static function secret(string $name): string
    {
        if (self::$key === '') throw new \RuntimeException('Falta la clave de la aplicación.');
        return hash_hmac('sha256', 'agora-fiscal|' . $name, self::$key);
    }
}
