<?php
declare(strict_types=1);
namespace Agora\Services;
use Agora\Core\HttpError;
/**
 * Agora New App · cifrado de los tokens de GoHighLevel (portado de Ágora Mobile).
 * XChaCha20-Poly1305 con datos asociados «subcuenta:location»: un token copiado a otra
 * subcuenta o a otro Location ID no se puede abrir.
 * Clave: `vault_key` de config.php (64 hexadecimales) o, si no existe, derivada de `app_key`.
 * Cambiar cualquiera de las dos invalida los tokens guardados (hay que volver a escribirlos).
 */
final class TokenVault {
 private string $key;
 public function __construct(array $config){
  if(!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt'))throw new HttpError('Falta la extensión PHP sodium para guardar el token cifrado.',503);
  $hex=is_string($config['vault_key']??null)&&preg_match('/^[a-f0-9]{64}$/D',$config['vault_key'])?$config['vault_key']:null;
  $this->key=$hex!==null?hex2bin($hex):hash_hkdf('sha256',hex2bin((string)$config['app_key']),SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,'agora-ghl-token-v1');
 }
 public function seal(string $token,int $tenant,string $location):string{
  $nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
  return base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($token,$tenant.':'.$location,$nonce,$this->key));
 }
 public function open(string $cipher,int $tenant,string $location):string{
  $raw=base64_decode($cipher,true);$n=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
  if($raw===false||strlen($raw)<=$n)throw new \RuntimeException('Token cifrado inválido.');
  $token=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw,$n),$tenant.':'.$location,substr($raw,0,$n),$this->key);
  if($token===false)throw new \RuntimeException('No se pudo abrir el token (clave cambiada o datos alterados).');
  return $token;
 }
}
