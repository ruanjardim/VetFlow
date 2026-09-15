<?php

namespace App\Modules\Printers\Services;

use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Printers\Models\Printer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PrinterService
{
    public function __construct(
        private readonly AuditTrailService $audit
    ) {}

    /** @return array<string, string> */
    public static function types(): array
    {
        return [
            'fiscal' => 'Fiscal',
            'non_fiscal' => 'Não fiscal',
            'label' => 'Etiquetas',
            'document' => 'Documentos',
            'other' => 'Outra',
        ];
    }

    /** @return array<string, string> */
    public static function purposes(): array
    {
        return [
            'receipt' => 'Comprovantes do PDV',
            'fiscal_document' => 'Documento fiscal',
            'label' => 'Etiquetas',
            'prescription' => 'Receitas e documentos clínicos',
            'report' => 'Relatórios',
            'general' => 'Uso geral',
        ];
    }

    /** @return array<string, string> */
    public static function connections(): array
    {
        return [
            'browser' => 'Impressora do navegador/sistema',
            'network' => 'Rede (IP)',
            'usb' => 'USB',
            'serial' => 'Serial',
            'bluetooth' => 'Bluetooth',
            'cloud' => 'Serviço ou fila em nuvem',
        ];
    }

    /** @return array<string, string> */
    public static function paperSizes(): array
    {
        return [
            '58mm' => 'Bobina 58 mm',
            '80mm' => 'Bobina 80 mm',
            'a4' => 'A4',
            'label' => 'Etiqueta',
            'custom' => 'Personalizado',
        ];
    }

    public function paginate(): LengthAwarePaginator
    {
        return Printer::query()
            ->orderByDesc('is_default')
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(15);
    }

    public function find(int $id): Printer
    {
        return Printer::query()->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Printer
    {
        return DB::transaction(function () use ($data): Printer {
            $data = $this->normalize($data);
            $this->clearCurrentDefault($data);
            $printer = Printer::query()->create($data);

            $this->audit->record(
                'printer.created',
                $printer,
                [],
                $this->snapshot($printer),
                subjectLabel: $printer->name,
                clinicId: $printer->clinic_id
            );

            return $printer;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Printer $printer, array $data): Printer
    {
        return DB::transaction(function () use ($printer, $data): Printer {
            $before = $this->snapshot($printer);
            $data = $this->normalize($data);
            $this->clearCurrentDefault($data, $printer->id);
            $printer->update($data);
            $printer->refresh();

            $this->audit->record(
                'printer.updated',
                $printer,
                $before,
                $this->snapshot($printer),
                subjectLabel: $printer->name,
                clinicId: $printer->clinic_id
            );

            return $printer;
        });
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data): array
    {
        $data['active'] = (bool) $data['active'];
        $data['is_default'] = $data['active'] && (bool) $data['is_default'];

        if ($data['connection_type'] !== 'network') {
            $data['network_host'] = null;
            $data['network_port'] = null;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function clearCurrentDefault(array $data, ?int $exceptId = null): void
    {
        if (! $data['is_default']) {
            return;
        }

        Printer::query()
            ->when($exceptId, fn ($query, int $id) => $query->where('id', '!=', $id))
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /** @return array<string, mixed> */
    private function snapshot(Printer $printer): array
    {
        return $printer->only([
            'name', 'type', 'purpose', 'connection_type', 'paper_size',
            'queue_name', 'network_host', 'network_port', 'manufacturer',
            'model', 'is_default', 'active',
        ]);
    }
}
