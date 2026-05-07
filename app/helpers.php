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

function flash_form_state(string $form, array $oldInput = [], array $errors = []): void
{
    $_SESSION['_form_state'][$form] = [
        'old' => $oldInput,
        'errors' => $errors,
    ];
}

function form_state(string $form = 'default'): array
{
    static $state = null;

    if ($state === null) {
        $state = is_array($_SESSION['_form_state'] ?? null) ? $_SESSION['_form_state'] : [];
        unset($_SESSION['_form_state']);
    }

    return is_array($state[$form] ?? null) ? $state[$form] : [];
}

function form_has_old(string $form = 'default'): bool
{
    $state = form_state($form);
    return array_key_exists('old', $state) && is_array($state['old']);
}

function old_input(string $form = 'default'): array
{
    $state = form_state($form);
    return is_array($state['old'] ?? null) ? $state['old'] : [];
}

function form_data_get(array $data, string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $data)) {
        return $data[$key];
    }

    $segments = explode('.', $key);
    $value = $data;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function old(string $key, mixed $default = '', string $form = 'default'): mixed
{
    $state = form_state($form);
    $old = is_array($state['old'] ?? null) ? $state['old'] : [];
    return form_data_get($old, $key, $default);
}

function old_array(string $key, array $default = [], string $form = 'default'): array
{
    $value = old($key, $default, $form);
    return is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
}

function old_checked(string $key, mixed $default = false, string $form = 'default'): string
{
    $value = form_has_old($form) ? old($key, false, $form) : $default;
    return checked(!empty($value));
}

function old_selected(string $key, mixed $option, mixed $default = '', string $form = 'default'): string
{
    return selected(old($key, $default, $form), $option);
}

function field_error(string $key, string $form = 'default'): ?string
{
    $state = form_state($form);
    $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
    $message = form_data_get($errors, $key);
    return is_string($message) && $message !== '' ? $message : null;
}

function field_error_id(string $key, string $form = 'default'): string
{
    return 'error-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $form . '-' . $key);
}

function field_attrs(string $key, string $form = 'default'): string
{
    if (!field_error($key, $form)) {
        return '';
    }

    return ' class="is-invalid" aria-invalid="true" aria-describedby="' . e(field_error_id($key, $form)) . '"';
}

function field_error_html(string $key, string $form = 'default'): string
{
    $message = field_error($key, $form);
    if (!$message) {
        return '';
    }

    return '<small id="' . e(field_error_id($key, $form)) . '" class="field-error">' . e($message) . '</small>';
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

function admin_icon(string $name): string
{
    $icons = [
        'add' => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
        'back' => '<path d="M15 18l-6-6 6-6"></path><path d="M9 12h11"></path>',
        'cancel' => '<path d="M6 6l12 12"></path><path d="M18 6L6 18"></path>',
        'delete' => '<path d="M5 7h14"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M8 7l1 13h6l1-13"></path><path d="M9 7V5h6v2"></path>',
        'edit' => '<path d="M4 20h4L18.5 9.5l-4-4L4 16v4z"></path><path d="M13.5 5.5l4 4"></path>',
        'login' => '<path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M14 4h5v16h-5"></path>',
        'manage' => '<path d="M4 5h7v7H4z"></path><path d="M13 5h7v7h-7z"></path><path d="M4 14h7v5H4z"></path><path d="M13 14h7v5h-7z"></path>',
        'move' => '<path d="M5 12h14"></path><path d="M12 5l7 7-7 7"></path>',
        'open' => '<path d="M14 5h5v5"></path><path d="M10 14l9-9"></path><path d="M19 14v5H5V5h5"></path>',
        'remove' => '<path d="M5 12h14"></path>',
        'reload' => '<path d="M20 12a8 8 0 1 1-2.34-5.66"></path><path d="M20 4v6h-6"></path>',
        'save' => '<path d="M5 4h11l3 3v13H5z"></path><path d="M8 4v6h8"></path><path d="M8 20v-6h8v6"></path>',
        'settings' => '<path d="M4 7h16"></path><path d="M4 17h16"></path><circle cx="9" cy="7" r="2"></circle><circle cx="15" cy="17" r="2"></circle>',
        'upload' => '<path d="M12 16V5"></path><path d="M8 9l4-4 4 4"></path><path d="M5 19h14"></path>',
    ];

    if (!isset($icons[$name])) {
        return '';
    }

    return '<svg class="button-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $icons[$name] . '</svg>';
}

function require_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        $refererPath = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
        if (request_body_too_large()) {
            $errorMessage = __('errors.request_too_large', [], 'Request body too large. Please upload a smaller file or raise your server limit.');
            if (is_string($refererPath) && str_starts_with($refererPath, '/admin')) {
                flash('error', $errorMessage);
                redirect($refererPath);
            }

            http_response_code(413);
            echo $errorMessage;
            exit;
        }

        if (is_string($refererPath) && str_starts_with($refererPath, '/admin')) {
            flash('error', __('errors.csrf_mismatch', [], 'CSRF token mismatch.'));
            redirect($refererPath);
        }

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

function rgba_color_string(int $red, int $green, int $blue, float $alpha = 1.0): string
{
    $red = max(0, min(255, $red));
    $green = max(0, min(255, $green));
    $blue = max(0, min(255, $blue));
    $alpha = max(0, min(1, $alpha));
    $alphaText = rtrim(rtrim(sprintf('%.3f', $alpha), '0'), '.');

    return sprintf('rgba(%d, %d, %d, %s)', $red, $green, $blue, $alphaText === '' ? '0' : $alphaText);
}

function hex_to_rgba(string $hex, float $alpha = 1.0): string
{
    [$red, $green, $blue] = hex_to_rgb($hex);
    return rgba_color_string($red, $green, $blue, $alpha);
}

function normalize_css_rgba_color(?string $value, string $default = 'rgba(17, 24, 39, 1)'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $default;
    }

    if (strcasecmp($value, 'transparent') === 0) {
        return 'rgba(0, 0, 0, 0)';
    }

    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value, $m)) {
        $hex = strtolower($m[1]);
        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $red = hexdec($hex[0] . $hex[0]);
            $green = hexdec($hex[1] . $hex[1]);
            $blue = hexdec($hex[2] . $hex[2]);
            $alpha = strlen($hex) === 4 ? hexdec($hex[3] . $hex[3]) / 255 : 1.0;
            return rgba_color_string($red, $green, $blue, $alpha);
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $alpha = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;
        return rgba_color_string($red, $green, $blue, $alpha);
    }

    if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*([0-9]*\.?[0-9]+)\s*)?\)$/i', $value, $m)) {
        $red = (int)$m[1];
        $green = (int)$m[2];
        $blue = (int)$m[3];
        $alpha = isset($m[4]) && $m[4] !== '' ? (float)$m[4] : 1.0;
        if ($red <= 255 && $green <= 255 && $blue <= 255 && $alpha >= 0 && $alpha <= 1) {
            return rgba_color_string($red, $green, $blue, $alpha);
        }
    }

    return $default;
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

