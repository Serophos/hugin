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
        foreach ($types as $id => $key) {
            if (is_numeric((string)$id)) {
                $normalized[(int)$id] = (string)$key;
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
        $transKey = 'plugins.tl-1menu.locations.' . $mensaKey;
        if (trans_has($transKey)) {
            return __($transKey);
        }
        return $mensaKey;
    }

    public function getCategoryLabel(string $classification): string
    {
        return __('plugins.tl-1menu.categories.' . $classification, [], ucfirst($classification));
    }

    public function getFoodTypeLabel(int $typeId, ?string $fallbackKey = null): string
    {
        $translationKey = $fallbackKey ?? ($this->getFoodTypes()[$typeId] ?? null);
        if ($translationKey !== null) {
            $trans = 'plugins.tl-1menu.food_types.' . $translationKey;
            if (trans_has($trans)) {
                return __($trans);
            }
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
}
