<?php

namespace App\Modules\Patients\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Hospitalizations\Models\Hospitalization;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Prescriptions\Models\Prescription;
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

    public function animalCoat(): BelongsTo
    {
        return $this->belongsTo(AnimalCoat::class, 'animal_coat_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function hospitalizations(): HasMany
    {
        return $this->hasMany(Hospitalization::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function clinicalAlerts(): HasMany
    {
        return $this->hasMany(PatientClinicalAlert::class)->latest();
    }

    public function activeClinicalAlerts(): HasMany
    {
        return $this->hasMany(PatientClinicalAlert::class)
            ->where('status', 'active')
            ->latest();
    }
}
