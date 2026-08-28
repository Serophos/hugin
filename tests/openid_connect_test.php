<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Services/OpenIdConnectService.php';

use App\Services\OpenIdConnectService;

function oidc_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$settings = [
    'enabled' => true,
    'issuer_url' => 'https://keycloak.example/realms/hugin',
    'client_id' => 'hugin',
    'client_secret' => 'secret',
    'scopes' => 'openid profile',
    'username_claim' => 'preferred_username',
    'name_claim' => 'name',
    'first_name_claim' => 'given_name',
    'last_name_claim' => 'family_name',
    'department_claim' => 'department',
    'title_claim' => 'title',
    'picture_claim' => 'picture',
    'groups_claim' => 'custom.groups',
    'admin_group' => 'hugin-admin',
    'editor_group' => 'hugin-editor',
];

oidc_assert(OpenIdConnectService::isConfigured($settings), 'Complete settings must activate OIDC.');
$incomplete = $settings;
$incomplete['client_secret'] = '';
oidc_assert(!OpenIdConnectService::isConfigured($incomplete), 'Missing client secret must keep OIDC inactive.');

$service = new OpenIdConnectService($settings);
$method = new ReflectionMethod($service, 'mapIdentity');
$method->setAccessible(true);

$editor = $method->invoke($service, [
    'preferred_username' => 'editor.user',
    'name' => 'Editor User',
    'given_name' => 'Editor',
    'family_name' => 'User',
    'department' => 'Communications',
    'title' => 'Editor',
    'picture' => 'https://images.example.org/editor.jpg',
    'custom' => ['groups' => ['/organisation/hugin-editor']],
]);
oidc_assert($editor['role'] === 'editor', 'A path-style editor group must match by leaf name.');
oidc_assert($editor['first_name'] === 'Editor' && $editor['last_name'] === 'User', 'Profile claims must map.');
oidc_assert($editor['department'] === 'Communications' && $editor['title'] === 'Editor', 'Department and title claims must map.');
oidc_assert($editor['picture_url'] === 'https://images.example.org/editor.jpg', 'Valid HTTP(S) picture claim must map.');

$admin = $method->invoke($service, [
    'preferred_username' => 'admin.user',
    'custom' => ['groups' => ['hugin-editor', '/organisation/hugin-admin']],
]);
oidc_assert($admin['role'] === 'admin', 'Administrator group must win when both groups match.');
$invalidPicture = $method->invoke($service, ['preferred_username' => 'no.picture', 'picture' => 'javascript:alert(1)', 'custom' => ['groups' => ['hugin-editor']]]);
oidc_assert($invalidPicture['picture_url'] === '', 'Invalid or non-HTTP(S) picture claims must be ignored.');

foreach ([
    ['preferred_username' => 'denied.user', 'custom' => ['groups' => ['/organisation/unrelated']]],
    ['preferred_username' => 'bad.user', 'custom' => ['groups' => 'hugin-admin']],
] as $claims) {
    $denied = false;
    try {
        $method->invoke($service, $claims);
    } catch (Throwable) {
        $denied = true;
    }
    oidc_assert($denied, 'Unauthorized or malformed group claims must be rejected.');
}

echo "OpenID Connect tests passed.\n";
