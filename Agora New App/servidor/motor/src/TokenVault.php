<?php
declare(strict_types=1);
require_once __DIR__.'/SetupException.php';
final class TokenVault {
    public function __construct(private readonly string $key) {
        if(!extension_loaded('sodium')||!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt'))throw new SetupException('encryption_module_missing');
        if(strlen($key)!==SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)throw new SetupException('encryption_key_invalid');
    }
    public static function keyFile():string {
        // Same fallback as the native key generator. Never generate a key here.
        return getenv('APP_KEY_FILE') ?: dirname(__DIR__).'/var/integration.key';
    }
    public static function assertPrivatePath(string $file):void {
        $resolved=realpath($file) ?: ((realpath(dirname($file))?:dirname($file)).DIRECTORY_SEPARATOR.basename($file));
        foreach([dirname(__DIR__).'/public',dirname(dirname(__DIR__)).'/public_html'] as $public) {
            $root=realpath($public);
            if($root && ($resolved===$root || str_starts_with($resolved,$root.DIRECTORY_SEPARATOR)))throw new SetupException('encryption_key_unsafe_path');
        }
    }
    public static function fromEnvironment():self {
        if(!extension_loaded('sodium')||!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt'))throw new SetupException('encryption_module_missing');
        $file=self::keyFile();self::assertPrivatePath($file);
        if(!is_file($file)) {
            $directory=dirname($file);
            // An inaccessible parent must not be mistaken for a missing key.
            if(is_dir($directory)&&!is_readable($directory))throw new SetupException('encryption_key_unreadable');
            throw new SetupException('encryption_key_missing');
        }
        if(!is_readable($file))throw new SetupException('encryption_key_unreadable');
        $raw=@file_get_contents($file);
        if($raw===false)throw new SetupException('encryption_key_unreadable');
        $key=base64_decode(trim($raw),true);
        if($key===false)throw new SetupException('encryption_key_invalid');
        return new self($key);
    }
    public function seal(string $token,int $tenant,string $location):string {
        $nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($token,$tenant.':'.$location,$nonce,$this->key));
    }
    public function open(string $cipher,int $tenant,string $location):string {
        $raw=base64_decode($cipher,true);$size=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if($raw===false || strlen($raw)<=$size) throw new RuntimeException('Invalid ciphertext');
        $token=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw,$size),$tenant.':'.$location,substr($raw,0,$size),$this->key);
        if($token===false) throw new RuntimeException('Credential authentication failed');
        return $token;
    }
}
