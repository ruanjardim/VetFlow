<?php

namespace App\Modules\Implementation\Services;

use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImplementationDataQualityService
{
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
        $descriptions = [
            'tutors' => 'CPF ou e-mail não informado',
            'patients' => 'Responsável, espécie ou nascimento não informado',
            'suppliers' => 'Documento ou canal de contato não informado',
            'products' => 'Preço de venda ou identificador comercial ausente',
            'stock' => 'Saldo positivo sem custo cadastrado',
            'financial' => 'Valor ou data incompatível com o status',
        ];
        $readinessByClinic = collect($readiness)->keyBy('clinic_id');

        return $clinics
            ->map(function (Clinic $clinic) use (
                $counts,
                $descriptions,
                $readinessByClinic
            ): array {
                $clinicReadiness = $readinessByClinic->get($clinic->id, []);
                $coverageBlocks = collect($clinicReadiness['blocks'] ?? [])->keyBy('type');
                $blocks = $coverageBlocks
                    ->map(function (array $coverage, string $type) use (
                        $clinic,
                        $counts,
                        $descriptions
                    ): array {
                        $completed = (bool) ($coverage['completed'] ?? false);
                        $issueCount = $completed
                            ? (int) ($counts[$type]->get($clinic->id, 0))
                            : null;

                        return [
                            'type' => $type,
                            'label' => $coverage['label'],
                            'description' => $descriptions[$type],
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

    /** @param  array<int, int>  $clinicIds */
    private function tutorIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            DB::table('tutors')
                ->where('active', true)
                ->where(function (Builder $query): void {
                    $query->whereNull('cpf')
                        ->orWhere('cpf', '')
                        ->orWhereNull('email')
                        ->orWhere('email', '');
                }),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function patientIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            DB::table('patients')
                ->where(function (Builder $query): void {
                    $query->whereNull('tutor_id')
                        ->orWhereNull('animal_species_id')
                        ->orWhereNull('birth_date');
                }),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function supplierIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            DB::table('suppliers')
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
                }),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function productIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            DB::table('products')
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
                }),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function stockIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            DB::table('products')
                ->where('active', true)
                ->where('stock_quantity', '>', 0)
                ->where('cost_price', '<=', 0),
            $clinicIds
        );
    }

    /** @param  array<int, int>  $clinicIds */
    private function financialIssues(array $clinicIds): Collection
    {
        return $this->countsByClinic(
            DB::table('financial_transactions')
                ->where(function (Builder $query): void {
                    $query->where('amount', '<=', 0)
                        ->orWhere(function (Builder $pending): void {
                            $pending->whereIn('status', ['pending', 'overdue'])
                                ->whereNull('due_date');
                        })
                        ->orWhere(function (Builder $paid): void {
                            $paid->where('status', 'paid')->whereNull('paid_at');
                        });
                }),
            $clinicIds
        );
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
