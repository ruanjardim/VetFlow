<?php

namespace App\Modules\Products\Data;

class ProductLookupOutcome
{
    public const FOUND = 'found';

    public const NOT_FOUND = 'not_found';

    public const UNAVAILABLE = 'unavailable';

    public const DISABLED = 'disabled';

    public const INVALID = 'invalid';

    public function __construct(
        public readonly string $status,
        public readonly ?ProductLookupResult $result = null,
        public readonly array $diagnostics = [],
        public readonly bool $cached = false
    ) {}

    public function found(): bool
    {
        return $this->status === self::FOUND && (bool) $this->result?->hasUsefulData();
    }

    public function unavailable(): bool
    {
        return $this->status === self::UNAVAILABLE;
    }
}
