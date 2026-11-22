<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Fiscal;
/**
 * Agora New App · disparadores que impiden cambiar o borrar lo emitido, aunque alguien entre directo a la base (traídos de mi.agora).
 * El instalador del ERP solo ejecuta DDL simple; estos se crean aquí. Si el usuario de la base no tiene permiso para crearlos
 * (pasa en algunos hostings con registro binario), la inmutabilidad queda en la aplicación y en la cadena de huellas, y estado() lo dice.
 */
final class Esquema
{
    public const NOMBRES = ["trg_afdoc_before_update", "trg_afdoc_before_delete", "trg_afline_before_insert", "trg_afline_before_update", "trg_afline_before_delete"];

    /** @return array{ok:bool,faltan:list<string>,error:?string} */
    public static function instalar(\PDO $pdo): array
    {
        $faltan = self::faltan($pdo);
        $error = null;
        foreach (self::SQL as $nombre => $sql) {
            if (!in_array($nombre, $faltan, true)) continue;
            try { $pdo->exec($sql); } catch (\Throwable $e) { $error = $e->getMessage(); break; }
        }
        $faltan = self::faltan($pdo);
        return ['ok' => !$faltan, 'faltan' => $faltan, 'error' => $faltan ? $error : null];
    }

    /** @return list<string> */
    public static function faltan(\PDO $pdo): array
    {
        try {
            $hay = $pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE 'trg\\_af%'")->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable) { $hay = []; }
        return array_values(array_diff(self::NOMBRES, $hay));
    }

    private const SQL = [
        'trg_afdoc_before_update' => <<<'SQL'
CREATE TRIGGER trg_afdoc_before_update BEFORE UPDATE ON agora_fiscal_documents FOR EACH ROW
BEGIN
  IF OLD.status <> 'BORRADOR' AND (
       NOT (OLD.org_id <=> NEW.org_id) OR NOT (OLD.doc_type <=> NEW.doc_type) OR NOT (OLD.series <=> NEW.series)
    OR NOT (OLD.number <=> NEW.number) OR NOT (OLD.number_text <=> NEW.number_text)
    OR NOT (OLD.issue_date <=> NEW.issue_date) OR NOT (OLD.issue_time <=> NEW.issue_time) OR NOT (OLD.due_date <=> NEW.due_date)
    OR NOT (OLD.currency <=> NEW.currency) OR NOT (OLD.rate_bcv <=> NEW.rate_bcv) OR NOT (OLD.rate_bcv_date <=> NEW.rate_bcv_date)
    OR NOT (OLD.client_name <=> NEW.client_name) OR NOT (OLD.client_rif <=> NEW.client_rif) OR NOT (OLD.client_address <=> NEW.client_address)
    OR NOT (OLD.subtotal_cents <=> NEW.subtotal_cents) OR NOT (OLD.exempt_cents <=> NEW.exempt_cents)
    OR NOT (OLD.base_general_cents <=> NEW.base_general_cents) OR NOT (OLD.base_reduced_cents <=> NEW.base_reduced_cents) OR NOT (OLD.base_luxury_cents <=> NEW.base_luxury_cents)
    OR NOT (OLD.iva_general_cents <=> NEW.iva_general_cents) OR NOT (OLD.iva_reduced_cents <=> NEW.iva_reduced_cents) OR NOT (OLD.iva_luxury_cents <=> NEW.iva_luxury_cents)
    OR NOT (OLD.total_cents <=> NEW.total_cents) OR NOT (OLD.total_bs_cents <=> NEW.total_bs_cents)
    OR NOT (OLD.igtf_base_cents <=> NEW.igtf_base_cents) OR NOT (OLD.igtf_cents <=> NEW.igtf_cents)
    OR NOT (OLD.foreign_payment <=> NEW.foreign_payment) OR NOT (OLD.client_app_id <=> NEW.client_app_id)
    OR NOT (OLD.affected_document_id <=> NEW.affected_document_id) OR NOT (OLD.affected_number <=> NEW.affected_number)
    OR NOT (OLD.fiscal_snapshot <=> NEW.fiscal_snapshot) OR NOT (OLD.prev_hash <=> NEW.prev_hash)
    OR NOT (OLD.emitted_at <=> NEW.emitted_at) OR NOT (OLD.emitted_by <=> NEW.emitted_by)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
  IF OLD.status <> 'BORRADOR' AND NOT (OLD.hash <=> NEW.hash) AND NOT (OLD.control_number IS NULL AND NEW.control_number IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
  IF OLD.status <> 'BORRADOR' AND NOT (OLD.representation_html <=> NEW.representation_html) AND NOT (OLD.control_number IS NULL AND NEW.control_number IS NOT NULL) AND NOT (OLD.status = 'EMITIDA' AND NEW.status = 'ANULADA') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
  IF OLD.status <> 'BORRADOR' AND NOT (OLD.control_assigned_at <=> NEW.control_assigned_at) AND NOT (OLD.control_number IS NULL AND NEW.control_number IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
  IF OLD.control_number IS NOT NULL AND NOT (OLD.control_number <=> NEW.control_number) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NUMERO_CONTROL_INMUTABLE';
  END IF;
  IF NOT (OLD.status <=> NEW.status) AND NOT ((OLD.status = 'BORRADOR' AND NEW.status = 'EMITIDA') OR (OLD.status = 'EMITIDA' AND NEW.status = 'ANULADA')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'TRANSICION_INVALIDA';
  END IF;
END
SQL,
        'trg_afdoc_before_delete' => <<<'SQL'
CREATE TRIGGER trg_afdoc_before_delete BEFORE DELETE ON agora_fiscal_documents FOR EACH ROW
BEGIN
  IF OLD.status <> 'BORRADOR' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
END
SQL,
        'trg_afline_before_insert' => <<<'SQL'
CREATE TRIGGER trg_afline_before_insert BEFORE INSERT ON agora_fiscal_lines FOR EACH ROW
BEGIN
  IF (SELECT status FROM agora_fiscal_documents WHERE id = NEW.document_id) <> 'BORRADOR' AND (SELECT legacy FROM agora_fiscal_documents WHERE id = NEW.document_id) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
END
SQL,
        'trg_afline_before_update' => <<<'SQL'
CREATE TRIGGER trg_afline_before_update BEFORE UPDATE ON agora_fiscal_lines FOR EACH ROW
BEGIN
  IF (SELECT status FROM agora_fiscal_documents WHERE id = OLD.document_id) <> 'BORRADOR' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
END
SQL,
        'trg_afline_before_delete' => <<<'SQL'
CREATE TRIGGER trg_afline_before_delete BEFORE DELETE ON agora_fiscal_lines FOR EACH ROW
BEGIN
  IF (SELECT status FROM agora_fiscal_documents WHERE id = OLD.document_id) <> 'BORRADOR' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCUMENTO_FISCAL_INMUTABLE';
  END IF;
END
SQL,
    ];

    /** 2.12.0 · Columnas nuevas de los documentos, solo si faltan (MySQL 8 no admite «ADD COLUMN IF NOT EXISTS»). */
    public static function columnas(\PDO $pdo): void
    {
        $tiene = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agora_fiscal_documents'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!$tiene) return;
        if (!in_array('ref_json', $tiene, true)) $pdo->exec('ALTER TABLE agora_fiscal_documents ADD COLUMN ref_json TEXT NULL');
        if (!in_array('due_time', $tiene, true)) $pdo->exec('ALTER TABLE agora_fiscal_documents ADD COLUMN due_time TIME NULL');
    }
}
