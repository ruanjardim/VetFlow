<?php

namespace App\Modules\Patients\Models;

use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalCoat extends Model
{
    protected $table = 'animal_coats';

    protected $guarded = [];

    protected $casts = [
        'system' => 'boolean',
        'active' => 'boolean',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(AnimalSpecies::class, 'animal_species_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
