<?php

namespace App\Modules\Clinics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Clinic extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clinics';

    protected $fillable = [
        'ulid',
        'parent_clinic_id',
        'corporate_name',
        'trade_name',
        'cnpj',
        'crmv',
        'technical_manager',
        'email',
        'phone',
        'whatsapp',
        'website',
        'zip_code',
        'state',
        'city',
        'district',
        'street',
        'number',
        'complement',
        'logo',
        'brand_icon_mode',
        'brand_icon_key',
        'timezone',
        'currency',
        'language',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Clinic $clinic) {
            if (empty($clinic->ulid)) {
                $clinic->ulid = (string) Str::ulid();
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_clinic_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_clinic_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }
}
