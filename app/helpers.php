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

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'] ?? [];
    if ($key === null || $key === '') {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function current_locale(): string
{
    return (string)($GLOBALS['i18n_locale'] ?? app_config('app.locale', 'en'));
}

function __(string $key, array $replace = [], ?string $default = null): string
{
    $i18n = $GLOBALS['i18n'] ?? null;
    if (!$i18n || !method_exists($i18n, 'translate')) {
        $message = $default ?? $key;
        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string)$value, $message);
        }
        return $message;
    }

    return $i18n->translate($key, $replace, $default);
}

function trans_has(string $key): bool
{
    $i18n = $GLOBALS['i18n'] ?? null;
    return $i18n && method_exists($i18n, 'has') ? (bool)$i18n->has($key) : false;
}

function enum_label(string $prefix, string $value, ?string $fallback = null): string
{
    $key = rtrim($prefix, '.') . '.' . $value;
    return __($key, [], $fallback ?? $value);
}

function url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $baseUrl = rtrim((string)app_config('app.base_url', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $baseUrl . ($path === '/' ? '' : $path);
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function selected(mixed $a, mixed $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function checked(mixed $value): string
{
    return $value ? 'checked' : '';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        echo __('errors.csrf_mismatch', [], 'CSRF token mismatch.');
        exit;
    }
}

function current_user(): ?array
{
    return $_SESSION['_user'] ?? null;
}

function current_user_name(): string
{
    $user = current_user();
    if (!$user) {
        return '';
    }

    return (string)($user['display_name'] ?: $user['username']);
}

function current_user_role(): string
{
    return (string)(current_user()['role'] ?? '');
}

function current_user_role_label(): string
{
    $role = current_user_role();
    return $role === '' ? '' : enum_label('roles', $role, $role);
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'display';
}

function client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['HTTP_X_REAL_IP'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate || !is_string($candidate)) {
            continue;
        }
        $ip = trim(explode(',', $candidate)[0]);
        if ($ip !== '') {
            return substr($ip, 0, 45);
        }
    }

    return __('common.unknown', [], 'unknown');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float)$bytes;
    foreach ($units as $index => $unit) {
        if ($value < 1024 || $index === array_key_last($units)) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $unit;
        }
        $value /= 1024;
    }
    return $bytes . ' B';
}
