<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);

namespace Agora\Fiscal;

use Agora\Fiscal\Base\App;
use Agora\Fiscal\Base\Clock;
use Agora\Fiscal\Base\Db;
use Agora\Fiscal\Base\Env;
use Agora\Fiscal\Base\Ids;
use Agora\Fiscal\Base\HttpError;
use Agora\Fiscal\Base\Validator;
use Agora\Fiscal\Base\Audit;


/**
 * Documentos fiscales (facturas, notas de crédito y de débito) según la norma venezolana:
 *  · número correlativo único y SIN SALTOS por tipo y serie (se asigna al emitir, dentro de la misma transacción);
 *  · número de control: de los rangos de la imprenta (forma libre) o el que asigna la imprenta digital (Prov. 102);
 *  · fecha de emisión = hoy (no se puede antedatar); equivalente en Bs a la tasa BCV;
 *  · lo emitido no se edita ni se borra (disparadores en la base): se anula (con código) o se corrige con una nota;
 *  · cada documento queda sellado con un HMAC encadenado al anterior (se puede verificar).
 */
final class Documents
{
    private const TITLES = ['FAC' => 'Factura', 'NC' => 'Nota de crédito', 'ND' => 'Nota de débito', 'SIM' => 'Factura simple'];

    public function __construct(private Db $db, private Audit $audit)
    {
    }

    /** Agora New App · emisor de la factura simple: los datos fiscales si los hay; si no, el nombre de la subcuenta. */
    private function simpleIssuer(int $orgId): array
    {
        $p = $this->profile($orgId);
        $name = (string) ($this->db->value('SELECT name FROM agora_tenants WHERE id = ?', [$orgId]) ?? '');
        return ($p ?? []) + $this->profileOrDefault($orgId) + ['legal_name' => $name, 'rif' => '', 'fiscal_address' => '', 'phone' => null, 'email' => null, 'special_taxpayer' => 0,
            'printer_name' => null, 'printer_rif' => null, 'printer_authorization' => null, 'printer_authorization_date' => null, 'legends' => null];
    }

    // ================================================================== perfil fiscal
    /** @return array<string,mixed>|null */
    public function profile(int $orgId): ?array
    {
        return $this->db->one('SELECT * FROM agora_fiscal_profiles WHERE org_id = ?', [$orgId]);
    }

    public function profileOut(int $orgId): array
    {
        $p = $this->profile($orgId);
        $missing = $p ? $this->missing($p) : ['todo'];
        return [
            'configurado' => $p !== null, 'completo' => !$missing, 'faltan' => $missing,
            'razon_social' => $p['legal_name'] ?? '', 'rif' => $p['rif'] ?? '', 'domicilio' => $p['fiscal_address'] ?? '', 'telefono' => $p['phone'] ?? '', 'correo' => $p['email'] ?? '',
            'contribuyente_especial' => !empty($p['special_taxpayer']), 'modo' => $p['issuance_mode'] ?? 'IMPRENTA_DIGITAL', 'serie' => $p['series'] ?? '',
            'imprenta' => ['nombre' => $p['printer_name'] ?? '', 'rif' => $p['printer_rif'] ?? '', 'providencia' => $p['printer_authorization'] ?? '', 'fecha' => $p['printer_authorization_date'] ?? null],
            'iva' => ['general' => (int) ($p['iva_general_bp'] ?? 1600) / 100, 'reducida' => (int) ($p['iva_reduced_bp'] ?? 800) / 100, 'adicional_lujo' => (int) ($p['iva_luxury_bp'] ?? 1500) / 100],
            'igtf' => ['activo' => !empty($p['igtf_enabled']), 'alicuota' => (int) ($p['igtf_bp'] ?? 300) / 100],
            'leyendas' => $p['legends'] ?? '',
            'emitidos' => (int) $this->db->value("SELECT COUNT(*) FROM agora_fiscal_documents WHERE org_id = ? AND status <> 'BORRADOR' AND legacy = 0", [$orgId]),
        ];
    }

    /** @return list<string> datos que faltan para poder emitir */
    private function missing(array $p): array
    {
        $out = [];
        if (mb_strlen(trim((string) $p['legal_name'])) < 2) {
            $out[] = 'razón social';
        }
        if (!Validator::rif($p['rif'])) {
            $out[] = 'RIF';
        }
        if (mb_strlen(trim((string) $p['fiscal_address'])) < 5) {
            $out[] = 'domicilio fiscal';
        }
        if (trim((string) $p['printer_name']) === '' || trim((string) $p['printer_rif']) === '' || trim((string) $p['printer_authorization']) === '') {
            $out[] = $p['issuance_mode'] === 'FORMA_LIBRE' ? 'datos de la imprenta autorizada' : 'datos de la imprenta digital';
        }
        return $out;
    }

