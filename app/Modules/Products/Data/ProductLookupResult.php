<?php

namespace App\Modules\Products\Data;

use App\Modules\Products\Models\ProductLookupCatalog;

class ProductLookupResult
{
    public function __construct(
        public readonly string $gtin,
        public readonly ?string $name = null,
        public readonly ?string $brand = null,
        public readonly ?string $category = null,
        public readonly ?string $description = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $unit = null,
        public readonly ?string $weight = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $imagePath = null,
        public readonly ?string $source = null,
        public readonly array $metadata = [],
        public readonly array $sourcePayload = []
    ) {
    }

    public static function fromCatalog(ProductLookupCatalog $catalog): self
    {
        return new self(
            gtin: $catalog->gtin,
            name: $catalog->name,
            brand: $catalog->brand,
            category: $catalog->category,
            description: $catalog->description,
            manufacturer: $catalog->manufacturer,
            unit: $catalog->unit,
            weight: $catalog->weight,
            imageUrl: $catalog->image_url,
            imagePath: $catalog->image_path,
            source: $catalog->source,
            metadata: $catalog->metadata ?? [],
            sourcePayload: $catalog->source_payload ?? []
        );
    }

    public function withImagePath(?string $imagePath): self
    {
        return new self(
            gtin: $this->gtin,
            name: $this->name,
            brand: $this->brand,
            category: $this->category,
            description: $this->description,
            manufacturer: $this->manufacturer,
            unit: $this->unit,
            weight: $this->weight,
            imageUrl: $this->imageUrl,
            imagePath: $imagePath,
            source: $this->source,
            metadata: $this->metadata,
            sourcePayload: $this->sourcePayload
        );
    }

    public function hasUsefulData(): bool
    {
        return (bool) ($this->name || $this->brand || $this->category || $this->description || $this->imageUrl || $this->imagePath);
    }

    public function toProductAttributes(): array
    {
        return [
            'gtin' => $this->gtin,
            'barcode' => $this->gtin,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'description' => $this->description,
            'manufacturer' => $this->manufacturer,
            'unit' => $this->unit ?: 'un',
            'weight' => $this->weight,
            'image_path' => $this->imagePath,
            'lookup_source' => $this->source,
            'lookup_metadata' => $this->metadata,
            'looked_up_at' => now()->toDateTimeString(),
        ];
    }

    public function toCatalogAttributes(string $status = 'found'): array
    {
        return [
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'description' => $this->description,
            'manufacturer' => $this->manufacturer,
            'unit' => $this->unit,
            'weight' => $this->weight,
            'image_path' => $this->imagePath,
            'image_url' => $this->imageUrl,
            'source' => $this->source,
            'lookup_status' => $status,
            'metadata' => $this->metadata,
            'source_payload' => $this->sourcePayload,
            'last_lookup_at' => now(),
            'failed_at' => $status === 'found' ? null : now(),
        ];
    }
}
