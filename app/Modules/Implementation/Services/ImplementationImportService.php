<?php

namespace App\Modules\Implementation\Services;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Implementation\Models\ImplementationImport;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ImplementationImportService
{
    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public function import(
        CsvImportService $importer,
        array $analysis,
        Clinic $clinic,
        User $user,
        string $entityType,
        string $entityLabel,
        string $dataSource,
        string $fileName,
        DateTimeInterface $completedAt
    ): array {
        return DB::transaction(function () use (
            $importer,
            $analysis,
            $clinic,
            $user,
            $entityType,
            $entityLabel,
            $dataSource,
            $fileName,
            $completedAt
        ): array {
            $result = $importer->import($analysis, $clinic->id);

            $history = ImplementationImport::query()->create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'clinic_name' => $clinic->trade_name,
                'user_name' => $user->name,
                'entity_type' => $entityType,
                'entity_label' => $entityLabel,
                'data_source' => $dataSource,
                'file_name' => $this->normalizeFileName($fileName),
                'total_rows' => (int) ($analysis['total_rows'] ?? 0),
                'imported_count' => (int) $result['imported_count'],
                'invalid_rows' => (int) ($analysis['invalid_rows'] ?? 0),
                'completed_at' => $completedAt,
            ]);

            return [
                ...$result,
                'history_id' => $history->id,
            ];
        });
    }

    /**
     * @return Collection<int, ImplementationImport>
     */
    public function recentFor(User $user, int $limit = 10): Collection
    {
        $query = ImplementationImport::query()
            ->latest('completed_at')
            ->latest('id');

        if ($user->clinic_id !== null) {
            $query->where('clinic_id', $user->clinic_id);
        }

        return $query
            ->limit($limit)
            ->get();
    }

    private function normalizeFileName(string $fileName): string
    {
        $normalized = basename(str_replace('\\', '/', $fileName));

        return mb_substr($normalized, 0, 255);
    }
}
