<?php
declare(strict_types=1);

/**
 * Pausa de mantenimiento. Quien es propietario (cuenta nativa administradora) la activa desde
 * «Mi cuenta»; mientras dure, el resto de las personas ve la pantalla de mantenimiento y el
 * propietario sigue entrando con normalidad. El estado vive en app_state, no en un archivo.
 */
final class Maintenance
{
    private const KEY = 'maintenance';

    public static function on(): bool
    {
        $raw = query('SELECT value FROM app_state WHERE name=?', [self::KEY])->fetchColumn();
        if ($raw === false) return false;
        $state = json_decode((string)$raw, true);
        return is_array($state) && ($state['on'] ?? false) === true;
    }

    public static function state(): array
    {
        $raw = query('SELECT value FROM app_state WHERE name=?', [self::KEY])->fetchColumn();
        $state = $raw === false ? null : json_decode((string)$raw, true);
        return ['on' => is_array($state) && ($state['on'] ?? false) === true, 'since' => is_array($state) ? ($state['since'] ?? null) : null];
    }

    public static function set(bool $on): array
    {
        $value = json_encode(['on' => $on, 'since' => $on ? gmdate('c') : null], JSON_THROW_ON_ERROR);
        query('INSERT INTO app_state(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)', [self::KEY, $value]);
        return self::state();
    }

    /** Sólo el propietario (cuenta nativa administradora) pasa durante la pausa. */
    public static function passes(array $actor): bool
    {
        return ($actor['identity_type'] ?? 'native') === 'native' && ($actor['role'] ?? '') === 'administrator';
    }
}
