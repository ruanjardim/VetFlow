<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Operations\Models\OperationsSmokeCheck;
use App\Support\Operations\ReleaseIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationsSmokeChecklistService
{
    /** @var array<string, array{label: string, description: string}> */
    private const CHECKS = [
        'health_endpoint' => [
            'label' => 'Saúde da aplicação',
            'description' => 'O endpoint de saúde respondeu no ambiente publicado.',
        ],
        'release_identity' => [
            'label' => 'Identidade da release',
            'description' => 'O SHA publicado corresponde ao commit que está sendo validado.',
        ],
        'tenant_login' => [
            'label' => 'Acesso e clínica esperada',
            'description' => 'Um administrador ativo entrou e confirmou o contexto correto da clínica.',
        ],
        'implementation_scope' => [
            'label' => 'Implantação isolada',
            'description' => 'Cobertura, qualidade, checklist e plano permanecem no escopo da clínica.',
        ],
        'product_lookup' => [
            'label' => 'Consulta de produto',
            'description' => 'Uma consulta de produto funcionou sem exigir provedor pago.',
        ],
        'nfe_preview' => [
            'label' => 'Prévia de NF-e fictícia',
            'description' => 'Uma NF-e fictícia chegou à prévia sem salvar uma entrada indevida.',
        ],
        'stock_entry' => [
            'label' => 'Entrada manual de estoque',
            'description' => 'Uma entrada fictícia criou o movimento de estoque esperado.',
        ],
        'draft_sale' => [
            'label' => 'Rascunho de venda',
            'description' => 'O rascunho não alterou estoque nem financeiro.',
        ],
        'completed_sale' => [
            'label' => 'Venda concluída',
            'description' => 'Estoque, pagamento e lançamento financeiro ficaram consistentes.',
        ],
        'async_queue' => [
            'label' => 'Processamento assíncrono',
            'description' => 'Worker ou cron consumiu um job inofensivo sem falha.',
        ],
        'disposable_asset' => [
            'label' => 'Armazenamento persistente',
            'description' => 'Um arquivo descartável foi enviado e removido no disco configurado.',
        ],
        'logs_review' => [
            'label' => 'Revisão dos logs',
            'description' => 'Não foram encontrados erros inesperados de infraestrutura ou provedores.',
        ],
    ];

    public function __construct(private readonly ReleaseIdentityService $releaseIdentity) {}

    /**
     * @return array{available: bool, completed: int, total: int, items: array<int, array<string, mixed>>}
     */
    public function summary(User $user): array
    {
        $sha = $this->releaseIdentity->sha();

        if ($sha === null) {
            return [
                'available' => false,
                'completed' => 0,
                'total' => count(self::CHECKS),
                'items' => $this->emptyItems(),
            ];
        }

        $latest = OperationsSmokeCheck::query()
            ->with('actor:id,name')
            ->where('environment', app()->environment())
            ->where('release_sha', $sha)
            ->where(function ($query) use ($user): void {
                $user->clinic_id === null
                    ? $query->whereNull('clinic_id')
                    : $query->where('clinic_id', $user->clinic_id);
            })
            ->latest('id')
            ->get()
            ->unique('check_key')
            ->keyBy('check_key');

        $items = collect(self::CHECKS)->map(function (array $definition, string $key) use ($latest): array {
            $decision = $latest->get($key);

            return [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'completed' => (bool) ($decision?->completed ?? false),
                'note' => $decision?->note,
                'actor' => $decision?->actor?->name,
                'decided_at' => $decision?->created_at,
            ];
        })->values()->all();

        return [
            'available' => true,
            'completed' => collect($items)->where('completed', true)->count(),
            'total' => count(self::CHECKS),
            'items' => $items,
        ];
    }

    public function record(User $user, string $checkKey, bool $completed, ?string $note): OperationsSmokeCheck
    {
        if (! array_key_exists($checkKey, self::CHECKS)) {
            throw ValidationException::withMessages(['check' => 'O item de smoke test informado não existe.']);
        }

        $sha = $this->releaseIdentity->sha();

        if ($sha === null) {
            throw ValidationException::withMessages([
                'release' => 'Identifique o commit publicado antes de registrar o smoke test.',
            ]);
        }

        return DB::transaction(fn (): OperationsSmokeCheck => OperationsSmokeCheck::query()->create([
            'clinic_id' => $user->clinic_id,
            'actor_user_id' => $user->id,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'check_key' => $checkKey,
            'completed' => $completed,
            'note' => filled($note) ? trim((string) $note) : null,
        ]));
    }

    /** @return array<int, array<string, mixed>> */
    private function emptyItems(): array
    {
        return collect(self::CHECKS)->map(fn (array $definition, string $key): array => [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'completed' => false,
            'note' => null,
            'actor' => null,
            'decided_at' => null,
        ])->values()->all();
    }
}
