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
    private array $pseudoAllergenCategoryMap = [
        'STF' => 'streetfood',
        'SHT' => 'sh_teller',
        'KK' => 'kuechenklassiker',
        'YF' => 'your_favorite',
        'AGS' => 'pork_higher_welfare',
        'S' => 'pork',
        'AGF' => 'fish_higher_welfare',
        'G' => 'poultry',
        'AGR' => 'beef_higher_welfare',
        'R' => 'beef',
        'MV' => 'mensa_vital',
        'INTERNATIONAL' => 'international',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->standortNames = $this->normalizeStandortNames($config['standort_namen'] ?? []);
        $this->mensaLocations = $this->normalizeMensaMappings($config['mensen'] ?? []);
        $this->defaultExcludedTypes = array_map('intval', is_array($config['default_exclude'] ?? null) ? $config['default_exclude'] : []);
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
        return [$a->date, $a->locationId ?? 0, $a->spalte, $a->titleDe] <=> [$b->date, $b->locationId ?? 0, $b->spalte, $b->titleDe];
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
            $ids = [];
            foreach (explode(',', (string)$value) as $part) {
                $part = trim($part);
                if ($part !== '' && is_numeric($part)) {
                    $ids[] = (int)$part;
                }
            }
            $normalized[(string)$key] = array_values(array_unique($ids));
        }
        return $normalized;
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
        $haystack = strtolower(implode(' ', $lines) . ' ' . implode(' ', $attributes));
        return [
            'vegan' => str_contains($haystack, 'vegan') || str_contains($haystack, '(ve') || in_array($typeId, [48, 49, 101, 105, 109, 113, 118, 130, 200, 205, 208], true),
            'vegetarian' => str_contains($haystack, 'vegetar') || str_contains($haystack, '(vn') || in_array($typeId, [102, 106, 110, 114, 116, 201, 209], true),
            'fish' => str_contains($haystack, 'fisch') || array_key_exists('Fi', $this->parseZusatzstoffeAndAllergene($attributes['ZSNUMMERN'] ?? '', $attributes['ZSNAMEN'] ?? '')['allergens']),
            'bio' => str_contains($haystack, 'bio'),
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

        $haystack = strtolower(implode(' ', $attributes));
        if ($flags['fish']) {
            $categories[] = 'fish';
        }
        if (preg_match('/(rind|beef|cow)/u', $haystack) === 1) {
            $categories[] = 'beef';
        }
        if (preg_match('/(schwein|pork|pig)/u', $haystack) === 1) {
            $categories[] = 'pork';
        }
        if (preg_match('/(geflügel|gefluegel|huhn|chicken|poultry)/u', $haystack) === 1) {
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

        if ($typeId !== null && in_array($typeId, [48, 49, 101, 105, 109, 113, 118, 130, 200, 205, 208], true)) {
            $categories[] = 'vegan';
        }
        if ($typeId !== null && in_array($typeId, [102, 106, 110, 114, 116, 201, 209], true)) {
            $categories[] = 'vegetarian';
        }

        if (in_array('fish', $categories, true)) {
            $categories[] = 'fish';
        }
        if (count(array_intersect($categories, ['pork_higher_welfare', 'pork', 'poultry', 'beef_higher_welfare', 'beef'])) > 0) {
            $categories[] = 'meat';
        }

        return array_values(array_unique($categories));
    }

    /** @param list<string> $categories */
    private function classifyDish(array $categories): string
    {
        if (in_array('vegan', $categories, true)) {
            return 'vegan';
        }
        if (in_array('vegetarian', $categories, true)) {
            return 'vegetarian';
        }
        if (in_array('fish', $categories, true)) {
            return 'fish';
        }
        return 'meat';
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
        return 'Type ' . $typeId;
    }
}