    public function saveProfile(int $orgId, array $d, int $userId): array
    {
        $rif = Validator::rif($d['rif'] ?? '');
        if (!$rif || !preg_match('/^[VEJPGC]-\d{8}-\d$/', $rif)) {
            throw HttpError::badRequest('El RIF de la empresa debe tener la forma J-12345678-9.');
        }
        $mode = Validator::enum($d['modo'] ?? 'IMPRENTA_DIGITAL', ['IMPRENTA_DIGITAL', 'FORMA_LIBRE'], 'Modo de emisión');
        $series = strtoupper(trim((string) ($d['serie'] ?? '')));
        if ($series !== '' && !preg_match('/^[A-Z]{1,4}$/', $series)) {
            throw HttpError::badRequest('La serie, si la usas, son de 1 a 4 letras (por ejemplo A).');
        }
        $imp = is_array($d['imprenta'] ?? null) ? $d['imprenta'] : [];
        $prif = trim((string) ($imp['rif'] ?? '')) !== '' ? Validator::rif($imp['rif']) : null;
        if (trim((string) ($imp['rif'] ?? '')) !== '' && !$prif) {
            throw HttpError::badRequest('El RIF de la imprenta no es válido.');
        }
        $bp = function (mixed $v, int $def, string $label): int {
            if ($v === null || $v === '') {
                return $def;
            }
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 100) {
                throw HttpError::badRequest("{$label}: escribe un porcentaje entre 0 y 100.");
            }
            return (int) round((float) $v * 100);
        };
        $iva = is_array($d['iva'] ?? null) ? $d['iva'] : [];
        $igtf = is_array($d['igtf'] ?? null) ? $d['igtf'] : [];
        $row = [
            'org_id' => $orgId,
            'legal_name' => Validator::str($d, 'razon_social', 'La razón social', 2, 200),
            'rif' => $rif,
            'fiscal_address' => Validator::str($d, 'domicilio', 'El domicilio fiscal', 5, 400),
            'phone' => Validator::clean($d['telefono'] ?? '', 40) ?: null,
            'email' => isset($d['correo']) && $d['correo'] !== '' ? (Validator::email($d['correo']) ?? throw HttpError::badRequest('El correo de la empresa no es válido.')) : null,
            'special_taxpayer' => !empty($d['contribuyente_especial']) ? 1 : 0,
            'issuance_mode' => $mode,
            'series' => $series,
            'printer_name' => Validator::clean($imp['nombre'] ?? '', 200) ?: null,
            'printer_rif' => $prif,
            'printer_authorization' => Validator::clean($imp['providencia'] ?? '', 80) ?: null,
            'printer_authorization_date' => isset($imp['fecha']) && $imp['fecha'] !== '' ? (Validator::date($imp['fecha']) ?? throw HttpError::badRequest('La fecha de la providencia no es válida.')) : null,
            'iva_general_bp' => $bp($iva['general'] ?? null, 1600, 'IVA general'),
            'iva_reduced_bp' => $bp($iva['reducida'] ?? null, 800, 'IVA reducido'),
            'iva_luxury_bp' => $bp($iva['adicional_lujo'] ?? null, 1500, 'Alícuota adicional'),
            'igtf_bp' => $bp($igtf['alicuota'] ?? null, 300, 'IGTF'),
            'igtf_enabled' => !empty($igtf['activo']) ? 1 : 0,
            'legends' => Validator::clean($d['leyendas'] ?? '', 600, true) ?: null,
            'updated_at' => Clock::ts(),
            'updated_by' => $userId,
        ];
        $this->db->upsert('agora_fiscal_profiles', $row, ['org_id'], array_values(array_diff(array_keys($row), ['org_id'])));
        return $this->profileOut($orgId);
    }

    // ================================================================== rangos de números de control (forma libre)
    public function ranges(int $orgId): array
    {
        return array_map(fn ($r) => [
            'id' => (int) $r['id'], 'serie' => $r['series'], 'prefijo' => $r['prefix'], 'desde' => (int) $r['range_from'], 'hasta' => (int) $r['range_to'], 'siguiente' => (int) $r['next_value'],
            'disponibles' => max(0, (int) $r['range_to'] - (int) $r['next_value'] + 1), 'imprenta' => $r['printer_name'], 'rif_imprenta' => $r['printer_rif'], 'providencia' => $r['printer_authorization'],
            'fecha' => $r['assigned_date'], 'estado' => $r['status'],
        ], $this->db->all('SELECT * FROM agora_fiscal_ranges WHERE org_id = ? ORDER BY id DESC', [$orgId]));
    }

    public function addRange(int $orgId, array $d, int $userId): array
    {
        $prefix = (string) ($d['prefijo'] ?? '00');
        if (!preg_match('/^\d{2}$/', $prefix)) {
            throw HttpError::badRequest('El prefijo del número de control son 2 dígitos (por ejemplo 00).');
        }
        $from = filter_var($d['desde'] ?? null, FILTER_VALIDATE_INT);
        $to = filter_var($d['hasta'] ?? null, FILTER_VALIDATE_INT);
        if ($from === false || $to === false || $from < 1 || $to < $from || $to > 99999999) {
            throw HttpError::badRequest('Indica el rango de números de control (desde y hasta, de 1 a 99.999.999).');
        }
        $series = strtoupper(trim((string) ($d['serie'] ?? ($this->profile($orgId)['series'] ?? ''))));
        $overlap = $this->db->value('SELECT COUNT(*) FROM agora_fiscal_ranges WHERE org_id = ? AND prefix = ? AND status <> ? AND range_from <= ? AND range_to >= ?', [$orgId, $prefix, 'ANULADO', $to, $from]);
        if ((int) $overlap > 0) {
            throw new HttpError(409, 'RANGO_SOLAPADO', 'Ese rango se cruza con otro ya registrado.');
        }
        $used = (int) $this->db->value('SELECT COUNT(*) FROM agora_fiscal_documents WHERE org_id = ? AND control_number >= ? AND control_number <= ?', [$orgId, $prefix . '-' . str_pad((string) $from, 8, '0', STR_PAD_LEFT), $prefix . '-' . str_pad((string) $to, 8, '0', STR_PAD_LEFT)]);
        if ($used > 0) {
            throw new HttpError(409, 'RANGO_USADO', 'Ya hay documentos con números de control dentro de ese rango.');
        }
        $id = $this->db->insert('agora_fiscal_ranges', [
            'org_id' => $orgId, 'series' => $series, 'prefix' => $prefix, 'range_from' => $from, 'range_to' => $to, 'next_value' => $from,
            'printer_name' => Validator::str($d, 'imprenta', 'El nombre de la imprenta', 2, 200), 'printer_rif' => Validator::rif($d['rif_imprenta'] ?? '') ?? throw HttpError::badRequest('El RIF de la imprenta no es válido.'),
            'printer_authorization' => Validator::str($d, 'providencia', 'El número de providencia de la imprenta', 2, 80),
            'assigned_date' => Validator::date($d['fecha'] ?? '') ?? throw HttpError::badRequest('Indica la fecha de asignación del rango.'),
            'status' => 'ACTIVO', 'created_at' => Clock::ts(), 'created_by' => $userId,
        ]);
        return ['id' => $id, 'rangos' => $this->ranges($orgId)];
    }

    public function annulRange(int $orgId, int $id): void
    {
        $n = $this->db->exec("UPDATE agora_fiscal_ranges SET status = 'ANULADO' WHERE org_id = ? AND id = ? AND status = 'ACTIVO' AND next_value = range_from", [$orgId, $id]);
        if ($n !== 1) {
            throw new HttpError(409, 'RANGO_EN_USO', 'Solo se anula un rango que no se ha empezado a usar.');
        }
    }

    // ================================================================== borradores
    /** @return array<string,mixed> columnas del documento a partir de lo que manda la app */
    private function draftColumns(int $orgId, array $d): array
    {
        $currency = Validator::enum($d['moneda'] ?? 'USD', ['USD', 'VES', 'EUR'], 'Moneda');
        $cl = is_array($d['cliente'] ?? null) ? $d['cliente'] : [];
        $name = Validator::clean($cl['nombre'] ?? '', 200);
        $rifRaw = Validator::clean($cl['rif'] ?? '', 30);
        $rif = $rifRaw !== '' ? Validator::rif($rifRaw) : '';
        if ($rifRaw !== '' && !$rif) {
            throw HttpError::badRequest('El RIF o la cédula del cliente no es válido (por ejemplo J-12345678-9 o V-12345678).');
        }
        $appId = isset($cl['app_id']) && preg_match('/^[A-Za-z0-9_.-]{1,40}$/', (string) $cl['app_id']) ? (string) $cl['app_id'] : null;
        $clientId = null;
        $due = isset($d['vence']) && $d['vence'] !== '' && $d['vence'] !== null ? (Validator::date($d['vence']) ?? throw HttpError::badRequest('La fecha de vencimiento no es válida.')) : null;
        return [
            'currency' => $currency, 'client_id' => $clientId !== null ? (int) $clientId : null, 'client_app_id' => $appId, 'client_name' => $name, 'client_rif' => (string) $rif,
            'client_address' => Validator::clean($cl['direccion'] ?? '', 400), 'client_email' => isset($cl['correo']) && $cl['correo'] !== '' ? Validator::email($cl['correo']) : null,
            'client_phone' => Validator::clean($cl['telefono'] ?? '', 40) ?: null, 'payment_terms' => Validator::clean($d['condiciones'] ?? '', 120) ?: null,
            'sale_ref' => isset($d['venta']) && preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', (string) $d['venta']) ? (string) $d['venta'] : null,
            'due_date' => $due, 'notes' => Validator::clean($d['notas'] ?? '', 600, true) ?: null, 'foreign_payment' => !empty($d['pago_divisas']) ? 1 : 0,
            // Agora New App 2.12.0: de qué moneda y con qué tasa salieron los precios, y la hora de vencimiento (las pone el controlador, nunca la app).
            'ref_json' => isset($d['_ref']) && is_string($d['_ref']) ? $d['_ref'] : null, 'due_time' => isset($d['_vence_hora']) && $due !== null ? (string) $d['_vence_hora'] : null,
        ];
    }

    /** @param list<array<string,mixed>> $lines */
    private function writeLines(int $docId, array $lines, array $profile, bool $foreign, string $currency): array
    {
        $calc = Calculator::compute($lines, $profile, $foreign, $currency);
        $this->db->exec('DELETE FROM agora_fiscal_lines WHERE document_id = ?', [$docId]);
        foreach ($calc['lines'] as $l) {
            $this->db->insert('agora_fiscal_lines', [
                'document_id' => $docId, 'line_no' => $l['line_no'], 'product_code' => $l['product_code'], 'description' => $l['description'], 'quantity_milli' => $l['quantity_milli'],
                'unit_price_cents' => $l['unit_price_cents'], 'discount_cents' => $l['discount_cents'], 'tax_code' => $l['tax_code'], 'base_cents' => $l['base_cents'],
                'iva_cents' => $l['iva_cents'], 'total_cents' => $l['total_cents'], 'recurring' => $l['recurring'],
            ]);
        }
        return $calc['totals'];
    }

    private static function totalsColumns(array $t): array
    {
        $keys = ['subtotal_cents', 'exempt_cents', 'base_general_cents', 'base_reduced_cents', 'base_luxury_cents', 'iva_general_cents', 'iva_reduced_cents', 'iva_luxury_cents', 'total_cents', 'igtf_base_cents', 'igtf_cents'];
        return array_intersect_key($t, array_flip($keys));
    }

    private function profileOrDefault(int $orgId): array
    {
        return $this->profile($orgId) ?? ['iva_general_bp' => 1600, 'iva_reduced_bp' => 800, 'iva_luxury_bp' => 1500, 'igtf_bp' => 300, 'igtf_enabled' => 0, 'series' => '', 'issuance_mode' => 'IMPRENTA_DIGITAL'];
    }

    public function createDraft(int $orgId, array $d, int $userId): array
    {
        $cols = $this->draftColumns($orgId, $d);
        $lines = Calculator::lines($d['lineas'] ?? null);
        $prof = $this->profileOrDefault($orgId);
        $uid = Ids::ulid();
        $tipo = ($d['tipo'] ?? 'FAC') === 'SIM' ? 'SIM' : 'FAC';
        $this->db->transaction(function () use ($orgId, $cols, $lines, $prof, $uid, $userId, $tipo) {
            $id = $this->db->insert('agora_fiscal_documents', $cols + [
                'uid' => $uid, 'org_id' => $orgId, 'doc_type' => $tipo, 'series' => $tipo === 'SIM' ? '' : (string) ($prof['series'] ?? ''), 'status' => 'BORRADOR', 'legacy' => 0,
                'created_by' => $userId, 'created_at' => Clock::ts(), 'updated_at' => Clock::ts(),
            ]);
            $t = $this->writeLines($id, $lines, $prof, (bool) $cols['foreign_payment'], $cols['currency']);
            $this->db->update('agora_fiscal_documents', self::totalsColumns($t), ['id' => $id]);
        });
        return $this->get($orgId, $uid);
    }

    public function updateDraft(int $orgId, string $uid, array $d): array
    {
        if ($this->row($orgId, $uid)['status'] !== 'BORRADOR') {
            throw new HttpError(409, 'YA_EMITIDA', 'Ese documento ya se emitió: no se puede editar. Usa una nota de crédito o débito.');
        }
        $cols = $this->draftColumns($orgId, $d);
        $lines = Calculator::lines($d['lineas'] ?? null);
        $prof = $this->profileOrDefault($orgId);
        $this->db->transaction(function () use ($orgId, $uid, $cols, $lines, $prof) {
            $doc = $this->row($orgId, $uid, true);
            if ($doc['status'] !== 'BORRADOR') {
                throw new HttpError(409, 'YA_EMITIDA', 'Ese documento ya se emitió: no se puede editar. Usa una nota de crédito o débito.');
            }
            $t = $this->writeLines((int) $doc['id'], $lines, $prof, (bool) $cols['foreign_payment'], $cols['currency']);
            $this->db->update('agora_fiscal_documents', $cols + self::totalsColumns($t) + ['updated_at' => Clock::ts()], ['id' => (int) $doc['id']]);
        });
        return $this->get($orgId, $uid);
    }

    public function deleteDraft(int $orgId, string $uid): void
    {
        $doc = $this->row($orgId, $uid);
        if ($doc['status'] !== 'BORRADOR') {
            throw new HttpError(409, 'YA_EMITIDA', 'Un documento emitido no se borra: se anula (con código) o se corrige con una nota.');
        }
        $this->db->exec("DELETE FROM agora_fiscal_documents WHERE id = ? AND status = 'BORRADOR'", [(int) $doc['id']]);
    }

    /** @return array<string,mixed> */
    private function row(int $orgId, string $uid, bool $lock = false): array
    {
        if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $uid)) {
            throw HttpError::notFound('Ese documento no existe.');
        }
        $doc = $this->db->one('SELECT * FROM agora_fiscal_documents WHERE org_id = ? AND uid = ?' . ($lock ? $this->db->forUpdate() : ''), [$orgId, $uid]);
        if (!$doc) {
            throw HttpError::notFound('Ese documento no existe.');
        }
        return $doc;
    }

    // ================================================================== emisión
    public function emit(int $orgId, string $uid, array $actor): array
    {
        $this->db->transaction(function () use ($orgId, $uid, $actor) {
            $doc = $this->row($orgId, $uid, true);
            if ($doc['status'] !== 'BORRADOR' || !in_array($doc['doc_type'], ['FAC', 'SIM'], true)) {
                throw new HttpError(409, 'YA_EMITIDA', 'Ese documento ya se emitió.');
            }
            $this->emitRow($orgId, $doc, $actor, null);
        });
        return $this->get($orgId, $uid);
    }

    /** Emite un borrador ya bloqueado (factura o nota). Debe correr dentro de una transacción. */
    private function emitRow(int $orgId, array $doc, array $actor, ?string $grantId): void
    {
        $simple = $doc['doc_type'] === 'SIM';
        $prof = $simple ? $this->simpleIssuer($orgId) : $this->profile($orgId);
        if (!$prof) {
            throw new HttpError(409, 'SIN_PERFIL', 'Primero configura los datos fiscales de la empresa (Facturación fiscal → Datos fiscales).');
        }
        if (!$simple && ($missing = $this->missing($prof))) {
            throw new HttpError(409, 'PERFIL_INCOMPLETO', 'Para emitir faltan estos datos fiscales de la empresa: ' . implode(', ', $missing) . '.');
        }
        if (mb_strlen(trim((string) $doc['client_name'])) < 2 || (!$simple && !Validator::rif($doc['client_rif']))) {
            throw HttpError::badRequest('Faltan el nombre y el RIF o la cédula del cliente.');
        }
        if (!$simple && mb_strlen(trim((string) $doc['client_address'])) < 3) {
            throw HttpError::badRequest('Falta el domicilio fiscal del cliente.');
        }
        $lines = $this->db->all('SELECT * FROM agora_fiscal_lines WHERE document_id = ? ORDER BY line_no', [(int) $doc['id']]);
        if (!$lines) {
            throw HttpError::badRequest('El documento no tiene líneas.');
        }
        $today = Clock::today();
        $time = Clock::localNow()->format('H:i:s');
        $due = $doc['due_date'] && strcmp((string) $doc['due_date'], $today) >= 0 ? (string) $doc['due_date'] : $today;
        if ($simple || $doc['currency'] === 'VES') {
            // La factura simple no exige tasa BCV, ni la factura en bolívares (Agora New App 2.11.1): si la hay, queda registrada.
            try { [$rateStr, $rateDay] = $this->bcvRate($orgId, $today); $rateMicro = Money::rateMicro($rateStr); } catch (HttpError) { $rateDay = null; $rateMicro = null; }
        } else {
            [$rateStr, $rateDay] = $this->bcvRate($orgId, $today);
            $rateMicro = Money::rateMicro($rateStr);
        }
        $norm = array_map(fn ($l) => [
            'line_no' => (int) $l['line_no'], 'product_code' => $l['product_code'], 'description' => (string) $l['description'], 'quantity_milli' => (int) $l['quantity_milli'],
            'unit_price_cents' => (int) $l['unit_price_cents'], 'discount_cents' => (int) $l['discount_cents'], 'tax_code' => (string) $l['tax_code'], 'recurring' => (int) $l['recurring'],
            'gross_cents' => Money::divRound((int) $l['quantity_milli'] * (int) $l['unit_price_cents'], 1000),
        ], $lines);
        $calc = Calculator::compute($norm, $prof, (bool) $doc['foreign_payment'] && $doc['doc_type'] === 'FAC', (string) $doc['currency']);
        $t = $calc['totals'];
        if ($t['total_cents'] <= 0) {
            throw HttpError::badRequest('El total del documento debe ser mayor que cero.');
        }
        $bs = Calculator::inBs($t, (string) $doc['currency'], $rateMicro);
        $series = (string) $prof['series'];
        $number = $this->nextNumber($orgId, (string) $doc['doc_type'], $series);
        [$control, $range] = !$simple && $prof['issuance_mode'] === 'FORMA_LIBRE' ? $this->nextControl($orgId, $series) : [null, null];
        $numberText = str_pad((string) $number, 8, '0', STR_PAD_LEFT);
        $legends = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($prof['legends'] ?? '')) ?: [])));
        if ((int) $t['igtf_cents'] > 0) {
            $legends[] = 'IGTF percibido conforme a la Ley de Impuesto a las Grandes Transacciones Financieras (pago en divisas).';
        }
        $affected = null;
        if (!in_array($doc['doc_type'], ['FAC', 'SIM'], true)) {
            $affected = ['numero' => $doc['affected_number'], 'fecha' => $doc['affected_date'], 'total_cents' => (int) $doc['affected_total_cents'],
                'control' => $this->db->value('SELECT control_number FROM agora_fiscal_documents WHERE id = ?', [(int) $doc['affected_document_id']])];
        }
        $snap = [
            'v' => 1, 'tipo' => $doc['doc_type'], 'titulo' => self::TITLES[$doc['doc_type']], 'serie' => $series, 'numero' => $number, 'numero_texto' => $numberText,
            'fecha' => $today, 'hora' => $time, 'vence' => in_array($doc['doc_type'], ['FAC', 'SIM'], true) ? $due : null, 'condiciones' => $doc['payment_terms'],
            'moneda' => $doc['currency'], 'tasa_bcv' => $rateMicro !== null ? self::rateText($rateMicro) : null, 'fecha_tasa' => $rateDay, 'fiscal' => !$simple,
            // Agora New App 2.12.0: hora de vencimiento y de qué moneda/tasa salieron los precios (quedan sellados en la huella).
            'vence_hora' => !empty($doc['due_time']) && $due === (string) $doc['due_date'] ? substr((string) $doc['due_time'], 0, 5) : null,
            'referencia' => !empty($doc['ref_json']) ? json_decode((string) $doc['ref_json'], true) : null,
            'emisor' => ['razon_social' => $prof['legal_name'], 'rif' => $prof['rif'], 'domicilio' => $prof['fiscal_address'], 'telefono' => $prof['phone'], 'correo' => $prof['email'], 'contribuyente_especial' => (bool) $prof['special_taxpayer']],
            'imprenta' => $simple ? null : $range ?? ['modo' => $prof['issuance_mode'], 'nombre' => $prof['printer_name'], 'rif' => $prof['printer_rif'], 'providencia' => $prof['printer_authorization'], 'fecha' => $prof['printer_authorization_date']],
            'cliente' => ['nombre' => $doc['client_name'], 'rif' => Validator::rif($doc['client_rif']), 'domicilio' => $doc['client_address'], 'correo' => $doc['client_email'], 'telefono' => $doc['client_phone'], 'app_id' => $doc['client_app_id']],
            'lineas' => array_map(fn ($l) => [
                'n' => $l['line_no'], 'codigo' => $l['product_code'], 'descripcion' => $l['description'], 'cantidad_milli' => $l['quantity_milli'], 'precio_cents' => $l['unit_price_cents'],
                'descuento_cents' => $l['discount_cents'], 'impuesto' => $l['tax_code'], 'base_cents' => $l['base_cents'], 'iva_cents' => $l['iva_cents'], 'total_cents' => $l['total_cents'],
            ], $calc['lines']),
            'alicuotas' => $calc['rates'], 'igtf_bp' => (int) $prof['igtf_bp'],
            'totales' => $t, 'totales_bs' => $bs,
            'afecta' => $affected, 'motivo' => $doc['reason'], 'notas' => $doc['notes'], 'leyendas' => $legends,
            'emitido_por' => $actor['email'] ?? '', 'emitido_en' => Clock::ts(),
        ];
        $snapJson = (string) json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $prev = (string) ($this->db->value("SELECT hash FROM agora_fiscal_documents WHERE org_id = ? AND doc_type = ? AND series = ? AND legacy = 0 AND status <> 'BORRADOR' AND hash IS NOT NULL ORDER BY number DESC LIMIT 1", [$orgId, $doc['doc_type'], $series]) ?? str_repeat('0', 64));
        $assignedAt = $control ? Clock::ts() : null;
        foreach ($calc['lines'] as $l) {
            $this->db->update('agora_fiscal_lines', ['base_cents' => $l['base_cents'], 'iva_cents' => $l['iva_cents'], 'total_cents' => $l['total_cents']], ['document_id' => (int) $doc['id'], 'line_no' => $l['line_no']]);
        }
        $n = $this->db->exec(
            "UPDATE agora_fiscal_documents SET status = 'EMITIDA', series = ?, number = ?, number_text = ?, control_number = ?, control_assigned_at = ?, issue_date = ?, issue_time = ?, due_date = ?,
                rate_bcv = ?, rate_bcv_date = ?, subtotal_cents = ?, exempt_cents = ?, base_general_cents = ?, base_reduced_cents = ?, base_luxury_cents = ?, iva_general_cents = ?, iva_reduced_cents = ?,
                iva_luxury_cents = ?, total_cents = ?, total_bs_cents = ?, igtf_base_cents = ?, igtf_cents = ?, fiscal_snapshot = ?, representation_html = ?, hash = ?, prev_hash = ?, emitted_by = ?,
                emitted_at = ?, updated_at = ? WHERE id = ? AND status = 'BORRADOR'",
            [$series, $number, $numberText, $control, $assignedAt, $today, $time, $snap['vence'], $rateMicro !== null ? self::rateText($rateMicro) : null, $rateDay, $t['subtotal_cents'], $t['exempt_cents'], $t['base_general_cents'],
                $t['base_reduced_cents'], $t['base_luxury_cents'], $t['iva_general_cents'], $t['iva_reduced_cents'], $t['iva_luxury_cents'], $t['total_cents'], $bs['total_cents'], $t['igtf_base_cents'], $t['igtf_cents'],
                $snapJson, Representation::render($snap, ['estado' => 'EMITIDA', 'control' => $control, 'control_asignado' => $assignedAt]), self::sign($prev, $snapJson, $control, $assignedAt), $prev,
                $actor['user_id'] ?? null, Clock::ts(), Clock::ts(), (int) $doc['id']]
        );
        if ($n !== 1) {
            throw new HttpError(409, 'YA_EMITIDA', 'Ese documento ya se emitió.');
        }
        $sym = $doc['currency'] === 'VES' ? 'Bs.' : 'US$';
        $this->audit->log($orgId, $actor, 'facturacion', sprintf('emitió %s N.° %s%s por %s %s', mb_strtolower(self::TITLES[$doc['doc_type']]), $numberText, $control ? ' (control ' . $control . ')' : ($simple ? '' : ' (control pendiente de la imprenta digital)'), $sym, Money::fmt((int) $t['total_cents'])), 'documento_fiscal', (string) $doc['uid'], ['total_cents' => $t['total_cents'], 'total_bs_cents' => $bs['total_cents'], 'tasa_bcv' => $rateMicro !== null ? self::rateText($rateMicro) : null], 'SERVIDOR', $grantId);
        $this->bump($orgId);
    }

    private static function rateText(int $micro): string
    {
        return intdiv($micro, 1_000_000) . '.' . str_pad((string) ($micro % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public static function sign(string $prev, string $snapJson, ?string $control, ?string $assignedAt): string
    {
        return hash_hmac('sha256', $prev . '|' . $snapJson . '|' . ($control ?? '') . '|' . ($assignedAt ?? ''), Env::secret('AUDIT_KEY'));
    }

    /** Siguiente número correlativo (sin saltos: si la transacción se deshace, el número vuelve) */
    private function nextNumber(int $orgId, string $type, string $series): int
    {
        $upd = fn (): int => $this->db->exec('UPDATE agora_fiscal_sequences SET last_number = last_number + 1, updated_at = ? WHERE org_id = ? AND doc_type = ? AND series = ?', [Clock::ts(), $orgId, $type, $series]);
        if ($upd() === 0) {
            $max = (int) $this->db->value('SELECT COALESCE(MAX(number), 0) FROM agora_fiscal_documents WHERE org_id = ? AND doc_type = ? AND series = ? AND number IS NOT NULL', [$orgId, $type, $series]);
            try {
                $this->db->insert('agora_fiscal_sequences', ['org_id' => $orgId, 'doc_type' => $type, 'series' => $series, 'last_number' => $max + 1, 'updated_at' => Clock::ts()]);
                return $max + 1;
            } catch (\PDOException $e) {
                if (!Db::isDuplicate($e) || $upd() === 0) {
                    throw $e;
                }
            }
        }
        return (int) $this->db->value('SELECT last_number FROM agora_fiscal_sequences WHERE org_id = ? AND doc_type = ? AND series = ?', [$orgId, $type, $series]);
    }

    /** @return array{0:string,1:array<string,mixed>} número de control y datos de la imprenta */
    private function nextControl(int $orgId, string $series): array
    {
        for ($i = 0; $i < 50; $i++) {
            $r = $this->db->one("SELECT * FROM agora_fiscal_ranges WHERE org_id = ? AND series = ? AND status = 'ACTIVO' ORDER BY id LIMIT 1" . $this->db->forUpdate(), [$orgId, $series]);
            if (!$r) {
                throw new HttpError(409, 'SIN_CONTROL', 'No quedan números de control: registra el siguiente rango que te asignó la imprenta (Facturación fiscal → Números de control).');
            }
            $v = (int) $r['next_value'];
            if ($v > (int) $r['range_to']) {
                $this->db->update('agora_fiscal_ranges', ['status' => 'AGOTADO'], ['id' => (int) $r['id']]);
                continue;
            }
            $this->db->update('agora_fiscal_ranges', ['next_value' => $v + 1, 'status' => $v + 1 > (int) $r['range_to'] ? 'AGOTADO' : 'ACTIVO'], ['id' => (int) $r['id']]);
            $fmt = fn (int $x): string => $r['prefix'] . '-' . str_pad((string) $x, 8, '0', STR_PAD_LEFT);
            return [$fmt($v), ['modo' => 'FORMA_LIBRE', 'nombre' => $r['printer_name'], 'rif' => $r['printer_rif'], 'providencia' => $r['printer_authorization'], 'fecha' => $r['assigned_date'], 'rango' => ['desde' => $fmt((int) $r['range_from']), 'hasta' => $fmt((int) $r['range_to'])]]];
        }
        throw new HttpError(409, 'SIN_CONTROL', 'No quedan números de control disponibles.');
    }

    /** @return array{0:string,1:string} tasa BCV (texto con 6 decimales) y su fecha */
    public function bcvRate(int $orgId, string $day): array
    {
        $from = (new \DateTimeImmutable($day))->modify('-5 days')->format('Y-m-d');
        $r = $this->db->one("SELECT day, rate_last FROM agora_fiscal_fx WHERE source = 'BCV' AND day <= ? AND day >= ? ORDER BY day DESC LIMIT 1", [$day, $from]);
        if ($r && (float) $r['rate_last'] > 0) {
            return [self::rateText(Money::rateMicro(is_string($r['rate_last']) ? $r['rate_last'] : sprintf('%.6F', (float) $r['rate_last']))), (string) $r['day']];
        }
        // Agora New App · tasas escritas a mano por la subcuenta (Tasas del día).
        $manual = [];
        foreach ($this->db->all('SELECT day, rate FROM agora_fiscal_tasas WHERE org_id = ? AND day <= ? AND day >= ?', [$orgId, $day, $from]) as $m) $manual[(string) $m['day']] = ['BCV' => (string) $m['rate']];
        if (is_array($manual)) {
            krsort($manual);
            foreach ($manual as $k => $v) {
                if (is_string($k) && strcmp($k, $day) <= 0 && strcmp($k, $from) >= 0 && is_array($v) && isset($v['BCV']) && is_numeric($v['BCV']) && (float) $v['BCV'] > 0) {
                    return [self::rateText(Money::rateMicro(sprintf('%.6F', (float) $v['BCV']))), $k];
                }
            }
        }
        throw new HttpError(409, 'SIN_TASA_BCV', 'No hay una tasa BCV vigente de los últimos 5 días. Espera la actualización automática o escribe la tasa BCV de hoy en Tasas del día.');
    }

    // ================================================================== control de la imprenta digital
    public function assignControl(int $orgId, string $uid, string $control, ?string $assignedAt, array $actor): array
    {
        $control = preg_replace('/\s+/', '', $control) ?? '';
        if (preg_match('/^(\d{2})-?(\d{1,8})$/', $control, $m)) {
            $control = $m[1] . '-' . str_pad($m[2], 8, '0', STR_PAD_LEFT);
        } else {
            throw HttpError::badRequest('El número de control tiene la forma 00-00000123 (2 dígitos, guion y hasta 8 dígitos).');
        }
        $at = $assignedAt ? self::parseStamp($assignedAt) : Clock::ts();
        $this->db->transaction(function () use ($orgId, $uid, $control, $at, $actor) {
            $doc = $this->row($orgId, $uid, true);
            if ($doc['status'] !== 'EMITIDA' || $doc['control_number'] !== null || (int) $doc['legacy'] === 1 || $doc['doc_type'] === 'SIM') {
                throw new HttpError(409, 'CONTROL_NO_APLICA', 'Ese documento ya tiene número de control o no está emitido.');
            }
            if ($this->db->value('SELECT 1 FROM agora_fiscal_documents WHERE org_id = ? AND control_number = ?', [$orgId, $control])) {
                throw new HttpError(409, 'CONTROL_REPETIDO', 'Ese número de control ya está en otro documento.');
            }
            $snap = json_decode((string) $doc['fiscal_snapshot'], true) ?: [];
            $this->db->update('agora_fiscal_documents', [
                'control_number' => $control, 'control_assigned_at' => $at, 'hash' => self::sign((string) $doc['prev_hash'], (string) $doc['fiscal_snapshot'], $control, $at),
                'representation_html' => Representation::render($snap, ['estado' => 'EMITIDA', 'control' => $control, 'control_asignado' => $at]), 'updated_at' => Clock::ts(),
            ], ['id' => (int) $doc['id']]);
            $this->audit->log($orgId, $actor, 'facturacion', 'registró el número de control ' . $control . ' de la imprenta digital para el documento N.° ' . $doc['number_text'], 'documento_fiscal', $uid);
            $this->bump($orgId);
        });
        return $this->get($orgId, $uid);
    }

    /** "05102026 14.32.10" (Prov. 102, hora de Venezuela) o ISO → UTC */
    private static function parseStamp(string $s): string
    {
        $tz = new \DateTimeZone('America/Caracas');
        $d = \DateTimeImmutable::createFromFormat('dmY H.i.s', trim($s), $tz) ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($s), $tz) ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($s), $tz);
        if (!$d) {
            throw HttpError::badRequest('La fecha y hora de asignación del control no es válida (usa DDMMAAAA HH.MM.SS).');
        }
        if ($d->getTimestamp() > Clock::unix() + 300) {
            throw HttpError::badRequest('La fecha de asignación del control no puede ser futura.');
        }
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    // ================================================================== anulación y notas
    public function annul(int $orgId, string $uid, string $reason, array $actor, ?string $grantId): array
    {
        $this->db->transaction(function () use ($orgId, $uid, $reason, $actor, $grantId) {
            $doc = $this->row($orgId, $uid, true);
            if ($doc['status'] !== 'EMITIDA') {
                throw new HttpError(409, 'NO_EMITIDA', 'Solo se anula un documento emitido.');
            }
            if ((int) $doc['legacy'] === 1) {
                throw new HttpError(409, 'IMPORTADO', 'Los documentos importados de la edición local no se anulan aquí: emite una nota de crédito.');
            }
            if ((int) $this->db->value("SELECT COUNT(*) FROM agora_fiscal_documents WHERE org_id = ? AND affected_document_id = ? AND status = 'EMITIDA'", [$orgId, (int) $doc['id']]) > 0) {
                throw new HttpError(409, 'TIENE_NOTAS', 'Ese documento tiene notas de crédito o débito emitidas.');
            }
            $snap = json_decode((string) $doc['fiscal_snapshot'], true) ?: [];
            $now = Clock::ts();
            $this->db->update('agora_fiscal_documents', [
                'status' => 'ANULADA', 'annulled_by' => $actor['user_id'] ?? null, 'annulled_at' => $now, 'annul_reason' => mb_substr($reason, 0, 400), 'updated_at' => $now,
                'representation_html' => Representation::render($snap, ['estado' => 'ANULADA', 'control' => $doc['control_number'], 'control_asignado' => $doc['control_assigned_at'], 'anulada_en' => $now, 'motivo_anulacion' => $reason]),
            ], ['id' => (int) $doc['id']]);
            $this->audit->log($orgId, $actor, 'facturacion', 'anuló ' . mb_strtolower(self::TITLES[$doc['doc_type']]) . ' N.° ' . $doc['number_text'] . ' · motivo: ' . $reason, 'documento_fiscal', $uid, null, 'SERVIDOR', $grantId);
            $this->bump($orgId);
        });
        return $this->get($orgId, $uid);
    }

    public function note(int $orgId, string $affectedUid, string $type, array $d, string $reason, array $actor, ?string $grantId): array
    {
        if (!in_array($type, ['NC', 'ND'], true)) {
            throw HttpError::badRequest('Tipo de nota no válido.');
        }
        $lines = Calculator::lines($d['lineas'] ?? null);
        $uid = Ids::ulid();
        $this->db->transaction(function () use ($orgId, $affectedUid, $type, $lines, $reason, $actor, $grantId, $uid) {
            $inv = $this->row($orgId, $affectedUid, true);
            if ($inv['doc_type'] !== 'FAC' || $inv['status'] !== 'EMITIDA') {
                throw new HttpError(409, 'NO_APLICA', 'Las notas se emiten sobre una factura emitida (no anulada).');
            }
            $prof = $this->profile($orgId) ?? throw new HttpError(409, 'SIN_PERFIL', 'Primero configura los datos fiscales de la empresa.');
            $calc = Calculator::compute($lines, $prof, false, (string) $inv['currency']);
            if ($type === 'NC') {
                $credited = (int) $this->db->value("SELECT COALESCE(SUM(total_cents), 0) FROM agora_fiscal_documents WHERE org_id = ? AND affected_document_id = ? AND doc_type = 'NC' AND status = 'EMITIDA'", [$orgId, (int) $inv['id']]);
                $debited = (int) $this->db->value("SELECT COALESCE(SUM(total_cents), 0) FROM agora_fiscal_documents WHERE org_id = ? AND affected_document_id = ? AND doc_type = 'ND' AND status = 'EMITIDA'", [$orgId, (int) $inv['id']]);
                $room = (int) $inv['total_cents'] + $debited - $credited;
                if ($calc['totals']['total_cents'] > $room) {
                    throw HttpError::badRequest('La nota de crédito supera lo que queda por acreditar de la factura (' . Money::fmt($room) . ').');
                }
            }
            $id = $this->db->insert('agora_fiscal_documents', [
                'uid' => $uid, 'org_id' => $orgId, 'doc_type' => $type, 'series' => (string) $prof['series'], 'status' => 'BORRADOR', 'legacy' => 0, 'currency' => $inv['currency'],
                'client_id' => $inv['client_id'], 'client_app_id' => $inv['client_app_id'], 'client_name' => $inv['client_name'], 'client_rif' => $inv['client_rif'], 'client_address' => $inv['client_address'],
                'client_email' => $inv['client_email'], 'client_phone' => $inv['client_phone'], 'payment_terms' => null, 'foreign_payment' => 0,
                'affected_document_id' => (int) $inv['id'], 'affected_number' => $inv['number_text'], 'affected_date' => $inv['issue_date'], 'affected_total_cents' => (int) $inv['total_cents'],
                'reason' => mb_substr($reason, 0, 400), 'notes' => null, 'created_by' => $actor['user_id'] ?? null, 'created_at' => Clock::ts(), 'updated_at' => Clock::ts(),
            ]);
            $this->writeLines($id, $lines, $prof, false, (string) $inv['currency']);
            $doc = $this->db->one('SELECT * FROM agora_fiscal_documents WHERE id = ?', [$id]);
            $this->emitRow($orgId, $doc ?? [], $actor, $grantId);
        });
        return $this->get($orgId, $uid);
    }

    // ================================================================== consultas
    public static function appId(array $doc): string
    {
        return $doc['doc_type'] . '-' . ($doc['series'] !== '' && $doc['series'] !== null ? $doc['series'] . '-' : '') . ($doc['number_text'] ?? '');
    }

    /** @return array<string,mixed> documento para la app */
    public function out(array $r, bool $withLines = false): array
    {
        $f = fn ($k) => Money::toFloat((int) ($r[$k] ?? 0));
        $o = [
            'uid' => $r['uid'], 'tipo' => $r['doc_type'], 'titulo' => self::TITLES[$r['doc_type']] ?? $r['doc_type'], 'serie' => $r['series'], 'numero' => $r['number'] !== null ? (int) $r['number'] : null,
            'numero_texto' => $r['number_text'], 'id_app' => $r['number_text'] ? self::appId($r) : null, 'control' => $r['control_number'], 'control_asignado' => App::iso($r['control_assigned_at']),
            'control_pendiente' => $r['status'] === 'EMITIDA' && $r['control_number'] === null && (int) $r['legacy'] === 0 && $r['doc_type'] !== 'SIM', 'estado' => $r['status'], 'legado' => (bool) $r['legacy'],
            'fecha' => $r['issue_date'], 'hora' => $r['issue_time'], 'vence' => $r['due_date'], 'moneda' => $r['currency'], 'tasa_bcv' => $r['rate_bcv'] !== null ? (float) $r['rate_bcv'] : null, 'fecha_tasa' => $r['rate_bcv_date'],
            'cliente' => ['app_id' => $r['client_app_id'], 'nombre' => $r['client_name'], 'rif' => $r['client_rif'], 'direccion' => $r['client_address'], 'correo' => $r['client_email'], 'telefono' => $r['client_phone']],
            'vence_hora' => !empty($r['due_time']) ? substr((string) $r['due_time'], 0, 5) : null, 'referencia' => !empty($r['ref_json']) ? json_decode((string) $r['ref_json'], true) : null,
            'condiciones' => $r['payment_terms'], 'notas' => $r['notes'], 'pago_divisas' => (bool) $r['foreign_payment'], 'venta' => $r['sale_ref'] ?? null, 'creado_por' => $r['created_by'] !== null ? (int) $r['created_by'] : null,
            'totales' => [
                'subtotal' => $f('subtotal_cents'), 'exento' => $f('exempt_cents'), 'base_general' => $f('base_general_cents'), 'iva_general' => $f('iva_general_cents'), 'base_reducida' => $f('base_reduced_cents'),
                'iva_reducido' => $f('iva_reduced_cents'), 'base_lujo' => $f('base_luxury_cents'), 'iva_lujo' => $f('iva_luxury_cents'), 'igtf' => $f('igtf_cents'), 'total' => $f('total_cents'),
                'total_bs' => $r['total_bs_cents'] !== null && $r['rate_bcv'] !== null ? $f('total_bs_cents') : null, 'total_con_igtf' => Money::toFloat((int) $r['total_cents'] + (int) $r['igtf_cents']),
            ],
            'afecta' => $r['affected_document_id'] !== null ? ['numero' => $r['affected_number'], 'fecha' => $r['affected_date'], 'total' => $f('affected_total_cents'), 'uid' => $this->db->value('SELECT uid FROM agora_fiscal_documents WHERE id = ?', [(int) $r['affected_document_id']])] : null,
            'motivo' => $r['reason'], 'emitida_en' => App::iso($r['emitted_at']), 'anulada_en' => App::iso($r['annulled_at']), 'motivo_anulacion' => $r['annul_reason'], 'creada' => App::iso((string) $r['created_at']),
        ];
        if ($withLines) {
            $o['lineas'] = array_map(fn ($l) => [
                'n' => (int) $l['line_no'], 'codigo' => $l['product_code'], 'descripcion' => $l['description'], 'cantidad' => (int) $l['quantity_milli'] / 1000, 'precio' => Money::toFloat((int) $l['unit_price_cents']),
                'descuento' => Money::toFloat((int) $l['discount_cents']), 'impuesto' => $l['tax_code'], 'base' => Money::toFloat((int) $l['base_cents']), 'iva' => Money::toFloat((int) $l['iva_cents']),
                'total' => Money::toFloat((int) $l['total_cents']), 'recurrente' => (bool) $l['recurring'],
            ], $this->db->all('SELECT * FROM agora_fiscal_lines WHERE document_id = ? ORDER BY line_no', [(int) $r['id']]));
            $o['notas_emitidas'] = array_map(fn ($n) => ['uid' => $n['uid'], 'tipo' => $n['doc_type'], 'numero' => $n['number_text'], 'fecha' => $n['issue_date'], 'total' => Money::toFloat((int) $n['total_cents']), 'estado' => $n['status']],
                $this->db->all('SELECT uid, doc_type, number_text, issue_date, total_cents, status FROM agora_fiscal_documents WHERE affected_document_id = ? ORDER BY id', [(int) $r['id']]));
        }
        return $o;
    }

    public function get(int $orgId, string $uid): array
    {
        return $this->out($this->row($orgId, $uid), true);
    }

    /** @param array<string,string> $q */
    public function list(int $orgId, array $q): array
    {
        $where = ['org_id = ?'];
        $p = [$orgId];
        if (!empty($q['estado']) && in_array($q['estado'], ['BORRADOR', 'EMITIDA', 'ANULADA'], true)) {
            $where[] = 'status = ?';
            $p[] = $q['estado'];
        }
        if (!empty($q['tipo']) && in_array($q['tipo'], ['FAC', 'NC', 'ND', 'SIM'], true)) {
            $where[] = 'doc_type = ?';
            $p[] = $q['tipo'];
        }
        if (!empty($q['periodo']) && Validator::period($q['periodo'])) {
            $where[] = "((issue_date IS NOT NULL AND issue_date LIKE ?) OR (status = 'BORRADOR' AND created_at LIKE ?))";
            $p[] = $q['periodo'] . '%';
            $p[] = $q['periodo'] . '%';
        }
        if (!empty($q['cliente']) && preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $q['cliente'])) {
            $where[] = 'client_app_id = ?';
            $p[] = $q['cliente'];
        }
        if (!empty($q['buscar'])) {
            $s = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($q['buscar'], 0, 60)) . '%';
            $where[] = '(client_name LIKE ? OR number_text LIKE ? OR control_number LIKE ? OR client_rif LIKE ?)';
            array_push($p, $s, $s, $s, $s);
        }
        // Agora New App · quien solo ve lo suyo ve los documentos que preparó; y los de una venta concreta.
        if (!empty($q['creador'])) {
            $where[] = 'created_by = ?';
            $p[] = (int) $q['creador'];
        }
        if (!empty($q['venta']) && preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', (string) $q['venta'])) {
            $where[] = 'sale_ref LIKE ?';
            $p[] = str_replace(['%', '_'], ['\\%', '\\_'], (string) $q['venta']) . '%';
        }
        if (!empty($q['control_pendiente'])) {
            $where[] = "status = 'EMITIDA' AND control_number IS NULL AND legacy = 0 AND doc_type <> 'SIM'";
        }
        $limit = max(1, min(500, (int) ($q['limite'] ?? 100)));
        $offset = max(0, (int) ($q['desde'] ?? 0));
        $rows = $this->db->all('SELECT * FROM agora_fiscal_documents WHERE ' . implode(' AND ', $where) . " ORDER BY CASE WHEN status = 'BORRADOR' THEN 0 ELSE 1 END, COALESCE(issue_date, '9999-12-31') DESC, number DESC, id DESC LIMIT {$limit} OFFSET {$offset}", $p);
        $total = (int) $this->db->value('SELECT COUNT(*) FROM agora_fiscal_documents WHERE ' . implode(' AND ', $where), $p);
        return ['documentos' => array_map(fn ($r) => $this->out($r), $rows), 'total' => $total];
    }

    public function representation(int $orgId, string $uid, bool $copy): string
    {
        $doc = $this->row($orgId, $uid);
        if ($doc['status'] === 'BORRADOR') {
            return Representation::render($this->draftSnapshot($orgId, $doc), ['estado' => 'BORRADOR']);
        }
        if ((int) $doc['legacy'] === 1 && !$doc['fiscal_snapshot']) {
            return Representation::render($this->draftSnapshot($orgId, $doc) + ['legado' => true, 'numero_texto' => $doc['number_text'], 'fecha' => $doc['issue_date']], ['estado' => $doc['status'], 'control' => $doc['control_number']]);
        }
        if (!$copy) {
            return (string) $doc['representation_html'];
        }
        $snap = json_decode((string) $doc['fiscal_snapshot'], true) ?: [];
        return Representation::render($snap, ['estado' => $doc['status'], 'control' => $doc['control_number'], 'control_asignado' => $doc['control_assigned_at'], 'anulada_en' => $doc['annulled_at'], 'motivo_anulacion' => $doc['annul_reason'], 'copia' => true]);
    }

    /** Vista previa de un borrador con los datos actuales de la empresa (marca de agua BORRADOR) */
    private function draftSnapshot(int $orgId, array $doc): array
    {
        $prof = $this->profileOrDefault($orgId);
        $lines = $this->db->all('SELECT * FROM agora_fiscal_lines WHERE document_id = ? ORDER BY line_no', [(int) $doc['id']]);
        $t = ['subtotal_cents' => (int) $doc['subtotal_cents'], 'discount_cents' => array_sum(array_map(fn ($l) => (int) $l['discount_cents'], $lines)), 'exempt_cents' => (int) $doc['exempt_cents'],
            'base_general_cents' => (int) $doc['base_general_cents'], 'base_reduced_cents' => (int) $doc['base_reduced_cents'], 'base_luxury_cents' => (int) $doc['base_luxury_cents'],
            'iva_general_cents' => (int) $doc['iva_general_cents'], 'iva_reduced_cents' => (int) $doc['iva_reduced_cents'], 'iva_luxury_cents' => (int) $doc['iva_luxury_cents'],
            'total_cents' => (int) $doc['total_cents'], 'igtf_base_cents' => (int) $doc['igtf_base_cents'], 'igtf_cents' => (int) $doc['igtf_cents']];
        $rate = null;
        $rateDay = null;
        try {
            [$rs, $rateDay] = $this->bcvRate($orgId, Clock::today());
            $rate = Money::rateMicro($rs);
        } catch (HttpError) {
        }
        return [
            'tipo' => $doc['doc_type'], 'serie' => $prof['series'] ?? '', 'numero_texto' => $doc['number_text'] ?? '', 'fecha' => $doc['issue_date'] ?? Clock::today(), 'vence' => $doc['due_date'], 'condiciones' => $doc['payment_terms'],
            'moneda' => $doc['currency'], 'tasa_bcv' => $rate ? self::rateText($rate) : null, 'fecha_tasa' => $rateDay,
            'emisor' => ['razon_social' => $prof['legal_name'] ?? '(configura los datos fiscales)', 'rif' => $prof['rif'] ?? '', 'domicilio' => $prof['fiscal_address'] ?? '', 'telefono' => $prof['phone'] ?? '', 'correo' => $prof['email'] ?? '', 'contribuyente_especial' => !empty($prof['special_taxpayer'])],
            'imprenta' => ['modo' => $prof['issuance_mode'] ?? 'IMPRENTA_DIGITAL', 'nombre' => $prof['printer_name'] ?? '', 'rif' => $prof['printer_rif'] ?? '', 'providencia' => $prof['printer_authorization'] ?? '', 'fecha' => $prof['printer_authorization_date'] ?? null],
            'cliente' => ['nombre' => $doc['client_name'], 'rif' => $doc['client_rif'], 'domicilio' => $doc['client_address'], 'correo' => $doc['client_email'], 'telefono' => $doc['client_phone']],
            'lineas' => array_map(fn ($l) => ['codigo' => $l['product_code'], 'descripcion' => $l['description'], 'cantidad_milli' => (int) $l['quantity_milli'], 'precio_cents' => (int) $l['unit_price_cents'], 'descuento_cents' => (int) $l['discount_cents'], 'impuesto' => $l['tax_code'], 'base_cents' => (int) $l['base_cents']], $lines),
            'alicuotas' => ['G' => (int) ($prof['iva_general_bp'] ?? 1600), 'R' => (int) ($prof['iva_reduced_bp'] ?? 800), 'A' => (int) ($prof['iva_general_bp'] ?? 1600) + (int) ($prof['iva_luxury_bp'] ?? 1500)],
            'igtf_bp' => (int) ($prof['igtf_bp'] ?? 300), 'totales' => $t, 'totales_bs' => Calculator::inBs($t, (string) $doc['currency'], $rate), 'notas' => $doc['notes'], 'leyendas' => [],
        ];
    }

    /**
     * Facturas para el tablero (mismo formato que dejaba el facturero local en "imported_v1"):
     * solo facturas emitidas (incluidas las importadas), con lo acreditado o debitado por notas ya descontado.
     * @return list<array<string,mixed>>
     */
    public function forClient(int $orgId): array
    {
        $adj = [];
        foreach ($this->db->all("SELECT affected_document_id, doc_type, subtotal_cents, total_cents FROM agora_fiscal_documents WHERE org_id = ? AND doc_type IN ('NC','ND') AND status = 'EMITIDA'", [$orgId]) as $n) {
            $k = (int) $n['affected_document_id'];
            $sign = $n['doc_type'] === 'NC' ? -1 : 1;
            $adj[$k]['sub'] = ($adj[$k]['sub'] ?? 0) + $sign * (int) $n['subtotal_cents'];
            $adj[$k]['tot'] = ($adj[$k]['tot'] ?? 0) + $sign * (int) $n['total_cents'];
        }
        $lines = [];
        foreach ($this->db->all("SELECT l.document_id, l.product_code, l.description, l.base_cents FROM agora_fiscal_lines l JOIN agora_fiscal_documents d ON d.id = l.document_id WHERE d.org_id = ? AND d.doc_type = 'FAC' AND d.status = 'EMITIDA' ORDER BY l.document_id, l.line_no", [$orgId]) as $l) {
            $lines[(int) $l['document_id']][] = $l;
        }
        $out = [];
        foreach ($this->db->all("SELECT * FROM agora_fiscal_documents WHERE org_id = ? AND doc_type = 'FAC' AND status = 'EMITIDA' ORDER BY issue_date, number", [$orgId]) as $d) {
            $id = (int) $d['id'];
            $sub = (int) $d['subtotal_cents'];
            $tot = (int) $d['total_cents'];
            $subEff = $sub + (int) ($adj[$id]['sub'] ?? 0);
            $totEff = $tot + (int) ($adj[$id]['tot'] ?? 0);
            if ($subEff <= 0) {
                continue; // acreditada por completo
            }
            $factor = $sub > 0 ? $subEff / $sub : 1.0;
            $rate = $d['currency'] === 'VES' && $d['rate_bcv'] ? Money::rateMicro(is_string($d['rate_bcv']) ? $d['rate_bcv'] : sprintf('%.6F', (float) $d['rate_bcv'])) : null;
            $usd = fn (int $c): float => Money::toFloat($rate ? Money::divRate($c, $rate) : $c);
            $ls = array_map(fn ($l) => ['productId' => $l['product_code'] ?: null, 'amount' => $usd((int) round((int) $l['base_cents'] * $factor)), 'title' => $l['description']], $lines[$id] ?? []);
            $ivaEff = $totEff - $subEff;
            $out[] = [
                'id' => self::appId($d), 'num' => $d['number_text'], 'uid' => $d['uid'], 'clientAppId' => $d['client_app_id'], 'client' => $d['client_name'], 'rif' => $d['client_rif'],
                'issueDate' => $d['issue_date'], 'dueDate' => $d['due_date'] ?? $d['issue_date'], 'subtotal' => $usd($subEff), 'iva' => $usd($ivaEff),
                'ivaRate' => $subEff > 0 ? round($ivaEff / $subEff * 100) : 0, 'lines' => $ls, 'control' => $d['control_number'], 'legacy' => (bool) $d['legacy'],
                'currency' => $d['currency'], 'updatedAt' => App::iso($d['emitted_at'] ?? $d['updated_at']), 'adjusted' => isset($adj[$id]),
            ];
        }
        return $out;
    }

    /** Revisa los sellos de todos los documentos emitidos y que la numeración no tenga saltos */
    public function verifyChain(int $orgId): array
    {
        $chains = [];
        foreach ($this->db->all("SELECT id, uid, doc_type, series, number, number_text, fiscal_snapshot, control_number, control_assigned_at, prev_hash, hash FROM agora_fiscal_documents WHERE org_id = ? AND legacy = 0 AND status <> 'BORRADOR' ORDER BY doc_type, series, number", [$orgId]) as $r) {
            $chains[$r['doc_type'] . '|' . $r['series']][] = $r;
        }
        $problems = [];
        $count = 0;
        foreach ($chains as $key => $docs) {
            $prev = str_repeat('0', 64);
            $lastNum = null;
            foreach ($docs as $r) {
                $count++;
                if ($r['prev_hash'] !== $prev) {
                    $problems[] = ['documento' => $r['number_text'], 'tipo' => $r['doc_type'], 'problema' => 'el eslabón con el documento anterior no coincide'];
                }
                if (!hash_equals(self::sign((string) $r['prev_hash'], (string) $r['fiscal_snapshot'], $r['control_number'], $r['control_assigned_at']), (string) $r['hash'])) {
                    $problems[] = ['documento' => $r['number_text'], 'tipo' => $r['doc_type'], 'problema' => 'el contenido no coincide con su sello'];
                }
                if ($lastNum !== null && (int) $r['number'] !== $lastNum + 1) {
                    $problems[] = ['documento' => $r['number_text'], 'tipo' => $r['doc_type'], 'problema' => 'salto en la numeración después del N.° ' . str_pad((string) $lastNum, 8, '0', STR_PAD_LEFT)];
                }
                $prev = (string) $r['hash'];
                $lastNum = (int) $r['number'];
            }
        }
        return ['integro' => !$problems, 'documentos' => $count, 'problemas' => array_slice($problems, 0, 50)];
    }

    private function bump(int $orgId): void
    {
        $this->db->exec('UPDATE agora_tenants SET revision = revision + 1 WHERE id = ?', [$orgId]);
    }
}
