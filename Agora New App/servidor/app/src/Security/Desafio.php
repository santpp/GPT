<?php
/* © 2026-2027 Santiago Rafael Pérez Pérez · Ágora Digital App (Agora New App). Todos los derechos reservados. Software propietario: ver LICENSE.txt. */
declare(strict_types=1);
namespace Agora\Security;
use Agora\Core\Http;

/**
 * Agora New App 2.17.0 · Confirmación con una operación aritmética para lo que no tiene vuelta atrás
 * (borrar una subcuenta, borrar un usuario). El servidor la exige: la pregunta va firmada con app_key,
 * ligada a la acción, a lo que se borra y a quien la pide, y vence en 5 minutos. La respuesta nunca viaja en el token.
 */
final class Desafio {
 private const VIGENCIA=300;
 /** @return array{pregunta:string,token:string} */
 public static function crear(string $key,string $accion,string $objetivo,int $usuario):array{
  $op=random_int(0,2);
  if($op===0){$a=random_int(4,30);$b=random_int(3,20);$r=$a+$b;$s='+';}
  elseif($op===1){$a=random_int(12,40);$b=random_int(2,11);$r=$a-$b;$s='−';}
  else{$a=random_int(2,9);$b=random_int(2,9);$r=$a*$b;$s='×';}
  $exp=time()+self::VIGENCIA;$nonce=bin2hex(random_bytes(8));
  $firma=hash_hmac('sha256',implode('|',[$accion,$objetivo,$usuario,$exp,$nonce,$r]),$key);
  return ['pregunta'=>"¿Cuánto es $a $s $b?",'token'=>implode('.',[$exp,$nonce,$firma])];
 }
 /** Falla con 422 si la respuesta no es la correcta o el desafío venció. */
 public static function comprobar(string $key,string $accion,string $objetivo,int $usuario,mixed $token,mixed $respuesta):void{
  if(!is_string($token)||!preg_match('/^(\d{10})\.([a-f0-9]{16})\.([a-f0-9]{64})$/D',$token,$m))Http::fail('Falta resolver la operación de confirmación.',422);
  if((int)$m[1]<time())Http::fail('La confirmación venció. Vuelve a intentarlo.',422);
  $r=is_int($respuesta)?$respuesta:(is_string($respuesta)&&preg_match('/^\s*-?\d{1,4}\s*$/D',$respuesta)?(int)trim($respuesta):null);
  if($r===null||!hash_equals(hash_hmac('sha256',implode('|',[$accion,$objetivo,$usuario,$m[1],$m[2],$r]),$key),$m[3]))Http::fail('El resultado de la operación no es correcto.',422);
 }
}
