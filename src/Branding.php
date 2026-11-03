<?php
declare(strict_types=1);

/** Identidad del espacio: el nombre y el logo que salen en la barra y en el Resumen. */
final class Branding
{
    private const MAX_BYTES = 400000;
    /** Lo que admite la petición: el logo en base64 más el nombre. */
    public const MAX_REQUEST = 600000;
    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const SVG_ELEMENTS = ['svg','g','path','rect','circle','ellipse','line','polyline','polygon','defs','lineargradient','radialgradient','stop','clippath','mask','symbol','use','text','tspan','title','desc','style','pattern','image','marker'];

    public static function get(int $tenant, bool $locked): array
    {
        $row = query('SELECT name,(logo IS NOT NULL) AS has_logo,UNIX_TIMESTAMP(updated_at) AS at FROM workspace_branding WHERE tenant_id=?', [$tenant])->fetch();
        $name = $row && $row['name'] !== null && $row['name'] !== '' ? $row['name'] : (string)query('SELECT name FROM tenants WHERE id=?', [$tenant])->fetchColumn();
        return ['nombre' => $name, 'logo' => $row && $row['has_logo'] ? '/api/workspace/branding/logo?v='.$row['at'] : null, 'bloqueado' => $locked];
    }

    public static function save(int $tenant, array $in): void
    {
        $name = trim((string)($in['nombre'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) throw new InvalidArgumentException('Escribe el nombre del espacio (hasta 80 letras).');
        $logo = $in['logo'] ?? null;
        $type = null; $bytes = null;
        // Si sólo cambia el nombre, la pantalla devuelve la dirección del logo que ya hay: se queda como está.
        if (is_string($logo) && str_starts_with($logo, '/api/workspace/branding/logo')) {
            if (query('SELECT 1 FROM workspace_branding WHERE tenant_id=? AND logo IS NOT NULL', [$tenant])->fetchColumn()) {
                query('INSERT INTO workspace_branding(tenant_id,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)', [$tenant, $name]);
                return;
            }
            $logo = null;
        }
        if ($logo !== null) {
            if (!is_string($logo) || !preg_match('~^data:(image/(?:png|jpeg|webp|svg\+xml));base64,([A-Za-z0-9+/=\s]+)$~D', $logo, $m)) throw new InvalidArgumentException('El logo debe ser una imagen PNG, JPG, WebP o SVG.');
            $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
            if ($bytes === false || $bytes === '') throw new InvalidArgumentException('No se pudo leer el logo.');
            if (strlen($bytes) > self::MAX_BYTES) throw new InvalidArgumentException('El logo pesa demasiado (máximo 400 KB).');
            if ($m[1] === 'image/svg+xml') {
                // Un SVG es código: se vuelve a escribir sólo con lo que dibuja (formas, degradados, texto), sin scripts ni enlaces afuera.
                $clean = self::cleanSvg($bytes);
                if ($clean === null) throw new InvalidArgumentException('El SVG no es válido o trae algo que no se admite.');
                $bytes = $clean; $type = 'image/svg+xml';
            } else {
                // Se mira la imagen de verdad: lo que dice ser no basta.
                $info = @getimagesizefromstring($bytes);
                if (!$info || !isset(self::TYPES[$info['mime']]) || $info['mime'] !== $m[1] || $info[0] > 4000 || $info[1] > 4000) throw new InvalidArgumentException('El logo no es una imagen válida.');
                $type = $info['mime'];
            }
        }
        query('INSERT INTO workspace_branding(tenant_id,name,logo,logo_type) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),logo=VALUES(logo),logo_type=VALUES(logo_type)', [$tenant, $name, $bytes, $type]);
    }

    /** El SVG reescrito sólo con lo permitido, o null si no se puede dejar seguro. */
    public static function cleanSvg(string $svg): ?string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY|<\?xml-stylesheet/i', $svg)) return null;
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $root = $loaded ? $dom->documentElement : null;
        if (!$root || strtolower($root->localName) !== 'svg') return null;
        $walk = function (DOMElement $el) use (&$walk): void {
            foreach (iterator_to_array($el->childNodes) as $child) {
                if ($child instanceof DOMElement) {
                    if (!in_array(strtolower($child->localName), self::SVG_ELEMENTS, true)) { $el->removeChild($child); continue; }
                    $walk($child);
                } elseif (!($child instanceof DOMText) && !($child instanceof DOMCdataSection)) $el->removeChild($child);
            }
            foreach (iterator_to_array($el->attributes) as $attr) {
                $name = strtolower($attr->nodeName); $value = $attr->nodeValue;
                $bad = str_starts_with($name, 'on')
                    || (in_array($name, ['href', 'xlink:href'], true) && !preg_match('~^(#[\w.-]+|data:image/(?:png|jpeg|webp|gif);base64,[A-Za-z0-9+/=]+)$~D', $value))
                    || (preg_match('/url\s*\(\s*[\'"]?\s*(?!#)/i', $value))
                    || preg_match('/javascript:|expression\s*\(|@import|behavior\s*:/i', $value);
                if ($bad) $el->removeAttributeNode($attr);
            }
            if (strtolower($el->localName) === 'style' && preg_match('/@import|url\s*\(\s*[\'"]?\s*(?!#|data:image)/i', $el->textContent)) $el->textContent = '';
        };
        $walk($root);
        $out = $dom->saveXML($root);
        return is_string($out) && $out !== '' ? $out : null;
    }

    /** @return array{0:string,1:string}|null tipo y contenido */
    public static function logo(int $tenant): ?array
    {
        $row = query('SELECT logo,logo_type FROM workspace_branding WHERE tenant_id=? AND logo IS NOT NULL', [$tenant])->fetch();
        return $row ? [(string)$row['logo_type'], (string)$row['logo']] : null;
    }
}
