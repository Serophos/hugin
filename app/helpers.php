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

function app_manifest(?string $key = null, mixed $default = null): mixed
{
    $manifest = $GLOBALS['app_manifest'] ?? [];
    if ($key === null || $key === '') {
        return $manifest;
    }

    $segments = explode('.', $key);
    $value = $manifest;

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

function app_available_locales(): array
{
    $langDir = rtrim((string)app_config('paths.root', dirname(__DIR__)), '/') . '/app/lang';
    $labels = [
        'en' => 'English',
        'de' => 'Deutsch',
    ];
    $locales = [];

    foreach (glob($langDir . '/*.php') ?: [] as $file) {
        $locale = basename($file, '.php');
        if ($locale === '' || preg_match('/^[a-z]{2}(?:[-_][A-Z]{2})?$/', $locale) !== 1) {
            continue;
        }
        $locales[$locale] = $labels[$locale] ?? $locale;
    }

    if ($locales === []) {
        $locale = (string)app_config('app.locale', 'en');
        $locales[$locale] = $labels[$locale] ?? $locale;
    }

    ksort($locales, SORT_NATURAL | SORT_FLAG_CASE);
    return $locales;
}

function app_resolve_locale(string $locale, ?string $default = null): string
{
    $availableLocales = app_available_locales();
    $locale = trim($locale);
    if ($locale !== '' && array_key_exists($locale, $availableLocales)) {
        return $locale;
    }

    $default = trim((string)($default ?? app_config('app.locale', 'en')));
    if ($default !== '' && array_key_exists($default, $availableLocales)) {
        return $default;
    }

    return array_key_first($availableLocales) ?: 'en';
}

function app_build_i18n(string $locale, ?string $fallbackLocale = null): \App\Core\I18n
{
    $locale = app_resolve_locale($locale);
    $fallbackLocale = app_resolve_locale((string)($fallbackLocale ?? $locale), $locale);
    $root = rtrim((string)app_config('paths.root', dirname(__DIR__)), '/');
    $i18n = new \App\Core\I18n($locale, $fallbackLocale);

    foreach (array_values(array_unique([$fallbackLocale, $locale])) as $loadedLocale) {
        $i18n->loadFile($loadedLocale, $root . '/app/lang/' . $loadedLocale . '.php');
    }

    $pluginsRoot = $root . '/plugins';
    if (is_dir($pluginsRoot)) {
        foreach (scandir($pluginsRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $langDir = $pluginsRoot . '/' . $entry . '/lang';
            if (!is_dir($langDir)) {
                continue;
            }
            foreach (array_values(array_unique([$fallbackLocale, $locale])) as $loadedLocale) {
                $i18n->loadFile($loadedLocale, $langDir . '/' . $loadedLocale . '.php', 'plugins.' . $entry);
            }
        }
    }

    return $i18n;
}

function app_switch_locale(string $locale, ?string $fallbackLocale = null): string
{
    $systemLocale = (string)app_core_setting('system.locale', app_config('app.locale', 'en'));
    $locale = app_resolve_locale($locale, $systemLocale);
    $fallbackLocale = app_resolve_locale((string)($fallbackLocale ?? app_config('app.fallback_locale', $locale)), $locale);

    $GLOBALS['i18n'] = app_build_i18n($locale, $fallbackLocale);
    $GLOBALS['i18n_locale'] = $locale;

    return $locale;
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

function asset_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $href = url($path);
    $version = public_asset_version($path);
    return $version !== '' ? append_url_query_param($href, 'v', $version) : $href;
}

function plugin_asset_url(string $pluginName, string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
    $href = url('/plugin-assets/' . rawurlencode($pluginName) . '/' . $relativePath);
    $version = plugin_asset_version($pluginName, $relativePath);
    return $version !== '' ? append_url_query_param($href, 'v', $version) : $href;
}

function public_asset_version(string $path): string
{
    $publicRoot = realpath((string)app_config('paths.public', dirname(__DIR__) . '/public'));
    if ($publicRoot === false) {
        return '';
    }

    $assetPath = parse_url($path, PHP_URL_PATH);
    $assetPath = is_string($assetPath) ? $assetPath : $path;
    $filePath = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim(rawurldecode($assetPath), '/'));
    return local_file_asset_version($publicRoot, $filePath);
}

function plugin_asset_version(string $pluginName, string $relativePath): string
{
    if (preg_match('/^[a-zA-Z0-9_-]+$/', $pluginName) !== 1) {
        return '';
    }

    $root = rtrim((string)app_config('paths.root', dirname(__DIR__)), DIRECTORY_SEPARATOR);
    $pluginRoot = realpath($root . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $pluginName);
    if ($pluginRoot === false) {
        return '';
    }

    $assetPath = parse_url($relativePath, PHP_URL_PATH);
    $assetPath = is_string($assetPath) ? $assetPath : $relativePath;
    $filePath = realpath($pluginRoot . DIRECTORY_SEPARATOR . ltrim(rawurldecode($assetPath), '/'));
    return local_file_asset_version($pluginRoot, $filePath);
}

function local_file_asset_version(string $root, string|false $filePath): string
{
    if ($filePath === false || !str_starts_with($filePath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
        return '';
    }

    $modifiedAt = filemtime($filePath);
    $size = filesize($filePath);
    if ($modifiedAt === false || $size === false) {
        return '';
    }

    return (string)$modifiedAt . '-' . (string)$size;
}

function append_url_query_param(string $href, string $key, string $value): string
{
    $fragment = '';
    $hashPosition = strpos($href, '#');
    if ($hashPosition !== false) {
        $fragment = substr($href, $hashPosition);
        $href = substr($href, 0, $hashPosition);
    }

    $separator = str_contains($href, '?') ? '&' : '?';
    return $href . $separator . rawurlencode($key) . '=' . rawurlencode($value) . $fragment;
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

function field_note_id(string $key, string $form = 'default'): string
{
    return 'note-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $form . '-' . $key);
}

function field_attrs(string $key, string $form = 'default', array|string $describedBy = []): string
{
    $describedBy = is_array($describedBy) ? $describedBy : [$describedBy];
    $describedBy = array_values(array_filter(array_map('strval', $describedBy), static fn (string $id): bool => $id !== ''));
    $attrs = [];

    if (field_error($key, $form)) {
        $attrs[] = 'class="is-invalid"';
        $attrs[] = 'aria-invalid="true"';
        $describedBy[] = field_error_id($key, $form);
    }

    if ($describedBy !== []) {
        $attrs[] = 'aria-describedby="' . e(implode(' ', array_unique($describedBy))) . '"';
    }

    return $attrs === [] ? '' : ' ' . implode(' ', $attrs);
}

function field_error_html(string $key, string $form = 'default'): string
{
    $message = field_error($key, $form);
    if (!$message) {
        return '';
    }

    return '<small id="' . e(field_error_id($key, $form)) . '" class="field-error" role="alert">' . e($message) . '</small>';
}

function app_core_settings_defaults(string $namespace): array
{
    $defaults = [
        'upload' => [
            'max_size_bytes' => 52428800,
        ],
        'monitoring' => [
            'enabled' => false,
            'api_token' => '',
            'online_threshold_seconds' => 180,
            'stale_threshold_seconds' => 1800,
        ],
        'accessibility' => [
            'contact_email' => '',
            'feedback_url' => '',
            'enforcement_url' => '',
            'visual_mode' => 'default',
            'focus_style' => 'standard',
            'motion' => 'system',
        ],
        'system' => [
            'locale' => (string)app_config('app.locale', 'en'),
        ],
    ];

    return $defaults[$namespace] ?? [];
}

function app_core_settings(string $namespace): array
{
    static $cache = [];
    if (array_key_exists($namespace, $cache)) {
        return $cache[$namespace];
    }

    $settings = app_core_settings_defaults($namespace);

    $db = $GLOBALS['app_db'] ?? null;
    if ($db && method_exists($db, 'all')) {
        try {
            $rows = $db->all('SELECT setting_key, setting_value FROM app_settings WHERE namespace = ?', [$namespace]);
            foreach ($rows as $row) {
                if (is_string($row['setting_key']) && array_key_exists($row['setting_key'], $settings)) {
                    $settings[$row['setting_key']] = (string)($row['setting_value'] ?? '');
                }
            }
        } catch (\Throwable) {
            // Keep hard-coded defaults when the database or schema is not ready yet.
        }
    }

    return $cache[$namespace] = app_normalize_core_settings($namespace, $settings);
}

function app_normalize_core_settings(string $namespace, array $settings): array
{
    if ($namespace === 'upload') {
        $settings['max_size_bytes'] = max(1, (int)($settings['max_size_bytes'] ?? 52428800));
    }

    if ($namespace === 'monitoring') {
        $settings['enabled'] = filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $settings['api_token'] = trim((string)($settings['api_token'] ?? ''));
        $settings['online_threshold_seconds'] = max(30, (int)($settings['online_threshold_seconds'] ?? 180));
        $settings['stale_threshold_seconds'] = max($settings['online_threshold_seconds'], (int)($settings['stale_threshold_seconds'] ?? 1800));
    }

    if ($namespace === 'accessibility') {
        $settings['contact_email'] = (string)($settings['contact_email'] ?? '');
        $settings['feedback_url'] = (string)($settings['feedback_url'] ?? '');
        $settings['enforcement_url'] = (string)($settings['enforcement_url'] ?? '');
        $settings['visual_mode'] = in_array($settings['visual_mode'] ?? '', ['default', 'high_contrast', 'system'], true) ? $settings['visual_mode'] : 'default';
        $settings['focus_style'] = in_array($settings['focus_style'] ?? '', ['standard', 'strong'], true) ? $settings['focus_style'] : 'standard';
        $settings['motion'] = in_array($settings['motion'] ?? '', ['system', 'reduced'], true) ? $settings['motion'] : 'system';
    }

    if ($namespace === 'system') {
        $availableLocales = app_available_locales();
        $locale = trim((string)($settings['locale'] ?? app_config('app.locale', 'en')));
        $settings['locale'] = array_key_exists($locale, $availableLocales) ? $locale : (string)app_config('app.locale', 'en');
        if (!array_key_exists($settings['locale'], $availableLocales)) {
            $settings['locale'] = array_key_first($availableLocales) ?: 'en';
        }
    }

    return $settings;
}

function app_import_legacy_config_settings(array $config): void
{
    $db = $GLOBALS['app_db'] ?? null;
    if (!$db || !method_exists($db, 'one') || !method_exists($db, 'execute')) {
        return;
    }

    $legacy = [];
    foreach (app_core_settings_defaults('upload') as $key => $default) {
        if (array_key_exists($key, $config['upload'] ?? [])) {
            $legacy['upload'][$key] = $config['upload'][$key];
        }
    }
    foreach (app_core_settings_defaults('monitoring') as $key => $default) {
        if (array_key_exists($key, $config['monitoring'] ?? [])) {
            $legacy['monitoring'][$key] = $config['monitoring'][$key];
        }
    }
    foreach (app_core_settings_defaults('accessibility') as $key => $default) {
        if (array_key_exists($key, $config['accessibility'] ?? [])) {
            $legacy['accessibility'][$key] = $config['accessibility'][$key];
        }
    }
    foreach (app_core_settings_defaults('system') as $key => $default) {
        if (array_key_exists($key, $config['app'] ?? [])) {
            $legacy['system'][$key] = $config['app'][$key];
        }
    }

    try {
        foreach ($legacy as $namespace => $settings) {
            $settings = app_normalize_core_settings($namespace, $settings);
            foreach ($settings as $key => $value) {
                $exists = $db->one(
                    'SELECT setting_key FROM app_settings WHERE namespace = ? AND setting_key = ?',
                    [$namespace, $key]
                );
                if ($exists) {
                    continue;
                }
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                $db->execute(
                    'INSERT INTO app_settings (namespace, setting_key, setting_value) VALUES (?, ?, ?)',
                    [$namespace, $key, (string)$value]
                );
            }
        }
    } catch (\Throwable) {
        // The schema may not exist on first boot before database.sql has been imported.
    }
}

function app_upload_settings(): array
{
    return app_core_settings('upload');
}

function app_monitoring_settings(): array
{
    return app_core_settings('monitoring');
}

function app_accessibility_settings(): array
{
    return app_core_settings('accessibility');
}

function app_system_settings(): array
{
    return app_core_settings('system');
}

function app_core_setting(string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key, 2);
    if (count($segments) !== 2) {
        return $default;
    }

    $settings = app_core_settings($segments[0]);
    return $settings[$segments[1]] ?? $default;
}

function admin_accessibility_body_classes(): string
{
    $settings = app_accessibility_settings();
    return trim(sprintf(
        'a11y-visual-%s a11y-focus-%s a11y-motion-%s',
        preg_replace('/[^a-z0-9_-]/i', '', $settings['visual_mode']),
        preg_replace('/[^a-z0-9_-]/i', '', $settings['focus_style']),
        preg_replace('/[^a-z0-9_-]/i', '', $settings['motion'])
    ));
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
    static $icons = [
        'about', 'add', 'back', 'cancel', 'check', 'dashboard', 'delete',
        'dialog-error', 'dialog-exclamation', 'dialog-information',
        'dialog-question', 'dialog-trash', 'dialog-warning', 'displays',
        'edit', 'history', 'locations', 'login', 'logout', 'manage', 'media', 'menu',
        'move', 'open', 'playlists', 'plugins', 'primary-display', 'preview', 'reload',
        'remove', 'save', 'schedules', 'settings', 'slides', 'templates',
        'upload', 'users',
    ];

    if (!in_array($name, $icons, true)) {
        return '';
    }

    $href = asset_url('/assets/icons/admin/' . rawurlencode($name) . '.svg');
    return '<span class="button-icon" aria-hidden="true" style="--button-icon-url: url(&quot;' . e($href) . '&quot;)"></span>';
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

function current_user_needs_password_change(): bool
{
    $userId = (int)(current_user()['id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $db = $GLOBALS['app_db'] ?? null;
    if (!$db || !method_exists($db, 'one')) {
        return false;
    }

    try {
        $user = $db->one('SELECT password_changed_at FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [$userId]);
    } catch (Throwable) {
        return false;
    }

    return $user !== null && empty($user['password_changed_at']);
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

function list_uploaded_fonts(?array $ids = null): array
{
    $db = $GLOBALS['app_db'] ?? null;
    if (!$db || !method_exists($db, 'all')) {
        return [];
    }

    $params = [];
    $where = " WHERE media_kind = 'font'";
    if ($ids !== null) {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): int => (int)$id,
            $ids
        ), static fn(int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $where .= ' AND id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')';
        $params = $ids;
    }

    try {
        $rows = $db->all(
            'SELECT * FROM media_assets' . $where . '
             ORDER BY COALESCE(NULLIF(font_family_name, \'\'), name) ASC,
                      COALESCE(NULLIF(font_subfamily, \'\'), \'Regular\') ASC,
                      id ASC',
            $params
        );
    } catch (\Throwable) {
        return [];
    }

    $fonts = [];
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0 || empty($row['file_path'])) {
            continue;
        }

        $format = strtolower((string)($row['font_format'] ?? pathinfo((string)$row['file_path'], PATHINFO_EXTENSION)));
        $row['font_format'] = in_array($format, ['woff2', 'woff', 'ttf', 'otf'], true) ? $format : '';
        $row['css_family'] = uploaded_font_css_family($id);
        $row['css_weight'] = uploaded_font_css_weight($row);
        $row['css_style'] = uploaded_font_css_style($row);
        $row['css_format'] = font_format_css_label((string)$row['font_format']);
        $row['label'] = (string)(($row['font_full_name'] ?? '') ?: ($row['font_family_name'] ?? '') ?: ($row['name'] ?? ''));
        $fonts[$id] = $row;
    }

    return $fonts;
}

function uploaded_font_token(int $id): string
{
    return 'font:' . max(0, $id);
}

function uploaded_font_id_from_token(string $token): int
{
    $token = trim($token);
    if (!preg_match('/^font:([1-9][0-9]*)$/', $token, $matches)) {
        return 0;
    }

    return (int)$matches[1];
}

function uploaded_font_ids_from_tokens(array $tokens): array
{
    $ids = [];
    foreach ($tokens as $token) {
        $id = uploaded_font_id_from_token((string)$token);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_map('intval', array_keys($ids));
}

function uploaded_font_css_family(int $id): string
{
    return 'hugin-font-' . max(0, $id);
}

function uploaded_font_css_weight(array $font): int
{
    $weight = (int)($font['font_weight'] ?? 0);
    return $weight >= 1 && $weight <= 1000 ? $weight : 400;
}

function uploaded_font_css_style(array $font): string
{
    $style = strtolower((string)($font['font_subfamily'] ?? ''));
    return str_contains($style, 'italic') || str_contains($style, 'oblique') ? 'italic' : 'normal';
}

function font_format_css_label(string $format): string
{
    return match (strtolower($format)) {
        'woff2' => 'woff2',
        'woff' => 'woff',
        'ttf' => 'truetype',
        'otf' => 'opentype',
        default => '',
    };
}

function css_string_literal(string $value): string
{
    return str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], $value);
}

function uploaded_font_face_css(array $font): string
{
    $id = (int)($font['id'] ?? 0);
    $path = (string)($font['file_path'] ?? '');
    if ($id <= 0 || $path === '') {
        return '';
    }

    $format = font_format_css_label((string)($font['font_format'] ?? ''));
    $formatCss = $format !== '' ? " format('" . css_string_literal($format) . "')" : '';

    return implode("\n", [
        '@font-face {',
        "    font-family: '" . css_string_literal(uploaded_font_css_family($id)) . "';",
        "    src: url('" . css_string_literal(asset_url($path)) . "')" . $formatCss . ';',
        '    font-weight: ' . uploaded_font_css_weight($font) . ';',
        '    font-style: ' . uploaded_font_css_style($font) . ';',
        '    font-display: swap;',
        '}',
    ]);
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
    return normalize_integer_range($value, 600, 300, 1500);
}

function normalize_text_slide_animation_delay_ms(mixed $value): int
{
    return normalize_integer_range($value, 0, 0, 5000);
}

function normalize_text_slide_box_width_percent(mixed $value): int
{
    return normalize_integer_range($value, 76, 25, 95);
}

function text_slide_radius_corner_keys(): array
{
    return ['top_left', 'top_right', 'bottom_right', 'bottom_left'];
}

function normalize_text_slide_radius_rem(mixed $value): ?float
{
    $value = trim((string)$value);
    if ($value === '' || preg_match('/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/', $value) !== 1) {
        return null;
    }

    return round(max(0, min(10, (float)$value)), 2);
}

function format_text_slide_radius_rem(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $normalized = normalize_text_slide_radius_rem($value);
    if ($normalized === null) {
        return '';
    }

    return rtrim(rtrim(sprintf('%.2F', $normalized), '0'), '.');
}

function normalize_text_slide_radius_corners(string $mode, mixed $allValue, array $cornerValues): array
{
    $empty = array_fill_keys(text_slide_radius_corner_keys(), null);
    if ($mode === 'default') {
        return $empty;
    }

    if ($mode === 'all') {
        $radius = normalize_text_slide_radius_rem($allValue);
        return array_fill_keys(text_slide_radius_corner_keys(), $radius);
    }

    if ($mode !== 'custom') {
        return $empty;
    }

    $normalized = [];
    foreach (text_slide_radius_corner_keys() as $corner) {
        $normalized[$corner] = normalize_text_slide_radius_rem($cornerValues[$corner] ?? '');
    }

    return $normalized;
}

function text_slide_radius_mode_from_values(array $values): string
{
    $corners = [];
    foreach (text_slide_radius_corner_keys() as $corner) {
        $value = $values[$corner] ?? null;
        $corners[$corner] = normalize_text_slide_radius_rem($value);
    }

    $configured = array_values(array_filter($corners, static fn(?float $value): bool => $value !== null));
    if ($configured === []) {
        return 'default';
    }

    return count(array_unique(array_values($corners), SORT_REGULAR)) === 1 ? 'all' : 'custom';
}

function text_slide_radius_all_value(array $values): string
{
    if (text_slide_radius_mode_from_values($values) !== 'all') {
        return '';
    }

    foreach (text_slide_radius_corner_keys() as $corner) {
        $value = $values[$corner] ?? null;
        if ($value !== null && $value !== '') {
            return format_text_slide_radius_rem($value);
        }
    }

    return '';
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
