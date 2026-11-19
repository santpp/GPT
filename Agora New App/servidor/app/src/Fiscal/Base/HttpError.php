<?php
declare(strict_types=1);

namespace Agora\Fiscal\Base;

/** Error controlado: código HTTP + identificador estable + mensaje en español para la persona. */
final class HttpError extends \RuntimeException
{
    /** @param array<string,mixed> $extra */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $msg, array $extra = []): self
    {
        return new self(422, 'DATOS_INVALIDOS', $msg, $extra);
    }

    public static function forbidden(string $msg = 'No tienes permiso para hacer esto.'): self
    {
        return new self(403, 'SIN_PERMISO', $msg);
    }

    public static function notFound(string $msg = 'No encontrado.'): self
    {
        return new self(404, 'NO_ENCONTRADO', $msg);
    }
}
