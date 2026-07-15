<?php

namespace App\Modules\PurchaseEntries\Exceptions;

use RuntimeException;

class NfeAccessKeyLookupException extends RuntimeException
{
    public function __construct(string $message, private readonly array $diagnostics = [])
    {
        parent::__construct($message);
    }

    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
