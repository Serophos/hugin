<?php
namespace App\Core;

use RuntimeException;

final class SecretCipher
{
    private const PREFIX = 'hugin:v1:';
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'hugin:database-secret:v1';

    private string $key;

    public function __construct(string $deploymentKey)
    {
        if (strlen($deploymentKey) < 32) {
            throw new RuntimeException('The application encryption key must contain at least 32 characters.');
        }
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('The OpenSSL PHP extension is required for secret encryption.');
        }
        $this->key = hash_hkdf('sha256', $deploymentKey, 32, self::AAD);
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::AAD,
            16
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Secret encryption failed.');
        }
        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }
        if (!str_starts_with($encoded, self::PREFIX)) {
            throw new RuntimeException('The stored secret is not in the supported encrypted format.');
        }
        $payload = base64_decode(substr($encoded, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('The encrypted secret payload is invalid.');
        }
        $nonce = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::AAD
        );
        if ($plaintext === false) {
            throw new RuntimeException('Secret decryption failed.');
        }
        return $plaintext;
    }
}