function text_slide_position_options(): array
{
    return ['top-left', 'top-right', 'center', 'bottom-left', 'bottom-right'];
}

function text_slide_layout_options(): array
{
    return text_slide_position_options();
}

function normalize_text_slide_layout(?string $value): string
{
    $value = (string)$value;
    return in_array($value, text_slide_layout_options(), true) ? $value : 'center';
}

function text_slide_qr_position_options(): array
{
    return text_slide_position_options();
}

function normalize_text_slide_qr_position(?string $value): string
{
    $value = (string)$value;
    return in_array($value, text_slide_qr_position_options(), true) ? $value : 'bottom-right';
}

function normalize_text_slide_qr_size_percent(mixed $value): int
{
    return normalize_integer_range($value, 15, 8, 40);
}

function text_slide_animation_options(): array
{
    return ['none', 'fade-up', 'fly-left', 'fly-right', 'fly-top', 'fly-bottom', 'soft-bounce', 'gentle-zoom'];
}

function normalize_text_slide_animation(?string $value): string
{
    $value = (string)$value;
    return in_array($value, text_slide_animation_options(), true) ? $value : 'none';
}

function normalize_integer_range(mixed $value, int $default, int $min, int $max): int
{
    $value = trim((string)$value);
    if ($value === '' || preg_match('/^-?[0-9]+$/', $value) !== 1) {
        return $default;
    }

    return max($min, min($max, (int)$value));
}

function normalize_text_slide_animation_duration_ms(mixed $value): int
{
    return normalize_integer_range($value, 600, 300, 800);
}

function normalize_text_slide_animation_delay_ms(mixed $value): int
{
    return normalize_integer_range($value, 0, 0, 5000);
}

function normalize_text_slide_box_width_percent(mixed $value): int
{
    return normalize_integer_range($value, 76, 25, 95);
}

function render_markup(string $input): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string)$input));
    if ($text === '') {
        return '';
    }

    $parser = new Parsedown();
    $parser->setSafeMode(true);
    $parser->setBreaksEnabled(true);

    return $parser->text($text);
}

function render_markup_inline(string $text): string
{
    $parser = new Parsedown();
    $parser->setSafeMode(true);
    $parser->setUrlsLinked(true);

    return $parser->line(trim((string)$text));
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

function parse_size(string $value): int
{
    if (!preg_match('/^\s*([\d.]+)\s*([kmgtpezy]?)b?\s*$/i', $value, $matches)) {
        return 0;
    }

    $number = (float)$matches[1];
    $unit = strtolower($matches[2]);

    return (int) match ($unit) {
        'k' => $number * 1024,
        'm' => $number * 1024 ** 2,
        'g' => $number * 1024 ** 3,
        't' => $number * 1024 ** 4,
        'p' => $number * 1024 ** 5,
        'e' => $number * 1024 ** 6,
        'z' => $number * 1024 ** 7,
        'y' => $number * 1024 ** 8,
        default => $number,
    };
}

function request_body_too_large(): bool
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return false;
    }

    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength <= 0) {
        return false;
    }

    $postMaxSize = parse_size((string)ini_get('post_max_size'));
    $uploadMaxFilesize = parse_size((string)ini_get('upload_max_filesize'));
    $limit = max($postMaxSize, $uploadMaxFilesize);

    return $limit > 0 && $contentLength > $limit;
}
