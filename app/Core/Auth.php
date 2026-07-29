<?php
namespace App\Core;

class Auth
{
    public function __construct(private Database $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->db->one(
            'SELECT id, username, display_name, first_name, last_name, department, title, picture_url, role, password_hash, auth_provider, is_active FROM users WHERE username = ? LIMIT 1',
            [$username]
        );
        if (!$user || !(int)$user['is_active'] || ($user['auth_provider'] ?? 'local') !== 'local') {
            return false;
        }
        if (!$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        $this->establishSession($user, 'local');
        return true;
    }

    public function loginOpenId(array $identity): bool
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $user = $this->db->one(
                'SELECT id, is_active FROM users WHERE auth_provider = ? AND oidc_issuer = ? AND oidc_subject = ? LIMIT 1 FOR UPDATE',
                ['openid', $identity['issuer'], $identity['subject']]
            );
            if (!$user) {
                if ($this->db->one('SELECT id FROM users WHERE username = ? LIMIT 1', [$identity['username']])) {
                    throw new \RuntimeException('The mapped OpenID username conflicts with an existing account.');
                }
                $this->db->execute(
                    'INSERT INTO users (username, display_name, first_name, last_name, department, title, picture_url, role, password_hash, auth_provider, oidc_issuer, oidc_subject, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 1)',
                    [$identity['username'], $identity['display_name'], $identity['first_name'], $identity['last_name'], $identity['department'], $identity['title'], $identity['picture_url'], $identity['role'], 'openid', $identity['issuer'], $identity['subject']]
                );
                $user = ['id' => (int)$this->db->lastInsertId(), 'is_active' => 1];
            } else {
                if (!(int)$user['is_active']) {
                    $pdo->rollBack();
                    return false;
                }
                if ($this->db->one('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1', [$identity['username'], $user['id']])) {
                    throw new \RuntimeException('The mapped OpenID username conflicts with an existing account.');
                }
                $this->db->execute(
                    'UPDATE users SET username = ?, display_name = ?, first_name = ?, last_name = ?, department = ?, title = ?, picture_url = ?, role = ? WHERE id = ?',
                    [$identity['username'], $identity['display_name'], $identity['first_name'], $identity['last_name'], $identity['department'], $identity['title'], $identity['picture_url'], $identity['role'], $user['id']]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->establishSession([
            'id' => $user['id'],
            'username' => $identity['username'],
            'display_name' => $identity['display_name'],
            'first_name' => $identity['first_name'],
            'last_name' => $identity['last_name'],
            'department' => $identity['department'],
            'title' => $identity['title'],
            'picture_url' => $identity['picture_url'],
            'role' => $identity['role'],
        ], 'openid');
        return true;
    }

    private function establishSession(array $user, string $provider): void
    {
        $_SESSION['_user'] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'display_name' => (string)($user['display_name'] ?? ''),
            'first_name' => (string)($user['first_name'] ?? ''),
            'last_name' => (string)($user['last_name'] ?? ''),
            'department' => (string)($user['department'] ?? ''),
            'title' => (string)($user['title'] ?? ''),
            'picture_url' => (string)($user['picture_url'] ?? ''),
            'role' => (string)$user['role'],
            'auth_provider' => $provider,
        ];
        session_regenerate_id(true);
    }

    public function user(): ?array
    {
        return $_SESSION['_user'] ?? null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function check(): bool
    {
        return !empty($this->user());
    }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            $requested = (string)($_SERVER['REQUEST_URI'] ?? '/admin');
            $path = parse_url($requested, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/admin') && !str_starts_with($path, '/admin/login') && !str_starts_with($path, '/admin/oidc')) {
                $_SESSION['_auth_return_to'] = $requested;
            }
            redirect('/admin/login');
        }
    }

    public function requireRole(string $role): void
    {
        $this->requireLogin();
        if (($this->user()['role'] ?? null) !== $role) {
            http_response_code(403);
            echo __('errors.forbidden', [], 'Forbidden');
            exit;
        }
    }

    public function logout(): void
    {
        unset($_SESSION['_user'], $_SESSION['_oidc']);
        session_regenerate_id(true);
    }
}
