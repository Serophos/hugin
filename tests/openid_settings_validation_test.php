<?php
require_once __DIR__ . '/../app/helpers.php';

foreach (['displayname', 'given_name', 'family_name', 'groups', 'realm_access.roles', 'custom:claim-name'] as $claim) {
    if (!app_openid_claim_path_is_valid($claim)) {
        throw new RuntimeException('Valid OpenID claim path was rejected: ' . $claim);
    }
}

foreach (['', 'given name', 'groups[]', '/groups', "groups\nadmin"] as $claim) {
    if (app_openid_claim_path_is_valid($claim)) {
        throw new RuntimeException('Invalid OpenID claim path was accepted.');
    }
}

$_SESSION['_user']['picture_url'] = 'https://images.example.org/avatar.png';
if (current_user_picture_url() !== 'https://images.example.org/avatar.png') { throw new RuntimeException('Valid picture URL was rejected.'); }
$_SESSION['_user']['picture_url'] = 'javascript:alert(1)';
if (current_user_picture_url() !== '') { throw new RuntimeException('Unsafe picture URL was accepted.'); }
echo "OpenID settings validation tests passed.\n";
