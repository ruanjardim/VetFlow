<?php

namespace App\Modules\Tutors\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tutor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
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