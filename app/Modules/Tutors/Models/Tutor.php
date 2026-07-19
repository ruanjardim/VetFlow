<?php

namespace App\Modules\Tutors\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
