<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Fiscal\Base;
/** Agora New App · cliente web mínimo (curl) para traer la tasa BCV. Solo HTTPS, sin seguir redirecciones a otros protocolos. */
final class HttpClient
{
    /** @return array{status:int,body:string,error:?string} */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 10): array
    {
        if (!str_starts_with($url, 'https://')) return ['status' => 0, 'body' => '', 'error' => 'solo https'];
        $h = curl_init($url);
        $hs = ['User-Agent: AgoraERP/1.0'];
        foreach ($headers as $k => $v) $hs[] = $k . ': ' . $v;
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $hs, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS]);
        if ($body !== null) curl_setopt($h, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($h); $status = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE); $err = curl_errno($h) ? curl_error($h) : null; curl_close($h);
        return ['status' => $status, 'body' => is_string($raw) ? $raw : '', 'error' => $err];
    }
    /** @return array{status:int,json:mixed,error:?string} */
    public static function json(string $method, string $url, ?array $body = null, array $headers = [], int $timeout = 10): array
    {
        $r = self::request($method, $url, $headers + ['Accept' => 'application/json'] + ($body !== null ? ['Content-Type' => 'application/json'] : []), $body !== null ? json_encode($body) : null, $timeout);
        return ['status' => $r['status'], 'json' => json_decode($r['body'], true), 'error' => $r['error']];
    }
}
