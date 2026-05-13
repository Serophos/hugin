<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Parser;

use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Plugins\Tl1Menu\Domain\MenuItem;
use RuntimeException;

final class MensaXmlParser
{
    /** @var array<string, mixed> */
    private array $config;
    /** @var array<int, string> */
    private array $standortNames = [];
    /** @var array<string, list<int>> */
    private array $mensaLocations = [];
    /** @var list<int> */
    private array $defaultExcludedTypes = [];
    /** @var array<string, string> */
    private array $pseudoAllergenCategoryMap = [];
    /** @var array<int, list<string>> */
    private array $foodTypeCategoryMap = [];
    /** @var array<int, string> */
    private array $foodTypeNames = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->standortNames = $this->normalizeStandortNames($config['standort_namen'] ?? []);
        $this->mensaLocations = $this->normalizeMensaMappings($config['mensen'] ?? []);
        $this->defaultExcludedTypes = array_map('intval', is_array($config['default_exclude'] ?? null) ? $config['default_exclude'] : []);
        $this->pseudoAllergenCategoryMap = $this->normalizeStringMap($config['pseudo_allergen_category_map'] ?? []);
        $this->foodTypeCategoryMap = $this->normalizeFoodTypeCategoryMap($config['food_types'] ?? [], $config['food_type_categories'] ?? []);
        $this->foodTypeNames = $this->normalizeFoodTypeNames($config['food_types'] ?? []);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<MenuItem>
     */
    public function parseFile(string $xmlFile, array $filters = []): array
    {
        if (!is_file($xmlFile) || !is_readable($xmlFile)) {
            throw new InvalidArgumentException('XML file not found or not readable: ' . $xmlFile);
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->load($xmlFile, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('Could not load XML file: ' . $xmlFile);
        }

        return $this->parseDom($dom, $filters);
    }

    /**
     * @return list<MenuItem>
     */
    public function getMenuForDay(string $xmlFile, string $mensa, DateTimeInterface|string $date, bool $excludeDefaultTypes = true): array
    {
        return $this->parseFile($xmlFile, [
            'mensa' => $mensa,
            'date' => $date,
            'exclude_types' => $excludeDefaultTypes ? $this->defaultExcludedTypes : [],
            'sort' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<MenuItem>
     */
    private function parseDom(DOMDocument $dom, array $filters): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('/DATAPACKET/ROWDATA/ROW');
        if ($nodes === false) {
            throw new RuntimeException('Could not query ROW nodes from XML.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $items = [];

        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            $item = $this->mapRow($node);
            if ($this->matchesFilters($item, $normalizedFilters)) {
                $items[] = $item;
            }
        }

        if ($normalizedFilters['sort'] === true) {
            usort($items, [$this, 'sortItems']);
        }

        return $items;
    }

    private function mapRow(DOMElement $row): MenuItem
    {
        $attributes = [];
        foreach ($row->attributes as $attribute) {
            $attributes[$attribute->nodeName] = trim((string)$attribute->nodeValue);
        }

        $textLinesDe = $this->collectLines($attributes, 'TEXTL');
        $textLinesEn = $this->collectLines($attributes, 'TEXT3L');
        $locationId = $this->toNullableInt($attributes['VERBRAUCHSORT'] ?? ($attributes['ORT'] ?? ''));
        $typeId = $this->toNullableInt($attributes['TYP'] ?? '');
        $parsedDate = $this->parseGermanDate($attributes['DATUM'] ?? '');
        $tokenInfo = $this->parseZusatzstoffeAndAllergene($attributes['ZSNUMMERN'] ?? '', $attributes['ZSNAMEN'] ?? '');
        $flags = $this->detectFlags($textLinesDe, $attributes, $typeId);
        $categories = $this->detectCategories($typeId, $tokenInfo['allergens'], $tokenInfo['pseudo_categories'], $attributes, $flags);
        $classification = $this->classifyDish($categories);

        $prices = [
            'student' => $this->parseGermanDecimal($attributes['STUDIERENDE'] ?? ''),
            'staff' => $this->parseGermanDecimal($attributes['BEDIENSTETE'] ?? ''),
            'guest' => $this->parseGermanDecimal($attributes['GAESTE'] ?? ''),
        ];

        return MenuItem::fromArray([
            'id' => (string)($attributes['DISPO_ID'] ?? ''),
            'date' => $parsedDate?->format('Y-m-d') ?? '',
            'mensa_key' => $this->resolveMensaKey($locationId),
            'mensa_name' => (string)($attributes['MENSA'] ?? ''),
            'location_id' => $locationId,
            'location_name' => $locationId !== null ? ($this->standortNames[$locationId] ?? '') : '',
            'type_id' => $typeId,
            'type_name' => $this->resolveTypeName($typeId),
            'spalte' => $this->toNullableInt($attributes['SPALTESPEISEPLAN'] ?? '') ?? 0,
            'title_de' => $this->buildTitle($textLinesDe),
            'title_en' => $this->buildTitle($textLinesEn),
            'description_de' => $this->buildDescription($textLinesDe),
            'description_en' => $this->buildDescription($textLinesEn),
            'prices' => $prices,
            'allergens' => $tokenInfo['allergens'],
            'additives' => $tokenInfo['additives'],
            'categories' => $categories,
            'classification' => $classification,
            'environment' => [
                'co2_value' => $this->parseGermanDecimal($attributes['EXTINFO_CO2_WERT'] ?? ''),
                'co2_rating' => $this->nullIfEmpty($attributes['EXTINFO_CO2_BEWERTUNG'] ?? ''),
                'co2_saving' => $this->parseGermanDecimal($attributes['EXTINFO_CO2_EINSPARUNG'] ?? ''),
                'water_value' => $this->parseGermanDecimal($attributes['EXTINFO_WASSER_WERT'] ?? ''),
                'water_rating' => $this->nullIfEmpty($attributes['EXTINFO_WASSER_BEWERTUNG'] ?? ''),
                'animal_welfare' => $this->normalizeRating($attributes['EXTINFO_TIERWOHL'] ?? ''),
                'rainforest' => $this->normalizeRating($attributes['EXTINFO_REGENWALD'] ?? ''),
            ],
            'raw' => $attributes,
        ]);
    }

    /** @param array<string, string> $attributes @return list<string> */
    private function collectLines(array $attributes, string $prefix): array
    {
        $lines = [];
        for ($i = 1; $i <= 5; $i++) {
            $value = $this->stripInlineMeta(trim((string)($attributes[$prefix . $i] ?? '')));
            if ($value !== '') {
                $lines[] = $value;
            }
        }
        return $lines;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $locationIds = [];
        if (isset($filters['mensa'])) {
            $locationIds = $this->resolveLocationFilter((string)$filters['mensa']);
        }

        if (isset($filters['location_ids']) && is_array($filters['location_ids'])) {
            foreach ($filters['location_ids'] as $id) {
                if (is_numeric((string)$id)) {
                    $locationIds[] = (int)$id;
                }
            }
        }

        $dates = [];
        if (isset($filters['date'])) {
            $singleDate = $this->normalizeDate($filters['date']);
            if ($singleDate !== null) {
                $dates[] = $singleDate;
            }
        }

        $excludeTypes = [];
        if (isset($filters['exclude_types']) && is_array($filters['exclude_types'])) {
            $excludeTypes = array_values(array_unique(array_map('intval', $filters['exclude_types'])));
        }

        return [
            'location_ids' => array_values(array_unique($locationIds)),
            'dates' => array_values(array_unique($dates)),
            'exclude_types' => $excludeTypes,
            'sort' => !empty($filters['sort']),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function matchesFilters(MenuItem $item, array $filters): bool
    {
        if ($filters['location_ids'] !== [] && !in_array((int)$item->locationId, $filters['location_ids'], true)) {
            return false;
        }

        if ($filters['dates'] !== [] && !in_array($item->date, $filters['dates'], true)) {
            return false;
        }

        if ($item->typeId !== null && in_array($item->typeId, $filters['exclude_types'], true)) {
            return false;
        }

        return true;
    }

    private function sortItems(MenuItem $a, MenuItem $b): int
    {
        $aSpalte = $a->spalte > 0 ? $a->spalte : PHP_INT_MAX;
        $bSpalte = $b->spalte > 0 ? $b->spalte : PHP_INT_MAX;

        return [$a->date, $aSpalte, $a->locationId ?? 0, $a->titleDe] <=> [$b->date, $bSpalte, $b->locationId ?? 0, $b->titleDe];
    }

    /** @return array<int, string> */
    private function normalizeStandortNames(mixed $names): array
    {
        $normalized = [];
        if (!is_array($names)) {
            return $normalized;
        }
        foreach ($names as $id => $name) {
            if (is_numeric((string)$id)) {
                $normalized[(int)$id] = trim((string)$name);
            }
        }
        return $normalized;
    }

    /** @return array<string, list<int>> */
    private function normalizeMensaMappings(mixed $mensen): array
    {
        $normalized = [];
        if (!is_array($mensen)) {
            return $normalized;
        }
        foreach ($mensen as $key => $value) {
            $normalized[(string)$key] = $this->normalizeLocationIds($value);
        }
        return $normalized;
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

    /** @return array<string, string> */
    private function normalizeStringMap(mixed $map): array
    {
        $normalized = [];
        if (!is_array($map)) {
            return $normalized;
        }

        foreach ($map as $key => $value) {
            $normalizedKey = strtoupper(trim((string)$key));
            $normalizedValue = trim((string)$value);
            if ($normalizedKey !== '' && $normalizedValue !== '') {
                $normalized[$normalizedKey] = $normalizedValue;
            }
        }

        return $normalized;
    }

    /** @return array<int, list<string>> */
    private function normalizeFoodTypeCategoryMap(mixed $foodTypes, mixed $foodTypeCategories): array
    {
        $normalized = [];
        if (is_array($foodTypes)) {
            foreach ($foodTypes as $typeId => $typeConfig) {
                if (!is_numeric((string)$typeId) || !is_array($typeConfig)) {
                    continue;
                }
                $categories = $this->normalizeStringList($typeConfig['categories'] ?? []);
                if ($categories !== []) {
                    $normalized[(int)$typeId] = $categories;
                }
            }
        }

        if (is_array($foodTypeCategories)) {
            foreach ($foodTypeCategories as $category => $typeIds) {
                $category = trim((string)$category);
                if ($category === '') {
                    continue;
                }
                foreach ($this->normalizeIntList($typeIds) as $typeId) {
                    $normalized[$typeId][] = $category;
                }
            }
        }

        foreach ($normalized as $typeId => $categories) {
            $normalized[$typeId] = array_values(array_unique($categories));
        }

        return $normalized;
    }

    /** @return array<int, string> */
    private function normalizeFoodTypeNames(mixed $foodTypes): array
    {
        $normalized = [];
        if (!is_array($foodTypes)) {
            return $normalized;
        }

        foreach ($foodTypes as $typeId => $typeConfig) {
            if (!is_numeric((string)$typeId)) {
                continue;
            }

            if (is_array($typeConfig)) {
                foreach (['name', 'label'] as $key) {
                    $name = trim((string)($typeConfig[$key] ?? ''));
                    if ($name !== '') {
                        $normalized[(int)$typeId] = $name;
                        continue 2;
                    }
                }

                $labels = is_array($typeConfig['labels'] ?? null) ? $typeConfig['labels'] : [];
                $defaultLanguage = trim((string)($this->config['default_language'] ?? ''));
                foreach (array_values(array_unique(array_filter([$defaultLanguage, 'de', 'en']))) as $language) {
                    $name = trim((string)($labels[$language] ?? ''));
                    if ($name !== '') {
                        $normalized[(int)$typeId] = $name;
                        continue 2;
                    }
                }

                $key = trim((string)($typeConfig['key'] ?? ''));
                if ($key !== '') {
                    $normalized[(int)$typeId] = $key;
                }
            } elseif (is_string($typeConfig) && trim($typeConfig) !== '') {
                $normalized[(int)$typeId] = trim($typeConfig);
            }
        }

        return $normalized;
    }

    /** @return list<string> */
    private function normalizeStringList(mixed $value): array
    {
        $rawValues = is_array($value) ? $value : explode(',', (string)$value);
        $strings = [];
        foreach ($rawValues as $rawValue) {
            $string = trim((string)$rawValue);
            if ($string !== '') {
                $strings[] = $string;
            }
        }

        return array_values(array_unique($strings));
    }

    /** @return list<int> */
    private function resolveLocationFilter(string $mensa): array
    {
        if (isset($this->mensaLocations[$mensa])) {
            return $this->mensaLocations[$mensa];
        }
        if (is_numeric($mensa)) {
            return [(int)$mensa];
        }
        return [];
    }

    private function resolveMensaKey(?int $locationId): ?string
    {
        if ($locationId === null) {
            return null;
        }
        foreach ($this->mensaLocations as $key => $ids) {
            if (in_array($locationId, $ids, true)) {
                return $key;
            }
        }
        return null;
    }

    private function parseGermanDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('d.m.Y', $value);
        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function normalizeDate(DateTimeInterface|string $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        $date = $this->parseGermanDate($value);
        return $date?->format('Y-m-d');
    }

    private function parseGermanDecimal(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float)$value : null;
    }

    private function toNullableInt(string $value): ?int
    {
        $value = trim($value);
        return $value !== '' && is_numeric($value) ? (int)$value : null;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function normalizeRating(string $value): ?string
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['A', 'B', 'C', 'D', 'E'], true) ? $value : null;
    }

    private function stripInlineMeta(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        do {
            $before = $value;
            $value = preg_replace('/\s*\(([[:alnum:]]{1,20}(?:\s*,\s*[[:alnum:]]{1,20})*)\)\s*$/u', '', $value) ?? $value;
            $value = trim($value);
        } while ($value !== $before);

        return $value;
    }

    /** @return array{allergens: array<string,string>, additives: array<string,string>, pseudo_categories: list<string>, all: array<string,string>} */
    private function parseZusatzstoffeAndAllergene(string $codesRaw, string $namesRaw): array
    {
        $codes = array_map('trim', explode(',', $codesRaw));
        $names = array_map('trim', explode(',', $namesRaw));
        $allergens = [];
        $additives = [];
        $pseudoCategories = [];
        $all = [];
        $max = max(count($codes), count($names));

        for ($i = 0; $i < $max; $i++) {
            $code = $codes[$i] ?? '';
            $name = $names[$i] ?? '';
            if ($code === '' && $name === '') {
                continue;
            }
            if ($name === '') {
                $name = $code;
            }
            $all[$code] = $name;

            $normalizedCode = strtoupper(trim($code));
            if (isset($this->pseudoAllergenCategoryMap[$normalizedCode])) {
                $pseudoCategories[] = $this->pseudoAllergenCategoryMap[$normalizedCode];
                continue;
            }

            if (preg_match('/^\d+[a-z]?$/i', $code) === 1) {
                $additives[$code] = $name;
            } else {
                $allergens[$code] = $name;
            }
        }

        return [
            'allergens' => $allergens,
            'additives' => $additives,
            'pseudo_categories' => array_values(array_unique($pseudoCategories)),
            'all' => $all,
        ];
    }

    /** @param list<string> $lines @param array<string,string> $attributes @return array<string,bool> */
    private function detectFlags(array $lines, array $attributes, ?int $typeId): array
    {
        $typeCategories = $typeId !== null ? ($this->foodTypeCategoryMap[$typeId] ?? []) : [];
        $primaryHaystack = $this->normalizeSearchText(implode(' ', array_filter([
            $lines[0] ?? '',
            $attributes['SPEISE'] ?? '',
        ])));
        $fullHaystack = $this->normalizeSearchText(implode(' ', array_filter([
            implode(' ', $lines),
            $attributes['AUSGABETEXT'] ?? '',
            $attributes['SPEISE'] ?? '',
            $attributes['ZSNAMEN'] ?? '',
        ])));

        return [
            'vegan' => $this->hasVeganMarker($primaryHaystack) || in_array('vegan', $typeCategories, true),
            'vegetarian' => $this->hasVegetarianMarker($primaryHaystack) || in_array('vegetarian', $typeCategories, true),
            'fish' => preg_match('/\b(fisch|fish)\b/u', $fullHaystack) === 1 || array_key_exists('Fi', $this->parseZusatzstoffeAndAllergene($attributes['ZSNUMMERN'] ?? '', $attributes['ZSNAMEN'] ?? '')['allergens']),
            'bio' => preg_match('/\bbio\b/u', $fullHaystack) === 1,
        ];
    }

    /** @param array<string,string> $allergens @param list<string> $pseudoCategories @param array<string,string> $attributes @param array<string,bool> $flags @return list<string> */
    private function detectCategories(?int $typeId, array $allergens, array $pseudoCategories, array $attributes, array $flags): array
    {
        $categories = [];

        if ($flags['vegan']) {
            $categories[] = 'vegan';
        } elseif ($flags['vegetarian']) {
            $categories[] = 'vegetarian';
        }

        $haystack = $this->normalizeSearchText(implode(' ', $attributes));
        if ($flags['fish']) {
            $categories[] = 'fish';
        }
        if (preg_match('/\b(rind|beef|cow)\b/u', $haystack) === 1) {
            $categories[] = 'beef';
        }
        if (preg_match('/\b(schwein|schweinefleisch|pork|pig)\b/u', $haystack) === 1) {
            $categories[] = 'pork';
        }
        if (preg_match('/\b(geflügel|gefluegel|huhn|chicken|poultry)\b/u', $haystack) === 1) {
            $categories[] = 'poultry';
        }
        if (isset($allergens['Fi'])) {
            $categories[] = 'fish';
        }
        if (!empty($flags['bio'])) {
            $categories[] = 'bio';
        }

        foreach ($pseudoCategories as $category) {
            $categories[] = $category;
            if ($category === 'fish_higher_welfare') {
                $categories[] = 'fish';
            }
            if (in_array($category, ['pork_higher_welfare', 'pork', 'poultry', 'beef_higher_welfare', 'beef'], true)) {
                $categories[] = 'meat';
            }
        }

        if ($typeId !== null) {
            foreach ($this->foodTypeCategoryMap[$typeId] ?? [] as $category) {
                $categories[] = $category;
            }
        }

        if (in_array('fish', $categories, true)) {
            $categories[] = 'fish';
        }
        if (count(array_intersect($categories, ['pork_higher_welfare', 'pork', 'poultry', 'beef_higher_welfare', 'beef'])) > 0) {
            $categories[] = 'meat';
        }

        return $this->normalizeDetectedCategories($categories);
    }

    /** @param list<string> $categories */
    private function classifyDish(array $categories): string
    {
        if (count(array_intersect($categories, ['fish_higher_welfare', 'fish'])) > 0) {
            return 'fish';
        }
        if (count(array_intersect($categories, ['meat', 'pork_higher_welfare', 'pork', 'poultry', 'beef_higher_welfare', 'beef'])) > 0) {
            return 'meat';
        }
        if (in_array('vegan', $categories, true)) {
            return 'vegan';
        }
        if (in_array('vegetarian', $categories, true)) {
            return 'vegetarian';
        }
        return 'meat';
    }

    private function normalizeSearchText(string $value): string
    {
        return strtolower(str_replace("\xc2\xa0", ' ', $value));
    }

    private function hasVeganMarker(string $haystack): bool
    {
        return preg_match('/\bvegan(?:e[rs]?|en)?\b/u', $haystack) === 1
            || preg_match('/\((vn|vegan)\b/u', $haystack) === 1;
    }

    private function hasVegetarianMarker(string $haystack): bool
    {
        return preg_match('/\bvegetar(?:isch(?:e[rs]?|en)?|ian)?\b/u', $haystack) === 1
            || preg_match('/\((ve|vegetarian|vegetarisch)\b/u', $haystack) === 1;
    }

    /** @param list<string> $categories @return list<string> */
    private function normalizeDetectedCategories(array $categories): array
    {
        $categories = array_values(array_unique($categories));
        $animalCategories = ['fish_higher_welfare', 'fish', 'meat', 'pork_higher_welfare', 'pork', 'poultry', 'beef_higher_welfare', 'beef'];

        if (count(array_intersect($categories, $animalCategories)) > 0) {
            return array_values(array_filter($categories, static fn (string $category): bool => !in_array($category, ['vegan', 'vegetarian'], true)));
        }

        if (in_array('vegan', $categories, true)) {
            return array_values(array_filter($categories, static fn (string $category): bool => $category !== 'vegetarian'));
        }

        return $categories;
    }

    /** @param list<string> $lines */
    private function buildTitle(array $lines): string
    {
        return trim((string)($lines[0] ?? ''));
    }

    /** @param list<string> $lines */
    private function buildDescription(array $lines): string
    {
        $parts = $lines;
        array_shift($parts);
        return trim(implode(' ', $parts));
    }

    private function resolveTypeName(?int $typeId): ?string
    {
        if ($typeId === null) {
            return null;
        }
        return $this->foodTypeNames[$typeId] ?? 'Type ' . $typeId;
    }
}
