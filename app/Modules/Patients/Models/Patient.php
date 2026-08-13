<?php

namespace App\Modules\Patients\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Tutors\Models\Tutor;
use App\Modules\Vaccinations\Models\Vaccination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'patients';

    protected $guarded = [];

    protected $casts = [
        'birth_date' => 'date',
        'weight' => 'decimal:2',
    ];

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function animalSpecies(): BelongsTo
    {
        return $this->belongsTo(AnimalSpecies::class, 'animal_species_id');
    }

    public function animalBreed(): BelongsTo
    {
        return $this->belongsTo(AnimalBreed::class, 'animal_breed_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }
}
