<?php

namespace App\Modules\Tutors\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tutor extends Model
{
    use BelongsToClinicTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'name',
        'cpf',
        'rg',
        'birth_date',
        'gender',
        'phone',
        'phone_secondary',
        'email',
        'zip_code',
        'state',
        'city',
        'district',
        'street',
        'number',
        'complement',
        'notes',
        'active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'active' => 'boolean',
    ];

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
