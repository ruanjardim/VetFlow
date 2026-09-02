<?php

namespace App\Modules\MedicalRecords\Models;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\AnimalSpecies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimalExam extends Model
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
        return $this->belongsToMany(AnimalSpecies::class, 'animal_exam_species');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MedicalRecordExam::class);
    }
}
