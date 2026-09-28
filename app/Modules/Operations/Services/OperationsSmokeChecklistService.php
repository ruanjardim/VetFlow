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

    /** @var array<string, array{label: string, description: string}> */
    private const GROOMING_CHECKS = [
        'grooming_schedule' => [
            'label' => 'Agenda e disponibilidade',
            'description' => 'Expediente, intervalo, bloqueio e conflito foram validados para a clínica e o profissional.',
        ],
        'grooming_booking' => [
            'label' => 'Agendamento e preço',
            'description' => 'Agendamento, duração, porte do pet, serviço e preço ficaram consistentes.',
        ],
        'grooming_lifecycle' => [
            'label' => 'Fluxo do atendimento',
            'description' => 'Chegada, início, animal pronto, falta e cancelamento respeitaram os estados operacionais.',
        ],
        'grooming_package' => [
            'label' => 'Pacote e saldo',
            'description' => 'Venda, ativação, consumo e liberação de sessão do pacote foram conferidos.',
        ],
        'grooming_checkout' => [
            'label' => 'PDV e recebimento',
            'description' => 'A comanda chegou ao PDV com cliente, pet, itens, preços e pagamento corretos.',
        ],
        'grooming_commission_return' => [
            'label' => 'Comissão e devolução',
            'description' => 'Comissão, fechamento, devolução parcial e estorno integral foram reconciliados.',
        ],
        'grooming_financial' => [
            'label' => 'Caixa e financeiro',
            'description' => 'Venda, estorno, caixa e conta a pagar de comissão fecharam sem divergência.',
        ],
        'grooming_audit' => [
            'label' => 'Histórico e segregação',
            'description' => 'Históricos, permissões, logs e isolamento entre clínicas foram revisados.',
        ],
    ];

    public function __construct(private readonly ReleaseIdentityService $releaseIdentity) {}

    public static function label(string $checkKey): string
    {
        return self::CHECKS[$checkKey]['label']
            ?? self::GROOMING_CHECKS[$checkKey]['label']
            ?? 'Item operacional';
    }

    /**
     * @return array{available: bool, completed: int, total: int, items: array<int, array<string, mixed>>}
     */
    public function summary(User $user): array
    {
        return $this->summaryFor($user, self::CHECKS);
    }

    /**
     * @return array{available: bool, completed: int, total: int, items: array<int, array<string, mixed>>}
     */
    public function groomingSummary(User $user): array
    {
        return $this->summaryFor($user, self::GROOMING_CHECKS);
    }

    public function record(User $user, string $checkKey, bool $completed, ?string $note): OperationsSmokeCheck
    {
        return $this->recordFor($user, self::CHECKS, $checkKey, $completed, $note);
    }

    public function recordGrooming(User $user, string $checkKey, bool $completed, ?string $note): OperationsSmokeCheck
    {
        return $this->recordFor($user, self::GROOMING_CHECKS, $checkKey, $completed, $note);
    }

    /**
     * @param  array<string, array{label: string, description: string}>  $checks
     * @return array{available: bool, completed: int, total: int, items: array<int, array<string, mixed>>}
     */
    private function summaryFor(User $user, array $checks): array
    {
        $sha = $this->releaseIdentity->sha();

        if ($sha === null) {
            return [
                'available' => false,
                'completed' => 0,
                'total' => count($checks),
                'items' => $this->emptyItems($checks),
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

        $items = collect($checks)->map(function (array $definition, string $key) use ($latest): array {
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
            'total' => count($checks),
            'items' => $items,
        ];
    }

    /** @param array<string, array{label: string, description: string}> $checks */
    private function recordFor(
        User $user,
        array $checks,
        string $checkKey,
        bool $completed,
        ?string $note,
    ): OperationsSmokeCheck {
        if (! array_key_exists($checkKey, $checks)) {
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

    /**
     * @param  array<string, array{label: string, description: string}>  $checks
     * @return array<int, array<string, mixed>>
     */
    private function emptyItems(array $checks): array
    {
        return collect($checks)->map(fn (array $definition, string $key): array => [
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
