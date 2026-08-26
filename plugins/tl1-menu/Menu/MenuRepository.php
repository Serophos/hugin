<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Menu;

use DateTimeInterface;
use RuntimeException;
use Throwable;

final class MenuRepository
{
    private string $cacheFile;
    private int $cacheTtl;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly MensaXmlParser $parser, private readonly array $config, ?string $cacheFile = null)
    {
        $this->cacheTtl = max(60, (int)($config['cache_ttl'] ?? 1800));
        $this->cacheFile = $cacheFile ?? $this->resolveCacheFile();
    }

    /** @return list<MenuItem> */
    public function findForDay(string $mensa, DateTimeInterface|string $date, bool $excludeDefaultTypes = true, bool $refresh = false): array
    {
        return $this->parser->getMenuForDay($this->getXmlFile($refresh), $mensa, $date, $excludeDefaultTypes);
    }

    /** @param array<string, mixed> $filters @return list<MenuItem> */
    public function findByFilters(array $filters = [], bool $refresh = false): array
    {
        return $this->parser->parseFile($this->getXmlFile($refresh), $filters);
    }

    public function getXmlFile(bool $refresh = false): string
    {
        $this->ensureCacheDirectoryExists();
        if (!$refresh && $this->isCacheFresh()) {
            return $this->cacheFile;
        }

        return $this->refreshCache(true, !$refresh);
    }

    public function refreshCache(bool $allowCachedFallback = false, bool $skipIfFreshAfterLock = false): string
    {
        $this->ensureCacheDirectoryExists();
        $lock = $this->acquireRefreshLock();
        try {
            if ($skipIfFreshAfterLock && $this->isCacheFresh()) {
                return $this->cacheFile;
            }

            try {
                $xml = $this->downloadXml();
            } catch (Throwable $error) {
                if ($allowCachedFallback && is_file($this->cacheFile)) {
                    return $this->cacheFile;
                }
                throw $error;
            }

            $this->writeValidatedCache($xml);
            return $this->cacheFile;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function contentRevision(): string
    {
        if (!is_file($this->cacheFile) || !is_readable($this->cacheFile)) {
            return 'missing';
        }
        $revision = hash_file('sha256', $this->cacheFile);
        return is_string($revision) && $revision !== '' ? $revision : 'unreadable';
    }

    private function downloadXml(): string
    {
        $url = (string)($this->config['menu_url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('TL1 menu URL is not configured.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "User-Agent: Hugin TL1 Menu Plugin\r\nAccept: application/xml,text/xml,*/*\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $xml = @file_get_contents($url, false, $context);
        if (!is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('Could not download TL1 menu XML from ' . $url);
        }
        return $xml;
    }

    private function writeValidatedCache(string $xml): void
    {
        $directory = dirname($this->cacheFile);
        $temporary = tempnam($directory, '.speiseplan-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create temporary XML cache file in: ' . $directory);
        }

        try {
            if (file_put_contents($temporary, $xml, LOCK_EX) === false) {
                throw new RuntimeException('Could not write XML cache file: ' . $temporary);
            }
            $this->parser->parseFile($temporary);
            @chmod($temporary, 0664);
            if (!rename($temporary, $this->cacheFile)) {
                throw new RuntimeException('Could not replace XML cache file: ' . $this->cacheFile);
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** @return resource */
    private function acquireRefreshLock()
    {
        $lockFile = $this->cacheFile . '.lock';
        $lock = fopen($lockFile, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Could not lock XML cache file: ' . $this->cacheFile);
        }
        return $lock;
    }

    private function resolveCacheFile(): string
    {
        $root = (string)(app_config('paths.root', dirname(__DIR__, 4)));
        return rtrim($root, '/') . '/storage/cache/plugins/tl1-menu/speiseplan.xml';
    }

    private function ensureCacheDirectoryExists(): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create cache directory: ' . $dir);
        }
    }

    private function isCacheFresh(): bool
    {
        return is_file($this->cacheFile) && (time() - (int)filemtime($this->cacheFile)) < $this->cacheTtl;
    }
}
