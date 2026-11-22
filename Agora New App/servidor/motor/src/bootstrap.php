<?php
/* © 2026 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
require_once __DIR__.'/SetupException.php';
require_once __DIR__.'/config.php';
function db(): PDO {
    static $db;
    return $db ??= new PDO(getenv('DB_DSN') ?: '', getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
/** Agora New App 2.14.0: los widgets del ERP leen respuestas del motor sin cortar la petición (modo captura). */
final class MotorCaptura extends RuntimeException {public function __construct(public readonly array $body,public readonly int $status){parent::__construct('captura');}}
function respond(array $body, int $status = 200): never {
    // Agora New App 2.9.6: el ERP puede ajustar la respuesta a sus reglas (por ejemplo, quién edita las cifras del mes).
    $t=$GLOBALS['motorPuente']['transformar']??null;
    if($status<400&&is_callable($t)){try{$body=$t($body);}catch(Throwable $e){}}
    motorEspejo($body,$status);
    // La primera respuesta es la buena: si el motor atrapa la captura en un catch genérico y responde otra cosa, se repite la primera.
    if(!empty($GLOBALS['motorCaptura'])){$GLOBALS['motorCapturado']??=[$body,$status];throw new MotorCaptura($GLOBALS['motorCapturado'][0],$GLOBALS['motorCapturado'][1]);}
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Responde y, si el servidor sabe cerrar la conexión antes de terminar, hace
 * después un trabajo que la persona no tiene por qué esperar. Si no sabe, el
 * trabajo no se hace aquí: lo hará el cron, y la respuesta no se retrasa nunca.
 */
/** Agora New App 2.9.0: antes de responder, el ERP guarda en su espejo los contactos y oportunidades que trae la respuesta. */
function motorEspejo(array $body,int $status):void {
    $f=$GLOBALS['motorPuente']['alResponder']??null;
    if($status<400&&is_callable($f)){try{$f($body);}catch(Throwable $e){}}
}

function respondThen(array $body, callable $after): never {
    if(!empty($GLOBALS['motorCaptura']))respond($body); // Agora New App 2.14.0
    motorEspejo($body,200);
    $finish=function_exists('litespeed_finish_request')?'litespeed_finish_request':(function_exists('fastcgi_finish_request')?'fastcgi_finish_request':null);
    if($finish===null)respond($body);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $finish();
    ignore_user_abort(true);
    try {$after();} catch(Throwable $e) {error_log('After-response work failed: '.get_class($e));}
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
