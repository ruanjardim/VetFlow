<?php

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditTrailService
{
    public const EVENT_LABELS = [
        'access.user.created' => 'Colaborador criado',
        'access.user.updated' => 'Acesso de colaborador atualizado',
        'clinic.branding.updated' => 'Identidade visual atualizada',
    ];

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $event,
        Model $subject,
        array $before,
        array $after,
        ?User $actor = null,
        array $metadata = [],
        ?int $clinicId = null,
        ?string $subjectLabel = null
    ): ?AuditEvent {
        $changes = $this->changedValues($before, $after);

        if ($changes === [] && $metadata === []) {
            return null;
        }

        $actor ??= Auth::user();
        $request = app()->bound('request') ? request() : null;
        $resolvedClinicId = $clinicId
            ?? $subject->getAttribute('clinic_id')
            ?? $actor?->clinic_id;

        return AuditEvent::query()->create([
            'clinic_id' => $resolvedClinicId,
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'subject_label' => $subjectLabel,
            'changes' => $changes ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request
                ? Str::limit((string) $request->userAgent(), 255, '')
                : null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array{event?: string|null, search?: string|null, from?: string|null, to?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return AuditEvent::query()
            ->with(['actor', 'clinic'])
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery
                        ->where('subject_label', 'like', $term)
                        ->orWhereHas('actor', fn ($actorQuery) => $actorQuery
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term))
                        ->orWhereHas('clinic', fn ($clinicQuery) => $clinicQuery
                            ->where('trade_name', 'like', $term)
                            ->orWhere('corporate_name', 'like', $term));
                });
            })
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array<string, string> */
    public static function eventLabels(): array
    {
        return self::EVENT_LABELS;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function changedValues(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($old === $new) {
                continue;
            }

            $changes[$field] = ['before' => $old, 'after' => $new];
        }

        return $changes;
    }
}
