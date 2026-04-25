<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Domain;

final class MenuItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $date,
        public readonly ?string $mensaKey,
        public readonly string $mensaName,
        public readonly ?int $locationId,
        public readonly string $locationName,
        public readonly ?int $typeId,
        public readonly ?string $typeName,
        public readonly int $spalte,
        public readonly string $titleDe,
        public readonly string $titleEn,
        public readonly string $descriptionDe,
        public readonly string $descriptionEn,
        /** @var array<string, float|null> */
        public readonly array $prices,
        /** @var array<string, string> */
        public readonly array $allergens,
        /** @var array<string, string> */
        public readonly array $additives,
        /** @var list<string> */
        public readonly array $categories,
        public readonly string $classification,
        /** @var array<string, mixed> */
        public readonly array $environment,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            date: (string)($data['date'] ?? ''),
            mensaKey: isset($data['mensa_key']) ? (string)$data['mensa_key'] : null,
            mensaName: (string)($data['mensa_name'] ?? ''),
            locationId: is_numeric($data['location_id'] ?? null) ? (int)$data['location_id'] : null,
            locationName: (string)($data['location_name'] ?? ''),
            typeId: is_numeric($data['type_id'] ?? null) ? (int)$data['type_id'] : null,
            typeName: isset($data['type_name']) ? (string)$data['type_name'] : null,
            spalte: is_numeric($data['spalte'] ?? null) ? (int)$data['spalte'] : 0,
            titleDe: (string)($data['title_de'] ?? ''),
            titleEn: (string)($data['title_en'] ?? ''),
            descriptionDe: (string)($data['description_de'] ?? ''),
            descriptionEn: (string)($data['description_en'] ?? ''),
            prices: is_array($data['prices'] ?? null) ? $data['prices'] : [],
            allergens: is_array($data['allergens'] ?? null) ? $data['allergens'] : [],
            additives: is_array($data['additives'] ?? null) ? $data['additives'] : [],
            categories: array_values(array_filter(is_array($data['categories'] ?? null) ? $data['categories'] : [], 'is_string')),
            classification: (string)($data['classification'] ?? 'meat'),
            environment: is_array($data['environment'] ?? null) ? $data['environment'] : [],
            raw: is_array($data['raw'] ?? null) ? $data['raw'] : [],
        );
    }

    public function getLocalizedTitle(string $language = 'de'): string
    {
        if ($language === 'en' && trim($this->titleEn) !== '') {
            return $this->titleEn;
        }

        return $this->titleDe !== '' ? $this->titleDe : $this->titleEn;
    }

    public function getLocalizedDescription(string $language = 'de'): string
    {
        if ($language === 'en' && trim($this->descriptionEn) !== '') {
            return $this->descriptionEn;
        }

        return $this->descriptionDe !== '' ? $this->descriptionDe : $this->descriptionEn;
    }

    public function getPrice(string $group): ?float
    {
        $value = $this->prices[$group] ?? null;
        return is_numeric($value) ? (float)$value : null;
    }

    public function hasZeroPrice(): bool
    {
        foreach (['student', 'staff', 'guest'] as $group) {
            $price = $this->getPrice($group);
            if ($price !== null && $price <= 0.0) {
                return true;
            }
        }

        return false;
    }
}
