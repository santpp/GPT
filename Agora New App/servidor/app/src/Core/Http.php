<?php
declare(strict_types=1);
namespace Agora\Core;
final class HttpError extends \RuntimeException {public function __construct(string $message,int $status=400){parent::__construct($message,$status);}}
final class Http {
 public static function fail(string $m,int $s=422):never{throw new HttpError($m,$s);}
 public static function body():array{if((int)($_SERVER['CONTENT_LENGTH']??0)>2200000)self::fail('Solicitud demasiado grande.',413);$raw=file_get_contents('php://input',false,null,0,2200001);if(strlen($raw)>2200000)self::fail('Solicitud demasiado grande.',413);if($raw==='')return [];if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))self::fail('Se requiere JSON.',415);try{$v=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){self::fail('JSON inválido.');}if(!is_array($v)||array_is_list($v)&&$v!==[])self::fail('Objeto JSON requerido.');return $v;}
 public static function text(array $b,string $k,int $max=120,bool $required=true):string{$v=$b[$k]??'';if(!is_string($v)&&!is_numeric($v))self::fail("Campo inválido: $k");$v=trim((string)$v);if(($required&&$v==='')||mb_strlen($v)>$max)self::fail("Revisa el campo $k.");return $v;}
 public static function email(array $b,string $key='correo'):string{$v=strtolower(self::text($b,$key,254));if(!filter_var($v,FILTER_VALIDATE_EMAIL))self::fail('Correo inválido.');return $v;}
 public static function integer(mixed $v,int $min,int $max):int{if(filter_var($v,FILTER_VALIDATE_INT)===false||$v<$min||$v>$max)self::fail('Número fuera de rango.');return (int)$v;}
 public static function period(mixed $v):string{if(!is_string($v)||!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$v))self::fail('Periodo inválido.');return $v;}
 public static function password(mixed $v):string{$min=defined('AGORA_MODO_PRUEBAS')?8:12;if(!is_string($v)||strlen($v)<$min||strlen($v)>72)self::fail('La contraseña debe tener entre '.$min.' y 72 bytes.');return $v;}
 public static function response(mixed $data,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');echo DB::json($data);exit;}
}
