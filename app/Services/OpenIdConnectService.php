<?php
namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use RuntimeException;

class OpenIdConnectService
{
    private Client $http;

    public function __construct(private array $settings, ?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 10, 'connect_timeout' => 5, 'verify' => true]);
    }

    public static function isConfigured(array $settings): bool
    {
        return !empty($settings['enabled'])
            && trim((string)($settings['issuer_url'] ?? '')) !== ''
            && trim((string)($settings['client_id'] ?? '')) !== ''
            && trim((string)($settings['client_secret'] ?? '')) !== ''
            && trim((string)($settings['admin_group'] ?? '')) !== ''
            && trim((string)($settings['editor_group'] ?? '')) !== '';
    }

    public function discovery(): array
    {
        $issuer = rtrim(trim((string)$this->settings['issuer_url']), '/');
        if (!filter_var($issuer, FILTER_VALIDATE_URL) || strtolower((string)parse_url($issuer, PHP_URL_SCHEME)) !== 'https') {
            throw new RuntimeException('The OpenID Connect issuer must be an HTTPS URL.');
        }
        $response = $this->http->get($issuer . '/.well-known/openid-configuration', ['headers' => ['Accept' => 'application/json']]);
        $metadata = json_decode((string)$response->getBody(), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($metadata)) {
            throw new RuntimeException('The OpenID Connect discovery response is invalid.');
        }
        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri', 'userinfo_endpoint'] as $required) {
            if (empty($metadata[$required]) || !is_string($metadata[$required])) {
                throw new RuntimeException('The discovery response is missing ' . $required . '.');
            }
        }
        if (rtrim($metadata['issuer'], '/') !== $issuer) {
            throw new RuntimeException('The discovered issuer does not match the configured issuer.');
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'userinfo_endpoint'] as $endpoint) {
            if (strtolower((string)parse_url($metadata[$endpoint], PHP_URL_SCHEME)) !== 'https') {
                throw new RuntimeException('OpenID Connect endpoints must use HTTPS.');
            }
        }
        return $metadata;
    }

    public function begin(string $redirectUri): array
    {
        $provider = $this->provider($this->discovery(), $redirectUri);
        $nonce = self::randomToken();
        $url = $provider->getAuthorizationUrl(['scope' => $this->scopes(), 'nonce' => $nonce]);
        return ['url' => $url, 'state' => $provider->getState(), 'nonce' => $nonce, 'pkce_code' => $provider->getPkceCode()];
    }

    public function complete(string $code, string $redirectUri, string $pkceCode, string $expectedNonce): array
    {
        $metadata = $this->discovery();
        $provider = $this->provider($metadata, $redirectUri);
        $provider->setPkceCode($pkceCode);
        $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
        $idToken = (string)($token->getValues()['id_token'] ?? '');
        if ($idToken === '') {
            throw new RuntimeException('The provider did not return an ID token.');
        }
        $jwksResponse = $this->http->get($metadata['jwks_uri'], ['headers' => ['Accept' => 'application/json']]);
        $jwks = json_decode((string)$jwksResponse->getBody(), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($jwks)) {
            throw new RuntimeException('The provider JWKS response is invalid.');
        }
        $claims = (array)JWT::decode($idToken, JWK::parseKeySet($jwks));
        $this->validateClaims($claims, $metadata['issuer'], $expectedNonce);
        $idSubject = (string)($claims['sub'] ?? '');
        $userinfo = $provider->getResourceOwner($token)->toArray();
        if (isset($userinfo['sub']) && !hash_equals($idSubject, (string)$userinfo['sub'])) {
            throw new RuntimeException('The UserInfo subject does not match the ID token.');
        }
        $claims = array_replace($claims, is_array($userinfo) ? $userinfo : []);
        $identity = $this->mapIdentity($claims);
        $identity['issuer'] = rtrim((string)$metadata['issuer'], '/');
        $identity['subject'] = trim((string)($claims['sub'] ?? ''));
        if ($identity['subject'] === '') {
            throw new RuntimeException('The subject claim is missing.');
        }
        return $identity;
    }

    public function testConfiguration(): array
    {
        $metadata = $this->discovery();
        return ['issuer' => $metadata['issuer']];
    }

    private function provider(array $metadata, string $redirectUri): GenericProvider
    {
        return new GenericProvider([
            'clientId' => (string)$this->settings['client_id'],
            'clientSecret' => (string)$this->settings['client_secret'],
            'redirectUri' => $redirectUri,
            'urlAuthorize' => $metadata['authorization_endpoint'],
            'urlAccessToken' => $metadata['token_endpoint'],
            'urlResourceOwnerDetails' => $metadata['userinfo_endpoint'],
            'pkceMethod' => AbstractProvider::PKCE_METHOD_S256,
            'scopes' => $this->scopes(),
            'scopeSeparator' => ' ',
        ]);
    }

    private function scopes(): array
    {
        $scopes = preg_split('/[\s,]+/', trim((string)($this->settings['scopes'] ?? 'openid profile'))) ?: [];
        $scopes = array_values(array_unique(array_filter(array_map('trim', $scopes))));
        if (!in_array('openid', $scopes, true)) {
            array_unshift($scopes, 'openid');
        }
        return $scopes;
    }

    private function validateClaims(array $claims, string $issuer, string $expectedNonce): void
    {
        if (rtrim((string)($claims['iss'] ?? ''), '/') !== rtrim($issuer, '/')) {
            throw new RuntimeException('The ID token issuer is invalid.');
        }
        $audience = $claims['aud'] ?? [];
        $audiences = is_array($audience) ? $audience : [$audience];
        if (!in_array((string)$this->settings['client_id'], array_map('strval', $audiences), true)) {
            throw new RuntimeException('The ID token audience is invalid.');
        }
        if (count($audiences) > 1 && (string)($claims['azp'] ?? '') !== (string)$this->settings['client_id']) {
            throw new RuntimeException('The ID token authorized party is invalid.');
        }
        if ($expectedNonce === '' || !hash_equals($expectedNonce, (string)($claims['nonce'] ?? ''))) {
            throw new RuntimeException('The ID token nonce is invalid.');
        }
    }

    private function mapIdentity(array $claims): array
    {
        $username = trim((string)$this->claim($claims, (string)$this->settings['username_claim']));
        if ($username === '' || mb_strlen($username) > 100) {
            throw new RuntimeException('The mapped username claim is missing or invalid.');
        }
        foreach (['name_claim', 'first_name_claim', 'last_name_claim', 'department_claim', 'title_claim'] as $claimSetting) {
            if (mb_strlen(trim((string)$this->claim($claims, (string)$this->settings[$claimSetting]))) > 150) {
                throw new RuntimeException('A mapped profile claim exceeds the database limit.');
            }
        }
        $groups = $this->claim($claims, (string)$this->settings['groups_claim']);
        if (!is_array($groups) || array_filter($groups, static fn ($group): bool => !is_string($group)) !== []) {
            throw new RuntimeException('The mapped groups claim must be an array of strings.');
        }
        $leaves = array_map(static function (string $group): string {
            $parts = array_values(array_filter(explode('/', trim($group, '/')), 'strlen'));
            return $parts === [] ? '' : (string)end($parts);
        }, $groups);
        $role = in_array((string)$this->settings['admin_group'], $leaves, true)
            ? 'admin'
            : (in_array((string)$this->settings['editor_group'], $leaves, true) ? 'editor' : '');
        if ($role === '') {
            throw new RuntimeException('The user is not a member of an authorized group.');
        }
        return [
            'username' => $username,
            'display_name' => trim((string)$this->claim($claims, (string)$this->settings['name_claim'])),
            'first_name' => trim((string)$this->claim($claims, (string)$this->settings['first_name_claim'])),
            'last_name' => trim((string)$this->claim($claims, (string)$this->settings['last_name_claim'])),
            'department' => trim((string)$this->claim($claims, (string)$this->settings['department_claim'])),
            'title' => trim((string)$this->claim($claims, (string)$this->settings['title_claim'])),
            'picture_url' => $this->normalizePictureUrl($this->claim($claims, (string)$this->settings['picture_claim'])),
            'role' => $role,
        ];
    }

    private function normalizePictureUrl(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $url = trim($value);
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return filter_var($url, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true) && strlen($url) <= 2048 ? $url : '';
    }

    private function claim(array $claims, string $path): mixed
    {
        $value = $claims;
        foreach (explode('.', trim($path)) as $segment) {
            if ($segment === '' || !is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
