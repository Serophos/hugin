<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Application;

use Plugins\Tl1Menu\Domain\MenuItem;
use Plugins\Tl1Menu\Infrastructure\MenuRepository;

final class MenuService
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly MenuRepository $repository, private readonly array $config)
    {
    }

    /** @return list<MenuItem> */
    public function getMenuForToday(string $mensa, bool $refresh = false): array
    {
        return $this->getMenuForDate($mensa, date('Y-m-d'), $this->getDefaultExcludedTypeIds(), $refresh);
    }

    /** @return list<MenuItem> */
    public function getMenuForDate(string $mensa, string $date, array $excludeTypes = [], bool $refresh = false): array
    {
        return $this->repository->findByFilters([
            'mensa' => $mensa,
            'date' => $date,
            'exclude_types' => array_values(array_unique(array_map('intval', $excludeTypes))),
            'sort' => true,
        ], $refresh);
    }

    /** @return list<string> */
    public function getAvailableMensen(): array
    {
        $mensen = is_array($this->config['mensen'] ?? null) ? $this->config['mensen'] : [];
        $keys = array_keys($mensen);
        sort($keys);
        return $keys;
    }

    /** @return array<int, string> */
    public function getFoodTypes(): array
    {
        $types = is_array($this->config['food_types'] ?? null) ? $this->config['food_types'] : [];
        $normalized = [];
        foreach ($types as $id => $typeConfig) {
            if (is_numeric((string)$id)) {
                $normalized[(int)$id] = $this->foodTypeKey($typeConfig, (int)$id);
            }
        }
        ksort($normalized);
        return $normalized;
    }

    /** @return list<int> */
    public function getDefaultExcludedTypeIds(): array
    {
        return array_values(array_map('intval', is_array($this->config['default_exclude'] ?? null) ? $this->config['default_exclude'] : []));
    }

    public function getMensaLabel(string $mensaKey): string
    {
        $mensen = is_array($this->config['mensen'] ?? null) ? $this->config['mensen'] : [];
        $mensaConfig = $mensen[$mensaKey] ?? null;

        if (is_array($mensaConfig)) {
            foreach (['label', 'name'] as $key) {
                $label = trim((string)($mensaConfig[$key] ?? ''));
                if ($label !== '') {
                    return $label;
                }
            }
        }

        $locationIds = $this->normalizeLocationIds($mensaConfig);
        if (count($locationIds) === 1) {
            $standortNames = is_array($this->config['standort_namen'] ?? null) ? $this->config['standort_namen'] : [];
            $label = trim((string)($standortNames[$locationIds[0]] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return $mensaKey;
    }

    public function getCategoryLabel(string $classification, ?string $language = null): string
    {
        $category = $this->getCategoryConfig($classification);
        $label = $this->localizedConfigLabel($category, $language);

        return $label ?? $this->humanizeKey($classification);
    }

    /** @return array{icon: string, label: string}|null */
    public function getCategoryDisplayData(string $classification, ?string $language = null): ?array
    {
        $category = $this->getCategoryConfig($classification);
        if ($category === null) {
            return null;
        }

        $icon = is_array($category) ? trim((string)($category['icon'] ?? '')) : '';

        return [
            'icon' => $icon !== '' ? $icon : '🏷',
            'label' => $this->getCategoryLabel($classification, $language),
        ];
    }

    public function getFoodTypeLabel(int $typeId, ?string $fallbackKey = null, ?string $language = null): string
    {
        $foodTypes = is_array($this->config['food_types'] ?? null) ? $this->config['food_types'] : [];
        $typeConfig = $foodTypes[$typeId] ?? null;
        $label = $this->localizedConfigLabel($typeConfig, $language);
        if ($label !== null) {
            return $label;
        }

        $translationKey = $fallbackKey ?? ($this->getFoodTypes()[$typeId] ?? null);
        if ($translationKey !== null && trim($translationKey) !== '') {
            return $this->humanizeKey($translationKey);
        }

        return (string)$typeId;
    }

    public function formatPrice(?float $price, string $language): string
    {
        if ($price === null || $price <= 0.0) {
            return __('plugins.tl-1menu.frontend.price_not_available');
        }
        $locale = $language === 'en' ? 'en_US' : 'de_DE';
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $value = $formatter->formatCurrency($price, 'EUR');
            if (is_string($value)) {
                return $value;
            }
        }
        $decimal = $language === 'en' ? '.' : ',';
        $thousand = $language === 'en' ? ',' : '.';
        return number_format($price, 2, $decimal, $thousand) . ' €';
    }

    public function formatDate(string $date, string $language): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        $format = $language === 'en' ? 'l, F j, Y' : 'l, d.m.Y';
        return date($format, $timestamp);
    }

    public function formatEnvironmentalValue(?float $value, string $kind, string $language): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($kind === 'co2') {
            $unit = __('plugins.tl-1menu.frontend.units.co2');
            return number_format($value, 0, $language === 'en' ? '.' : ',', $language === 'en' ? ',' : '.') . ' ' . $unit;
        }

        if ($kind === 'water') {
            $unit = __('plugins.tl-1menu.frontend.units.water');
            return number_format($value, 2, $language === 'en' ? '.' : ',', $language === 'en' ? ',' : '.') . ' ' . $unit;
        }

        return (string)$value;
    }

    public function getEnvironmentGradeLabel(?string $grade): string
    {
        return $grade ?: '–';
    }

    /** @return list<int> */
    private function normalizeLocationIds(mixed $mensaConfig): array
    {
        if (is_array($mensaConfig)) {
            foreach (['locations', 'location_ids', 'standorte', 'ids', 'id', 'location_id'] as $key) {
                if (array_key_exists($key, $mensaConfig)) {
                    return $this->normalizeIntList($mensaConfig[$key]);
                }
            }
            return [];
        }

        return $this->normalizeIntList($mensaConfig);
    }

    /** @return list<int> */
    private function normalizeIntList(mixed $value): array
    {
        $rawValues = is_array($value) ? $value : explode(',', (string)$value);
        $ids = [];
        foreach ($rawValues as $rawValue) {
            $rawValue = trim((string)$rawValue);
            if ($rawValue !== '' && is_numeric($rawValue)) {
                $ids[] = (int)$rawValue;
            }
        }

        return array_values(array_unique($ids));
    }

    private function getCategoryConfig(string $classification): mixed
    {
        $categories = is_array($this->config['categories'] ?? null) ? $this->config['categories'] : [];
        if (array_key_exists($classification, $categories)) {
            return $categories[$classification];
        }

        $legacyDisplay = is_array($this->config['category_display'] ?? null) ? $this->config['category_display'] : [];
        if (array_key_exists($classification, $legacyDisplay)) {
            return $legacyDisplay[$classification];
        }

        return null;
    }

    private function localizedConfigLabel(mixed $config, ?string $language = null): ?string
    {
        if (is_string($config)) {
            $label = trim($config);
            return $label !== '' ? $label : null;
        }

        if (!is_array($config)) {
            return null;
        }

        $localeCandidates = [];
        foreach (array_filter([
            $language !== null ? trim($language) : null,
            current_locale(),
            trim((string)($this->config['default_language'] ?? '')),
            'de',
            'en',
        ]) as $locale) {
            $localeCandidates[] = $locale;
            $baseLocale = preg_split('/[_-]/', $locale)[0] ?? '';
            if ($baseLocale !== '') {
                $localeCandidates[] = $baseLocale;
            }
        }
        $localeCandidates = array_values(array_unique($localeCandidates));

        $labels = is_array($config['labels'] ?? null) ? $config['labels'] : [];
        foreach ($localeCandidates as $locale) {
            $label = trim((string)($labels[$locale] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        foreach (['label', 'name', 'title', 'key'] as $key) {
            $label = trim((string)($config[$key] ?? ''));
            if ($label !== '') {
                return $key === 'key' ? $this->humanizeKey($label) : $label;
            }
        }

        return null;
    }

    private function humanizeKey(string $key): string
    {
        $label = trim(str_replace(['_', '-'], ' ', $key));
        return $label !== '' ? ucwords($label) : $key;
    }

    private function foodTypeKey(mixed $typeConfig, int $typeId): string
    {
        if (is_array($typeConfig)) {
            $key = trim((string)($typeConfig['key'] ?? ''));
            if ($key !== '') {
                return $key;
            }
        }

        if (is_string($typeConfig) && trim($typeConfig) !== '') {
            return trim($typeConfig);
        }

        return (string)$typeId;
    }
}
