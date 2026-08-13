<?php

namespace App\Modules\Patients\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimalSpecies extends Model
{
    protected $table = 'animal_species';

    protected $guarded = [];

    protected $casts = [
        'system' => 'boolean',
        'active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function breeds(): HasMany
    {
        return $this->hasMany(AnimalBreed::class)->orderBy('name');
    }

    public function coats(): HasMany
    {
        return $this->hasMany(AnimalCoat::class)->orderBy('name');
    }

    public function preferredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_animal_species')->withTimestamps();
    }
}
