<?php

namespace App\Modules\Products\Contracts;

use App\Modules\Products\Data\ProductLookupResult;

interface ProductLookupProviderInterface
{
    public function lookup(string $gtin): ?ProductLookupResult;
}
