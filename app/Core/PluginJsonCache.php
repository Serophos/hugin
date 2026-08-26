<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class PluginJsonCache
{
    /** @param callable():array<mixed> $loader @return array<mixed> */
    public static function load(string $cacheFile, int $ttlSeconds, bool $forceRefresh, callable $loader): array
    {
        $ttlSeconds = max(1, $ttlSeconds);
        if (!$forceRefresh) {
            $cached = self::readFresh($cacheFile, $ttlSeconds);
            if ($cached !== null) {
                return $cached;
            }
        }

        $lockFile = $cacheFile . '.lock';
        $lock = @fopen($lockFile, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open plugin cache lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not lock plugin cache.');
            }

            if (!$forceRefresh) {
                $cached = self::readFresh($cacheFile, $ttlSeconds);
                if ($cached !== null) {
                    return $cached;
                }
            }

            $payload = $loader();
            if (!is_array($payload)) {
                throw new RuntimeException('Plugin cache loader returned invalid JSON data.');
            }

            self::replace($cacheFile, $payload);
            return $payload;
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    public static function revision(string $cacheFile): string
    {
        $bytes = @file_get_contents($cacheFile);
        return is_string($bytes) && $bytes !== '' ? hash('sha256', $bytes) : 'missing';
    }

    /** @return array<mixed>|null */
    private static function readFresh(string $cacheFile, int $ttlSeconds): ?array
    {
        if (!is_file($cacheFile) || (filemtime($cacheFile) ?: 0) < time() - $ttlSeconds) {
            return null;
        }

        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        return is_array($cached) ? $cached : null;
    }

    /** @param array<mixed> $payload */
    private static function replace(string $cacheFile, array $payload): void
    {
        try {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            throw new RuntimeException('Could not encode plugin cache JSON.', 0, $error);
        }

        $temporary = tempnam(dirname($cacheFile), basename($cacheFile) . '.tmp-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create temporary plugin cache file.');
        }

        try {
            if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
                throw new RuntimeException('Could not write temporary plugin cache file.');
            }
            @chmod($temporary, 0664);
            if (!rename($temporary, $cacheFile)) {
                throw new RuntimeException('Could not replace plugin cache file.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
