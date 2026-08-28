<?php
require_once __DIR__ . '/../app/Core/SecretCipher.php';

use App\Core\SecretCipher;

$cipher = new SecretCipher(str_repeat('deployment-secret-', 3));
$encryptedA = $cipher->encrypt('keycloak-client-secret');
$encryptedB = $cipher->encrypt('keycloak-client-secret');

if ($encryptedA === 'keycloak-client-secret' || !str_starts_with($encryptedA, 'hugin:v1:')) {
    throw new RuntimeException('Secret was not stored in the encrypted envelope format.');
}
if ($encryptedA === $encryptedB) {
    throw new RuntimeException('Encryption must use a unique nonce.');
}
if ($cipher->decrypt($encryptedA) !== 'keycloak-client-secret') {
    throw new RuntimeException('Encrypted secret did not round-trip.');
}

foreach ([
    fn () => (new SecretCipher('short'))->encrypt('secret'),
    fn () => $cipher->decrypt('plaintext-secret'),
    fn () => (new SecretCipher(str_repeat('different-key-', 3)))->decrypt($encryptedA),
] as $invalidOperation) {
    $rejected = false;
    try {
        $invalidOperation();
    } catch (Throwable) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new RuntimeException('Invalid key or legacy/tampered plaintext was accepted.');
    }
}

echo "Secret cipher tests passed.\n";
