<?php
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


function normalize_hex_color(?string $value, string $default = '#111827'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $default;
    }

    if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $m)) {
        $chars = strtolower($m[1]);
        return sprintf('#%1$s%1$s%2$s%2$s%3$s%3$s', $chars[0], $chars[1], $chars[2]);
    }

    if (preg_match('/^#([0-9a-fA-F]{6})$/', $value)) {
        return strtolower($value);
    }

    return strtolower($default);
}

function hex_to_rgb(string $hex): array
{
    $hex = ltrim(normalize_hex_color($hex), '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function color_luminance(string $hex): float
{
    [$r, $g, $b] = hex_to_rgb($hex);
    $channels = [$r / 255, $g / 255, $b / 255];
    $linear = array_map(static function (float $channel): float {
        return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }, $channels);

    return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
}

function readable_text_color(string $backgroundHex): string
{
    return color_luminance($backgroundHex) > 0.42 ? '#111827' : '#f8fafc';
}

function readable_overlay_rgba(string $backgroundHex): string
{
    if (color_luminance($backgroundHex) > 0.42) {
        return 'rgba(255, 255, 255, 0.78)';
    }

    return 'rgba(15, 23, 42, 0.68)';
}

function render_markup(string $input): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string)$input));
    if ($text === '') {
        return '';
    }

    $blocks = preg_split("/\n{2,}/", $text) ?: [];
    $html = [];

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static fn($line) => $line !== ''));
        if ($lines === []) {
            continue;
        }

        $isUl = true;
        $isOl = true;
        foreach ($lines as $line) {
            $isUl = $isUl && (bool)preg_match('/^[-*]\s+/', $line);
            $isOl = $isOl && (bool)preg_match('/^\d+\.\s+/', $line);
        }

        if ($isUl) {
            $items = array_map(static function (string $line): string {
                $content = preg_replace('/^[-*]\s+/', '', $line) ?? $line;
                return '<li>' . render_markup_inline($content) . '</li>';
            }, $lines);
            $html[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }

        if ($isOl) {
            $items = array_map(static function (string $line): string {
                $content = preg_replace('/^\d+\.\s+/', '', $line) ?? $line;
                return '<li>' . render_markup_inline($content) . '</li>';
            }, $lines);
            $html[] = '<ol>' . implode('', $items) . '</ol>';
            continue;
        }

        if (preg_match('/^#{1,3}\s+/', $lines[0])) {
            $level = min(3, max(1, strspn($lines[0], '#')));
            $content = trim(substr($lines[0], $level));
            $html[] = sprintf('<h%d>%s</h%d>', $level, render_markup_inline($content), $level);
            if (count($lines) > 1) {
                $rest = implode("<br>\n", array_map('render_markup_inline', array_slice($lines, 1)));
                $html[] = '<p>' . $rest . '</p>';
            }
            continue;
        }

        $paragraph = implode("<br>\n", array_map('render_markup_inline', $lines));
        $html[] = '<p>' . $paragraph . '</p>';
    }

    return implode("\n", $html);
}

function render_markup_inline(string $text): string
{
    $escaped = e($text);
    $escaped = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $escaped) ?? $escaped;
    $escaped = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/s', '<em>$1</em>', $escaped) ?? $escaped;
    $escaped = preg_replace('/`(.+?)`/s', '<code>$1</code>', $escaped) ?? $escaped;
    return $escaped;
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
