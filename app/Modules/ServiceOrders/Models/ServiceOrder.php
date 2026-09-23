<?php

namespace App\Modules\ServiceOrders\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Sales\Models\Sale;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrder extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    public const STATUS_LABELS = [
        'scheduled' => 'Agendado',
        'confirmed' => 'Confirmado',
        'open' => 'Em espera',
        'in_service' => 'Em atendimento',
        'waiting_pickup' => 'Animal pronto',
        'finished' => 'Finalizada',
        'cancelled' => 'Cancelada',
        'no_show' => 'Não compareceu',
    ];

    /** Status anteriores a chegada do pet (agenda). */
    public const PRE_ARRIVAL_STATUSES = ['scheduled', 'confirmed'];

    /** Status que ocupam o horario do profissional na agenda. */
    public const BLOCKING_STATUSES = ['scheduled', 'confirmed', 'open', 'in_service', 'waiting_pickup'];

    /** Status em que a comanda ainda pode ser removida sem historico. */
    public const DELETABLE_STATUSES = ['scheduled', 'confirmed', 'open'];

    public const BOARD_STATUSES = [
        'scheduled',
        'open',
        'in_service',
        'waiting_pickup',
        'finished',
    ];

    public const BOARD_LABELS = [
        'scheduled' => 'Agendados',
        'open' => 'Em espera',
        'in_service' => 'Em atendimento',
        'waiting_pickup' => 'Animal pronto',
        'finished' => 'Finalizadas',
    ];

    public const DEFAULT_DURATION_MINUTES = 60;

    protected $table = 'service_orders';

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'duration_minutes' => 'integer',
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'closed_at' => 'datetime',
        'services_total' => 'decimal:2',
        'products_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tenantColumn(): string
    {
        return 'clinic_id';
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function effectiveDuration(): int
    {
        return max(5, (int) ($this->duration_minutes ?: self::DEFAULT_DURATION_MINUTES));
    }

    public function scheduledEnd(): ?\Carbon\CarbonInterface
    {
        return $this->scheduled_at?->copy()->addMinutes($this->effectiveDuration());
    }

    /** Agendado/confirmado cujo horario ja passou sem check-in. */
    public function isLate(): bool
    {
        return in_array($this->status, self::PRE_ARRIVAL_STATUSES, true)
            && $this->scheduled_at !== null
            && $this->scheduled_at->lt(now());
    }
}
