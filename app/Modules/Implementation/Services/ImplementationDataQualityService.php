<?php

namespace App\Modules\Implementation\Services;

use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImplementationDataQualityService
{
    /** @var array<string, array{label: string, description: string, permission: string, edit_route: string}> */
    private const BLOCKS = [
        'tutors' => [
            'label' => 'Responsáveis',
            'description' => 'CPF ou e-mail não informado',
            'permission' => 'tutors.manage',
            'edit_route' => 'tutores.edit',
        ],
        'patients' => [
            'label' => 'Pacientes',
            'description' => 'Responsável, espécie ou nascimento não informado',
            'permission' => 'patients.manage',
            'edit_route' => 'patients.edit',
        ],
        'suppliers' => [
            'label' => 'Fornecedores',
            'description' => 'Documento ou canal de contato não informado',
            'permission' => 'suppliers.manage',
            'edit_route' => 'suppliers.edit',
        ],
        'products' => [
            'label' => 'Produtos',
            'description' => 'Preço de venda ou identificador comercial ausente',
            'permission' => 'products.manage',
            'edit_route' => 'products.edit',
        ],
        'stock' => [
            'label' => 'Estoque inicial',
            'description' => 'Saldo positivo sem custo cadastrado',
            'permission' => 'products.manage',
            'edit_route' => 'products.edit',
        ],
        'financial' => [
            'label' => 'Financeiro',
            'description' => 'Valor ou data incompatível com o status',
            'permission' => 'financial.manage',
            'edit_route' => 'financial-transactions.edit',
        ],
    ];

    /** @return array<int, string> */
    public static function types(): array
    {
        return array_keys(self::BLOCKS);
    }

    /**
     * @param  Collection<int, Clinic>  $clinics
     * @param  array<int, array<string, mixed>>  $readiness
     * @return array<int, array<string, mixed>>
     */
    public function forClinics(Collection $clinics, array $readiness): array
    {
        if ($clinics->isEmpty()) {
            return [];
        }

        $clinicIds = $clinics->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $counts = [
            'tutors' => $this->tutorIssues($clinicIds),
            'patients' => $this->patientIssues($clinicIds),
            'suppliers' => $this->supplierIssues($clinicIds),
            'products' => $this->productIssues($clinicIds),
            'stock' => $this->stockIssues($clinicIds),
            'financial' => $this->financialIssues($clinicIds),
        ];
        $readinessByClinic = collect($readiness)->keyBy('clinic_id');

        return $clinics
            ->map(function (Clinic $clinic) use (
                $counts,
                $readinessByClinic
            ): array {
                $clinicReadiness = $readinessByClinic->get($clinic->id, []);
                $coverageBlocks = collect($clinicReadiness['blocks'] ?? [])->keyBy('type');
                $blocks = $coverageBlocks
                    ->map(function (array $coverage, string $type) use (
                        $clinic,
                        $counts
                    ): array {
                        $completed = (bool) ($coverage['completed'] ?? false);
                        $issueCount = $completed
                            ? (int) ($counts[$type]->get($clinic->id, 0))
                            : null;

                        return [
                            'type' => $type,
                            'label' => $coverage['label'],
                            'description' => self::BLOCKS[$type]['description'],
                            'evaluated' => $completed,
                            'issue_count' => $issueCount,
                            'status' => ! $completed
                                ? 'awaiting'
                                : ($issueCount === 0 ? 'ready' : 'attention'),
                        ];
                    })
                    ->values();
                $evaluated = $blocks->where('evaluated', true);
                $ready = $evaluated->where('status', 'ready')->count();
                $totalIssues = $evaluated->sum('issue_count');

                return [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->trade_name,
                    'evaluated_blocks' => $evaluated->count(),
                    'ready_blocks' => $ready,
                    'total_issues' => $totalIssues,
                    'percentage' => $evaluated->isEmpty()
                        ? 0
                        : (int) round(($ready / $evaluated->count()) * 100),
                    'blocks' => $blocks->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   type: string,
     *   label: string,
     *   description: string,
     *   permission: string,
     *   edit_route: string,
     *   issues: LengthAwarePaginator
     * }
     */
    public function issuesForClinic(Clinic $clinic, string $type): array
    {
        if (! isset(self::BLOCKS[$type])) {
            throw new InvalidArgumentException('Bloco de qualidade desconhecido.');
        }

        $issues = $this->issueQuery($type)
            ->where('clinic_id', $clinic->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $issues->through(fn (object $record): array => [
            'id' => (int) $record->id,
            'label' => $this->recordLabel($record, $type),
            'reasons' => $this->recordReasons($record, $type),
        ]);

        return [
            'type' => $type,
            ...self::BLOCKS[$type],
            'issues' => $issues,
        ];
    }

    /** @param  array<int, int>  $clinicIds */
    private function tutorIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            $this->tutorIssueQuery(),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function patientIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            $this->patientIssueQuery(),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function supplierIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            $this->supplierIssueQuery(),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function productIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            $this->productIssueQuery(),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function stockIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            $this->stockIssueQuery(),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function financialIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            $this->financialIssueQuery(),
            $clinicIds
        );
    }

    private function issueQuery(string $type): Builder
    {
        return match ($type) {
            'tutors' => $this->tutorIssueQuery(),
            'patients' => $this->patientIssueQuery(),
            'suppliers' => $this->supplierIssueQuery(),
            'products' => $this->productIssueQuery(),
            'stock' => $this->stockIssueQuery(),
            'financial' => $this->financialIssueQuery(),
        };
    }

    private function tutorIssueQuery(): Builder
    {
        return DB::table('tutors')
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('cpf')
                    ->orWhere('cpf', '')
                    ->orWhereNull('email')
                    ->orWhere('email', '');
            });
    }

    private function patientIssueQuery(): Builder
    {
        return DB::table('patients')
            ->where(function (Builder $query): void {
                $query->whereNull('tutor_id')
                    ->orWhereNull('animal_species_id')
                    ->orWhereNull('birth_date');
            });
    }

    private function supplierIssueQuery(): Builder
    {
        return DB::table('suppliers')
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('document')
                    ->orWhere('document', '')
                    ->orWhere(function (Builder $contacts): void {
                        $contacts
                            ->where(function (Builder $email): void {
                                $email->whereNull('email')->orWhere('email', '');
                            })
                            ->where(function (Builder $phone): void {
                                $phone->whereNull('phone')->orWhere('phone', '');
                            })
                            ->where(function (Builder $whatsapp): void {
                                $whatsapp->whereNull('whatsapp')->orWhere('whatsapp', '');
                            });
                    });
            });
    }

    private function productIssueQuery(): Builder
    {
        return DB::table('products')
            ->where('active', true)
            ->where(function (Builder $query): void {
                $query->where('sale_price', '<=', 0)
                    ->orWhere(function (Builder $identifiers): void {
                        $identifiers
                            ->where(function (Builder $barcode): void {
                                $barcode->whereNull('barcode')->orWhere('barcode', '');
                            })
                            ->where(function (Builder $sku): void {
                                $sku->whereNull('sku')->orWhere('sku', '');
                            });
                    });
            });
    }

    private function stockIssueQuery(): Builder
    {
        return DB::table('products')
            ->where('active', true)
            ->where('stock_quantity', '>', 0)
            ->where('cost_price', '<=', 0);
    }

    private function financialIssueQuery(): Builder
    {
        return DB::table('financial_transactions')
            ->where(function (Builder $query): void {
                $query->where('amount', '<=', 0)
                    ->orWhere(function (Builder $pending): void {
                        $pending->whereIn('status', ['pending', 'overdue'])
                            ->whereNull('due_date');
                    })
                    ->orWhere(function (Builder $paid): void {
                        $paid->where('status', 'paid')->whereNull('paid_at');
                    });
            });
    }

    private function recordLabel(object $record, string $type): string
    {
        $label = $type === 'financial'
            ? ($record->description ?? null)
            : ($record->name ?? null);

        return filled($label) ? (string) $label : "Registro #{$record->id}";
    }

    /** @return array<int, string> */
    private function recordReasons(object $record, string $type): array
    {
        return match ($type) {
            'tutors' => array_values(array_filter([
                blank($record->cpf) ? 'CPF não informado' : null,
                blank($record->email) ? 'E-mail não informado' : null,
            ])),
            'patients' => array_values(array_filter([
                $record->tutor_id === null ? 'Responsável não vinculado' : null,
                $record->animal_species_id === null ? 'Espécie catalogada não vinculada' : null,
                $record->birth_date === null ? 'Nascimento não informado' : null,
            ])),
            'suppliers' => array_values(array_filter([
                blank($record->document) ? 'Documento não informado' : null,
                blank($record->email) && blank($record->phone) && blank($record->whatsapp)
                    ? 'Nenhum canal de contato informado'
                    : null,
            ])),
            'products' => array_values(array_filter([
                (float) $record->sale_price <= 0 ? 'Preço de venda deve ser positivo' : null,
                blank($record->barcode) && blank($record->sku)
                    ? 'Código de barras e SKU ausentes'
                    : null,
            ])),
            'stock' => ['Saldo positivo sem custo cadastrado'],
            'financial' => array_values(array_filter([
                (float) $record->amount <= 0 ? 'Valor deve ser positivo' : null,
                in_array($record->status, ['pending', 'overdue'], true) && $record->due_date === null
                    ? 'Data de vencimento ausente'
                    : null,
                $record->status === 'paid' && $record->paid_at === null
                    ? 'Data de pagamento ausente'
                    : null,
            ])),
        };
    }

    /**
     * @param  array<int, int>  $clinicIds
     * @return Collection<int, int>
     */
    private function countsByClinic(Builder $query, array $clinicIds): Collection
    {
        return $query
            ->whereIn('clinic_id', $clinicIds)
            ->whereNull('deleted_at')
            ->selectRaw('clinic_id, COUNT(*) AS issue_count')
            ->groupBy('clinic_id')
            ->pluck('issue_count', 'clinic_id')
            ->map(fn (mixed $count): int => (int) $count);
    }
}
