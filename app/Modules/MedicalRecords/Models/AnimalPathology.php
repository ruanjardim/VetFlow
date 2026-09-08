<?php

namespace App\Modules\MedicalRecords\Models;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\AnimalSpecies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnimalPathology extends Model
{
    protected $guarded = [];

    protected $casts = [
        'system' => 'boolean',
        'active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function species(): BelongsToMany
    {
        return $this->belongsToMany(
            AnimalSpecies::class,
            'animal_pathology_species',
            'animal_pathology_id',
            'animal_species_id'
        );
    }

    public function medicalRecords(): BelongsToMany
    {
        return $this->belongsToMany(
            MedicalRecord::class,
            'medical_record_pathology',
            'animal_pathology_id',
            'medical_record_id'
        )->withTimestamps();
    }
}
