<?php
declare(strict_types=1);

namespace Agora\Fiscal;

use Agora\Fiscal\Base\HttpClient;

/**
 * Fuentes de tasas:
 *  · Binance P2P (USDT/VES): no existe un par USDT/VES en el mercado al contado ni un canal en vivo (WebSocket)
 *    para P2P, así que se consultan los anuncios públicos. BUY = anuncios para COMPRAR USDT (vendedores, el más
 *    barato primero); SELL = anuncios para VENDER USDT (compradores, el que más paga primero).
 *  · BCV: tasa oficial (DolarApi; si falla, la página del BCV).
 * Las respuestas se leen con tolerancia: si Binance cambia un campo, se busca el precio donde esté.
 */
final class FxSources
{
    public const BINANCE_AGENT = 'https://www.binance.com/bapi/c2c/v1/public/c2c/agent/ad-list';
    public const BINANCE_LEGACY = 'https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search';
    public const DOLARAPI = 'https://ve.dolarapi.com/v1/dolares/oficial';
    public const BCV = 'https://www.bcv.org.ve/';
    public const DOLARAPI_EUR = 'https://ve.dolarapi.com/v1/euros/oficial';

    /**
     * @param 'BUY'|'SELL' $side
     * @return array{ok:bool,prices:list<float>,ads:int,source:string,error:?string}
     */
    public static function binance(string $side, int $rows = 20): array
    {
        $side = $side === 'SELL' ? 'SELL' : 'BUY';
        $errors = [];
        $r = HttpClient::json('GET', self::BINANCE_AGENT . '?' . http_build_query(['fiat' => 'VES', 'asset' => 'USDT', 'tradeType' => $side, 'limit' => $rows]), null, ['Accept-Language' => 'es-VE,es;q=0.9'], 10);
        if ($r['status'] === 200 && is_array($r['json'])) {
            $prices = self::pricesFrom($r['json'], $side);
            if ($prices) {
                return ['ok' => true, 'prices' => $prices, 'ads' => count($prices), 'source' => 'binance_agent', 'error' => null];
            }
            $errors[] = 'sin anuncios (agent)';
        } else {
            $errors[] = 'agent: ' . ($r['error'] ?? ('HTTP ' . $r['status']));
        }
        $body = ['fiat' => 'VES', 'page' => 1, 'rows' => min(20, $rows), 'tradeType' => $side, 'asset' => 'USDT', 'countries' => [], 'proMerchantAds' => false, 'shieldMerchantAds' => false, 'publisherType' => null, 'payTypes' => [], 'classifies' => ['mass', 'profession']];
        $r = HttpClient::json('POST', self::BINANCE_LEGACY, $body, ['Accept-Language' => 'es-VE,es;q=0.9', 'Origin' => 'https://p2p.binance.com'], 10);
        if ($r['status'] === 200 && is_array($r['json'])) {
            $prices = self::pricesFrom($r['json'], $side);
            if ($prices) {
                return ['ok' => true, 'prices' => $prices, 'ads' => count($prices), 'source' => 'binance_p2p', 'error' => null];
            }
            $errors[] = 'sin anuncios (p2p)';
        } else {
            $errors[] = 'p2p: ' . ($r['error'] ?? ('HTTP ' . $r['status']));
        }
        return ['ok' => false, 'prices' => [], 'ads' => 0, 'source' => 'binance', 'error' => implode(' · ', $errors)];
    }

    /**
     * Extrae precios de anuncios confiables (si la respuesta trae datos del anunciante: ≥ 20 órdenes al mes y ≥ 90 % completadas).
     * @return list<float> ordenados del mejor al peor para quien opera
     */
    public static function pricesFrom(array $json, string $side): array
    {
        $ads = [];
        $walk = function ($node) use (&$walk, &$ads): void {
            if (!is_array($node)) {
                return;
            }
            $adv = isset($node['adv']) && is_array($node['adv']) ? $node['adv'] : null;
            $price = $adv['price'] ?? ($node['price'] ?? null);
            if ($price !== null && is_numeric($price) && (isset($node['adv']) || isset($node['advertiser']) || isset($node['tradeType']) || isset($node['advNo']) || isset($node['asset']))) {
                $ads[] = ['price' => (float) $price, 'advertiser' => is_array($node['advertiser'] ?? null) ? $node['advertiser'] : null];
                return;
            }
            foreach ($node as $v) {
                if (is_array($v)) {
                    $walk($v);
                }
            }
        };
        $walk($json['data'] ?? $json);
        $prices = [];
        foreach ($ads as $a) {
            $p = $a['price'];
            if (!($p > 0) || $p > 1e9) {
                continue;
            }
            $adv = $a['advertiser'];
            if ($adv !== null) {
                $orders = $adv['monthOrderCount'] ?? null;
                $rate = $adv['monthFinishRate'] ?? null;
                if (($orders !== null && (int) $orders < 20) || ($rate !== null && (float) $rate < 0.9)) {
                    continue;
                }
            }
            $prices[] = $p;
        }
        if (!$prices && $ads) {
            // si el filtro dejó todo fuera, se usan igual (mejor un dato que ninguno)
            $prices = array_map(fn ($a) => $a['price'], array_filter($ads, fn ($a) => $a['price'] > 0 && $a['price'] < 1e9));
        }
        $side === 'SELL' ? rsort($prices) : sort($prices);
        return array_values(array_map(fn ($p) => round((float) $p, 4), $prices));
    }

