<?php
declare(strict_types=1);
final class TokenVault {
    public function __construct(private readonly string $key) {
        if(strlen($key)!==SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) throw new RuntimeException('Invalid encryption key');
    }
    public static function fromEnvironment(): self {
        $file=getenv('APP_KEY_FILE');
        if(!$file || !is_file($file) || !is_readable($file)) throw new RuntimeException('Encryption key unavailable');
        $key=base64_decode(trim(file_get_contents($file)),true);
        if($key===false) throw new RuntimeException('Invalid encryption key');
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
