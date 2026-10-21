<?php

namespace App\Modules\Financial\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use BelongsToClinicTenant;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function statementImports(): HasMany
    {
        return $this->hasMany(FinancialStatementImport::class);
    }

    public function statementEntries(): HasMany
    {
        return $this->hasMany(FinancialStatementEntry::class);
    }
}
