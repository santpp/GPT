<?php
declare(strict_types=1);
final class Webhook {
    public static function verify(string $raw, string $signature, string $key, int $now): array {
        $bytes = base64_decode($signature, true);
        if (!$bytes || openssl_verify($raw, $bytes, $key, OPENSSL_ALGO_SHA256) !== 1) throw new InvalidArgumentException('Invalid signature');
        $event = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($event)) throw new InvalidArgumentException('Invalid event');
        $time = isset($event['timestamp']) && is_string($event['timestamp']) ? strtotime($event['timestamp']) : false;
        if ($time === false || abs($now - $time) > 300) throw new InvalidArgumentException('Stale event');
        foreach (['webhookId','locationId','type'] as $field) {
            if (!isset($event[$field]) || !is_string($event[$field]) || $event[$field] === '' || strlen($event[$field]) > ($field === 'webhookId' ? 190 : 80)) throw new InvalidArgumentException('Invalid envelope');
        }
        return $event;
    }
}