    /** @return array{ok:bool,rate:?float,date:?string,source:string,error:?string} */
    public static function bcv(): array
    {
        $errors = [];
        $r = HttpClient::json('GET', self::DOLARAPI, null, [], 10);
        if ($r['status'] === 200 && is_array($r['json'])) {
            $p = self::dolarApiRate($r['json']);
            if ($p) {
                return ['ok' => true] + $p + ['source' => 'dolarapi', 'error' => null];
            }
            $errors[] = 'dolarapi sin tasa';
        } else {
            $errors[] = 'dolarapi: ' . ($r['error'] ?? ('HTTP ' . $r['status']));
        }
        $r = HttpClient::request('GET', self::BCV, ['Accept' => 'text/html'], null, 12);
        if ($r['status'] === 200 && ($p = self::bcvHtmlRate($r['body']))) {
            return ['ok' => true] + $p + ['source' => 'bcv.org.ve', 'error' => null];
        }
        $errors[] = 'bcv: ' . ($r['error'] ?? ('HTTP ' . $r['status']));
        return ['ok' => false, 'rate' => null, 'date' => null, 'source' => 'bcv', 'error' => implode(' · ', $errors)];
    }

    /** @return array{rate:float,date:?string}|null */
    public static function dolarApiRate(array $j): ?array
    {
        foreach (['promedio', 'venta', 'compra'] as $k) {
            if (isset($j[$k]) && is_numeric($j[$k]) && (float) $j[$k] > 0) {
                $date = isset($j['fechaActualizacion']) ? substr((string) $j['fechaActualizacion'], 0, 10) : null;
                return ['rate' => round((float) $j[$k], 6), 'date' => $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null];
            }
        }
        return null;
    }

    /** @return array{rate:float,date:?string}|null */
    public static function bcvHtmlRate(string $html, string $id = 'dolar'): ?array
    {
        if (!preg_match('#id="' . preg_quote($id, '#') . '".{0,600}?<strong>\s*([\d.,]+)\s*</strong>#s', $html, $m)) {
            return null;
        }
        $n = $m[1];
        $n = str_contains($n, ',') ? str_replace(['.', ','], ['', '.'], $n) : $n;
        if (!is_numeric($n) || (float) $n <= 0) {
            return null;
        }
        $date = null;
        if (preg_match('#Fecha Valor:.{0,200}?content="(\d{4}-\d{2}-\d{2})#s', $html, $d)) {
            $date = $d[1];
        }
        return ['rate' => round((float) $n, 6), 'date' => $date];
    }

    /**
     * Agora New App 2.12.0 · Tasa oficial del euro (BCV). Misma fuente pública que el dólar, con la página del BCV de respaldo.
     * @return array{ok:bool,rate:?float,date:?string,source:string,error:?string}
     */
    public static function bcvEur(): array
    {
        $errors = [];
        $r = HttpClient::json('GET', self::DOLARAPI_EUR, null, [], 10);
        if ($r['status'] === 200 && is_array($r['json']) && ($p = self::dolarApiRate($r['json']))) return ['ok' => true] + $p + ['source' => 'dolarapi', 'error' => null];
        $errors[] = 'dolarapi: ' . ($r['error'] ?? ('HTTP ' . $r['status']));
        $r = HttpClient::request('GET', self::BCV, ['Accept' => 'text/html'], null, 12);
        if ($r['status'] === 200 && ($p = self::bcvHtmlRate($r['body'], 'euro'))) return ['ok' => true] + $p + ['source' => 'bcv.org.ve', 'error' => null];
        $errors[] = 'bcv: ' . ($r['error'] ?? ('HTTP ' . $r['status']));
        return ['ok' => false, 'rate' => null, 'date' => null, 'source' => 'bcv', 'error' => implode(' · ', $errors)];
    }

    /**
     * Agora New App 2.12.0 · USDT en Binance P2P: el punto medio entre la mediana de quienes venden y la de quienes compran
     * (los 10 primeros anuncios confiables de cada lado). Si solo responde un lado, se usa ese.
     * @return array{ok:bool,rate:?float,date:?string,source:string,error:?string}
     */
    public static function usdt(): array
    {
        $med = static function (array $p): ?float { $p = array_slice($p, 0, 10); if (!$p) return null; sort($p); $n = count($p); return $n % 2 ? $p[intdiv($n, 2)] : ($p[$n / 2 - 1] + $p[$n / 2]) / 2; };
        $buy = self::binance('BUY'); $sell = self::binance('SELL');
        $vals = array_values(array_filter([$buy['ok'] ? $med($buy['prices']) : null, $sell['ok'] ? $med($sell['prices']) : null]));
        if (!$vals) return ['ok' => false, 'rate' => null, 'date' => null, 'source' => 'binance', 'error' => trim(($buy['error'] ?? '') . ' · ' . ($sell['error'] ?? ''), ' ·')];
        return ['ok' => true, 'rate' => round(array_sum($vals) / count($vals), 6), 'date' => null, 'source' => 'binance_p2p', 'error' => null];
    }
}
