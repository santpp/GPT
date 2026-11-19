<?php
declare(strict_types=1);
namespace Agora\Fiscal\Base;
/** Agora New App · utilidades de fecha que el módulo fiscal usaba de mi.agora. */
final class App
{
    public static function iso(?string $ts): ?string
    {
        if ($ts === null || $ts === '') return null;
        return str_replace(' ', 'T', substr($ts, 0, 23)) . (strlen($ts) > 10 ? 'Z' : '');
    }
}
