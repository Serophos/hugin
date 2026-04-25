<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Infrastructure;

use DateTimeInterface;
use Plugins\Tl1Menu\Domain\MenuItem;
use Plugins\Tl1Menu\Parser\MensaXmlParser;
use RuntimeException;

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

        $xml = $this->downloadXml();
        if (file_put_contents($this->cacheFile, $xml) === false) {
            throw new RuntimeException('Could not write XML cache file: ' . $this->cacheFile);
        }

        return $this->cacheFile;
    }

    private function downloadXml(): string
    {
        $url = (string)($this->config['menu_url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('TL-1 menu URL is not configured.');
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
            if (is_file($this->cacheFile)) {
                return (string)file_get_contents($this->cacheFile);
            }
            throw new RuntimeException('Could not download TL-1 menu XML from ' . $url);
        }

        return $xml;
    }

    private function resolveCacheFile(): string
    {
        $root = (string)(app_config('paths.root', dirname(__DIR__, 4)));
        return rtrim($root, '/') . '/storage/cache/plugins/tl-1menu/speiseplan.xml';
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
