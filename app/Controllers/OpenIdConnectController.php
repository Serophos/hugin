<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\OpenIdConnectService;

class OpenIdConnectController
{
    public function __construct(private Auth $auth, private View $view)
    {
    }

    public function loginOrLocal(): void
    {
        if (OpenIdConnectService::isConfigured(app_openid_settings())) {
            $this->start();
        }
        $this->localLoginView();
    }

    public function localLoginView(): void
    {
        $this->view->render('admin/login', [
            'error' => flash('error'),
            'openidAvailable' => OpenIdConnectService::isConfigured(app_openid_settings()),
            'localLogin' => true,
        ]);
    }

    public function start(): void
    {
        $settings = app_openid_settings();
        if (!OpenIdConnectService::isConfigured($settings)) {
            flash('error', __('openid.not_configured'));
            redirect('/admin/login/local');
        }
        try {
            $flow = (new OpenIdConnectService($settings))->begin($this->callbackUrl());
            $_SESSION['_oidc'] = [
                'state' => $flow['state'],
                'nonce' => $flow['nonce'],
                'pkce_code' => $flow['pkce_code'],
                'created_at' => time(),
            ];
            header('Location: ' . $flow['url']);
            exit;
        } catch (\Throwable $e) {
            $this->logFailure('start', $e);
            flash('error', __('openid.login_failed'));
            redirect('/admin/login/local');
        }
    }

    public function callback(): void
    {
        $flow = is_array($_SESSION['_oidc'] ?? null) ? $_SESSION['_oidc'] : [];
        unset($_SESSION['_oidc']);
        try {
            if ((string)($_GET['error'] ?? '') !== '') {
                throw new \RuntimeException('Provider returned error: ' . (string)$_GET['error']);
            }
            $state = (string)($_GET['state'] ?? '');
            if ($state === '' || empty($flow['state']) || !hash_equals((string)$flow['state'], $state)) {
                throw new \RuntimeException('OpenID state mismatch.');
            }
            if ((int)($flow['created_at'] ?? 0) < time() - 600) {
                throw new \RuntimeException('OpenID authentication transaction expired.');
            }
            $code = trim((string)($_GET['code'] ?? ''));
            if ($code === '') {
                throw new \RuntimeException('Authorization code is missing.');
            }
            $identity = (new OpenIdConnectService(app_openid_settings()))->complete(
                $code,
                $this->callbackUrl(),
                (string)($flow['pkce_code'] ?? ''),
                (string)($flow['nonce'] ?? '')
            );
            if (!$this->auth->loginOpenId($identity)) {
                throw new \RuntimeException('The OpenID user is inactive.');
            }
            flash('success', __('messages.welcome_back'));
            $returnTo = (string)($_SESSION['_auth_return_to'] ?? '/admin');
            unset($_SESSION['_auth_return_to']);
            redirect($this->safeReturnPath($returnTo));
        } catch (\Throwable $e) {
            $this->logFailure('callback', $e);
            flash('error', __('openid.login_failed'));
            redirect('/admin/login/local');
        }
    }

    public function testConfiguration(): void
    {
        $this->auth->requireRole('admin');
        try {
            $result = (new OpenIdConnectService(app_openid_settings()))->testConfiguration();
            flash('success', __('openid.test_success', ['issuer' => (string)$result['issuer']]));
        } catch (\Throwable $e) {
            $this->logFailure('configuration test', $e);
            flash('error', __('openid.test_failed'));
        }
        redirect('/admin/settings');
    }

    private function callbackUrl(): string
    {
        return url('/admin/oidc/callback');
    }

    private function safeReturnPath(string $path): string
    {
        $parsed = parse_url($path);
        return is_array($parsed)
            && !isset($parsed['scheme'], $parsed['host'])
            && str_starts_with((string)($parsed['path'] ?? ''), '/admin')
            ? $path
            : '/admin';
    }

    private function logFailure(string $stage, \Throwable $e): void
    {
        error_log(sprintf('Hugin OpenID Connect %s failed: %s', $stage, $e->getMessage()));
    }
}
