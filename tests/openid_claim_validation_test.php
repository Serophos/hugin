<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Services/OpenIdConnectService.php';

use App\Services\OpenIdConnectService;

$settings = [
    'client_id' => 'hugin-client',
    'username_claim' => 'preferred_username',
    'name_claim' => 'name',
    'first_name_claim' => 'given_name',
    'last_name_claim' => 'family_name',
    'department_claim' => 'department',
    'title_claim' => 'title',
    'picture_claim' => 'picture',
    'groups_claim' => 'groups',
    'admin_group' => 'admin',
    'editor_group' => 'editor',
];
$service = new OpenIdConnectService($settings);
$validate = new ReflectionMethod($service, 'validateClaims');
$validate->setAccessible(true);
$valid = ['iss' => 'https://id.example/realms/hugin', 'aud' => 'hugin-client', 'nonce' => 'expected'];
$validate->invoke($service, $valid, 'https://id.example/realms/hugin', 'expected');

foreach ([
    [array_replace($valid, ['iss' => 'https://attacker.example']), 'https://id.example/realms/hugin', 'expected'],
    [array_replace($valid, ['aud' => 'another-client']), 'https://id.example/realms/hugin', 'expected'],
    [array_replace($valid, ['nonce' => 'wrong']), 'https://id.example/realms/hugin', 'expected'],
    [['iss' => $valid['iss'], 'aud' => ['hugin-client', 'another-client'], 'azp' => 'another-client', 'nonce' => 'expected'], $valid['iss'], 'expected'],
] as $case) {
    $rejected = false;
    try {
        $validate->invoke($service, ...$case);
    } catch (Throwable) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new RuntimeException('Invalid issuer, audience, nonce, or authorized party was accepted.');
    }
}

echo "OpenID claim validation tests passed.\n";
