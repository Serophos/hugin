<?php

declare(strict_types=1);

/**
 * A production heartbeat must never wait for an unrelated admin request that
 * happens to carry the same PHP session cookie. Preview-shaped heartbeat
 * requests and all other routes retain normal session and CSRF behavior.
 */
function app_request_requires_session(string $method, string $requestUri): bool
{
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path)) {
        return true;
    }

    if (
        strtoupper($method) !== 'POST'
        || preg_match('#^/display/[a-zA-Z0-9_-]+/heartbeat$#D', $path) !== 1
    ) {
        return true;
    }

    $query = parse_url($requestUri, PHP_URL_QUERY);
    $queryParameters = [];
    if (is_string($query)) {
        parse_str($query, $queryParameters);
    }
    $preview = $queryParameters['preview'] ?? '';
    if (!is_scalar($preview) && $preview !== null) {
        return true;
    }

    return in_array(strtolower(trim((string)$preview)), ['1', 'true', 'yes', 'on'], true);
}
