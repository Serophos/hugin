<?php
/**
 * Hugin - Digital Signage System
 * Copyright (C) 2026 Thees Winkler
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Source code: https://github.com/Serophos/hugin
 */

namespace App\Core;

class Auth
{
    public function __construct(private Database $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->db->one(
            'SELECT id, username, display_name, role, password_hash, is_active FROM users WHERE username = ? LIMIT 1',
            [$username]
        );

        if (!$user || !(int)$user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['_user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
        ];

        session_regenerate_id(true);
        return true;
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
        unset($_SESSION['_user']);
        session_regenerate_id(true);
    }
}
