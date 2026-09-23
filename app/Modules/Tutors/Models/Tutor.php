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

    /**
     * Single-line address used to prefill deliveries, e.g.
     * "Rua A, 10 - Casa 2 - Centro - Niterói/RJ - CEP 24000-000".
     */
    public function fullAddress(): ?string
    {
        $street = trim(implode(', ', array_filter([
            trim((string) $this->street),
            trim((string) $this->number),
        ], fn (string $part) => $part !== '')));
        $cityState = trim(implode('/', array_filter([
            trim((string) $this->city),
            trim((string) $this->state),
        ], fn (string $part) => $part !== '')));
        $zip = trim((string) $this->zip_code);

        $parts = array_filter([
            $street,
            trim((string) $this->complement),
            trim((string) $this->district),
            $cityState,
            $zip !== '' ? 'CEP '.$zip : '',
        ], fn (string $part) => $part !== '');

        return $parts === [] ? null : implode(' - ', $parts);
    }

    /**
     * Digits for a wa.me link, preferring the secondary (WhatsApp) number.
     */
    public function whatsappDigits(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($this->phone_secondary ?: $this->phone));

        if ($digits === '') {
            return null;
        }

        return strlen($digits) === 10 || strlen($digits) === 11 ? '55'.$digits : $digits;
    }
}
