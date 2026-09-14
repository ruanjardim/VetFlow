<?php

namespace App\Modules\Clinics\Models;

use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Services\LegacySubscriptionProvisioner;
use App\Modules\Saas\Services\SubscriptionFeatureService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'business_type',
        'document_type',
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

        static::created(function (Clinic $clinic): void {
            app(LegacySubscriptionProvisioner::class)->ensure($clinic);
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

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return app(SubscriptionFeatureService::class)->enabled($this->id, $feature);
    }

    public function featureLimit(string $feature): ?int
    {
        return app(SubscriptionFeatureService::class)->limit($this->id, $feature);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    public function documentLabel(): string
    {
        return $this->document_type === 'cpf' ? 'CPF' : 'CNPJ';
    }

    public function formattedDocument(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->cnpj);

        if ($this->document_type === 'cpf' && strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits);
        }

        if ($this->document_type !== 'cpf' && strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits);
        }

        return (string) $this->cnpj;
    }
}
