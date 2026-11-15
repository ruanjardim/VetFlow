<?php

namespace App\Modules\Saas\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LegalAcceptance extends Model
{
    protected $table = 'saas_legal_acceptances';

    protected $fillable = [
        'acceptance_uuid', 'legal_document_id', 'clinic_id', 'user_id',
        'signer_name', 'signer_email', 'company_document', 'document_type',
        'document_version', 'document_hash', 'plan_id', 'plan_name',
        'billing_cycle', 'base_price_cents', 'coupon_code', 'discount_percentage',
        'final_price_cents', 'promotion_starts_on', 'promotion_ends_on', 'evidence',
        'ip_address', 'user_agent', 'accepted_at',
    ];

    protected $casts = [
        'base_price_cents' => 'integer',
        'discount_percentage' => 'integer',
        'final_price_cents' => 'integer',
        'promotion_starts_on' => 'date',
        'promotion_ends_on' => 'date',
        'evidence' => 'array',
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O aceite jurídico é imutável.'));
        static::deleting(fn () => throw new LogicException('O aceite jurídico é imutável.'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
