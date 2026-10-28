<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
function db(): PDO {
    static $db;
    return $db ??= new PDO(getenv('DB_DSN') ?: '', getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
function respond(array $body, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
function body(): array {
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw)>16384) respond(['error'=>'Payload too large'],413);
    $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($value) || array_is_list($value)) throw new InvalidArgumentException('Expected an object');
    return $value;
}
function query(string $sql, array $args = []): PDOStatement {
    $q = db()->prepare($sql); $q->execute($args); return $q;
}
