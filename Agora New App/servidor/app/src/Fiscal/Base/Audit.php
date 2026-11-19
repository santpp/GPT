<?php
declare(strict_types=1);
namespace Agora\Fiscal\Base;
/** Agora New App · la auditoría fiscal se escribe en la auditoría del ERP (agora_audit) mediante la función que entrega el controlador. */
final class Audit
{
    /** @param \Closure(string,string,?string,?array):void $write  (acción, texto, entidad, detalle) */
    public function __construct(private \Closure $write) {}
    public function log(?int $orgId, array $actor, string $module, string $action, ?string $entity = null, ?string $entityId = null, ?array $detail = null): void
    {
        ($this->write)($action, $entity ?? $module, $entityId, $detail);
    }
}
