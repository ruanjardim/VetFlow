<?php

namespace App\Modules\Vaccinations\Models;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\AnimalSpecies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimalVaccine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'recommended_doses' => 'integer',
        'recommended_interval_days' => 'integer',
        'system' => 'boolean',
        'active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function species(): BelongsToMany
    {
        return $this->belongsToMany(AnimalSpecies::class, 'animal_vaccine_species');
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function protocolLabel(): string
    {
        if ($this->recommended_doses && $this->recommended_interval_days) {
            return "{$this->recommended_doses} doses · intervalo de {$this->recommended_interval_days} dias";
        }

        if ($this->recommended_doses) {
            return "{$this->recommended_doses} doses";
        }

        if ($this->recommended_interval_days) {
            return "Intervalo de {$this->recommended_interval_days} dias";
        }

        return 'A definir pela clínica';
    }
}
