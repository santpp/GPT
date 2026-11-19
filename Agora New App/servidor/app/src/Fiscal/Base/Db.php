<?php
declare(strict_types=1);

namespace Agora\Fiscal\Base;

use PDO;
use PDOException;

/**
 * Acceso a datos con PDO y consultas preparadas SIEMPRE (nunca se concatena texto del usuario en SQL).
 * Producción: MySQL 8 / MariaDB 10.6+. Pruebas automáticas: SQLite (mismo esquema convertido).
 */
final class Db
{
    private PDO $pdo;
    private string $driver;
    private int $depth = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<int|string,mixed> $params */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $name = is_int($k) ? $k + 1 : (str_starts_with((string) $k, ':') ? $k : ':' . $k);
            $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_INT : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $st->bindValue($name, is_bool($v) ? (int) $v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $r = $this->run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    /** @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function exec(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . self::ident($table) . ' (' . implode(', ', array_map([self::class, 'ident'], $cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $this->run($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where columna => valor (todas con AND)
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(fn ($c) => self::ident($c) . ' = ?', array_keys($data)));
        $cond = implode(' AND ', array_map(fn ($c) => self::ident($c) . ' = ?', array_keys($where)));
        return $this->exec('UPDATE ' . self::ident($table) . " SET {$set} WHERE {$cond}", [...array_values($data), ...array_values($where)]);
    }

    /**
     * Inserta o actualiza (clave única = $conflict). $update: columnas que se reemplazan.
     * @param array<string,mixed> $data
     * @param list<string> $conflict
     * @param list<string> $update
     */
    public function upsert(string $table, array $data, array $conflict, array $update): void
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . self::ident($table) . ' (' . implode(', ', array_map([self::class, 'ident'], $cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        if ($this->isSqlite()) {
            $sql .= ' ON CONFLICT (' . implode(', ', array_map([self::class, 'ident'], $conflict)) . ') DO UPDATE SET ' . implode(', ', array_map(fn ($c) => self::ident($c) . ' = excluded.' . self::ident($c), $update));
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn ($c) => self::ident($c) . ' = VALUES(' . self::ident($c) . ')', $update));
        }
        $this->run($sql, array_values($data));
    }

    /** Suma $by a una columna creando la fila si no existe (contadores de límites) */
    public function upsertIncrement(string $table, array $data, array $conflict, string $column, int $by = 1): void
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . self::ident($table) . ' (' . implode(', ', array_map([self::class, 'ident'], $cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $c = self::ident($column);
        $sql .= $this->isSqlite()
            ? ' ON CONFLICT (' . implode(', ', array_map([self::class, 'ident'], $conflict)) . ") DO UPDATE SET {$c} = {$c} + {$by}"
            : " ON DUPLICATE KEY UPDATE {$c} = {$c} + {$by}";
        $this->run($sql, array_values($data));
    }

    /** Cláusula para bloquear filas dentro de una transacción (SQLite ya bloquea toda la base al escribir) */
    public function forUpdate(): string
    {
        return $this->isSqlite() ? '' : ' FOR UPDATE';
    }

    /**
     * Ejecuta $fn en una transacción. Reintenta si la base detecta un interbloqueo o está ocupada.
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaction(callable $fn, int $retries = 4): mixed
    {
        // Agora New App: la API del ERP ya abre una transacción por solicitud; dentro de ella no se abre otra.
        if ($this->depth > 0 || $this->pdo->inTransaction()) {
            $this->depth++;
            try {
                return $fn();
            } finally {
                $this->depth--;
            }
        }
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                if ($this->isSqlite()) {
                    $this->pdo->exec('BEGIN IMMEDIATE');
                } else {
                    $this->pdo->beginTransaction();
                }
                $this->depth = 1;
                $result = $fn();
                if ($this->isSqlite()) {
                    $this->pdo->exec('COMMIT');
                } else {
                    $this->pdo->commit();
                }
                $this->depth = 0;
                return $result;
            } catch (\Throwable $e) {
                $this->depth = 0;
                try {
                    if ($this->isSqlite()) {
                        $this->pdo->exec('ROLLBACK');
                    } elseif ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                } catch (\Throwable) {
                }
                if ($attempt <= $retries && self::isRetryable($e)) {
                    usleep((int) (random_int(20, 60) * 1000 * $attempt));
                    continue;
                }
                throw $e;
            }
        }
    }

    public static function isRetryable(\Throwable $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }
        $info = $e->errorInfo ?? [];
        $code = (int) ($info[1] ?? 0);
        $msg = $e->getMessage();
        // 1205/1213 bloqueo o interbloqueo · 1040/1203 sin conexiones libres · 2002/2006/2013 la base no responde o se cortó
        return in_array($code, [1205, 1213, 1040, 1203, 2002, 2006, 2013], true) || ($info[0] ?? '') === '40001'
            || str_contains($msg, 'database is locked') || str_contains($msg, 'SQLITE_BUSY');
    }

    /** ¿Violación de clave única? (referencia de pago repetida, número ya usado…) */
    public static function isDuplicate(\Throwable $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }
        $info = $e->errorInfo ?? [];
        return ((int) ($info[1] ?? 0)) === 1062 || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }

    /** Mensaje de un disparador de la base (SIGNAL / RAISE) */
    public static function triggerMessage(\Throwable $e): ?string
    {
        $m = $e->getMessage();
        foreach (['DOCUMENTO_FISCAL_INMUTABLE', 'NUMERO_CONTROL_INMUTABLE', 'TRANSICION_INVALIDA', 'COBRO_INMUTABLE', 'AUDITORIA_INMUTABLE'] as $code) {
            if (str_contains($m, $code)) {
                return $code;
            }
        }
        return null;
    }

    public static function ident(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Identificador SQL no permitido');
        }
        return $name;
    }
}
