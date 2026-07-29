<?php

namespace App\Modules\Implementation\Services;

use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\Gtin;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockCsvImportService implements CsvImportService
{
    private const COLUMNS = [
        'ean_gtin_ou_sku' => [
            'field' => 'identifier',
            'source_label' => 'ean_gtin_ou_sku',
            'target_label' => 'EAN/GTIN ou SKU',
        ],
        'quantidade' => [
            'field' => 'quantity',
            'source_label' => 'quantidade',
            'target_label' => 'Quantidade',
        ],
        'custo_unitario' => [
            'field' => 'unit_cost',
            'source_label' => 'custo_unitario',
            'target_label' => 'Custo unitário',
        ],
        'lote' => [
            'field' => 'lot_number',
            'source_label' => 'lote',
            'target_label' => 'Lote',
        ],
        'validade' => [
            'field' => 'expires_at',
            'source_label' => 'validade',
            'target_label' => 'Validade',
        ],
        'observacoes' => [
            'field' => 'notes',
            'source_label' => 'observacoes',
            'target_label' => 'Observações',
        ],
    ];

    public function __construct(
        private readonly CsvFileAnalyzer $analyzer,
        private readonly CsvValueNormalizer $normalizer,
        private readonly InventoryMovementService $inventoryService
    ) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function mappingDefinitions(): array
    {
        return array_values(self::COLUMNS);
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(UploadedFile $file, int $clinicId): array
    {
        $products = $this->productIndex($clinicId);

        return $this->analyzer->analyze(
            $file,
            $clinicId,
            array_keys(self::COLUMNS),
            function (array $source) use ($products): array {
                $values = $this->mapRow($source);
                $errors = $this->validateRow($values);
                [$product, $productErrors] = $this->resolveProduct(
                    $values['identifier'],
                    $products
                );
                $values['product_id'] = $product['id'] ?? null;
                $values['product_name'] = $product['name'] ?? null;

                return [
                    'values' => $values,
                    'errors' => array_values(array_unique([
                        ...$errors,
                        ...$productErrors,
                    ])),
                ];
            }
        );
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{imported_count: int}
     */
    public function import(array $analysis, int $clinicId): array
    {
        $rows = $this->validatedRows($analysis, $clinicId);

        return DB::transaction(function () use ($rows, $clinicId): array {
            $products = $this->productIndex($clinicId);
            $importedCount = 0;

            foreach ($rows as $row) {
                $values = is_array($row['values'] ?? null)
                    ? $row['values']
                    : [];
                $errors = $this->validateRow($values);
                [$product, $productErrors] = $this->resolveProduct(
                    $values['identifier'] ?? null,
                    $products
                );
                $errors = [
                    ...$errors,
                    ...$productErrors,
                ];

                if (
                    $product !== null
                    && (int) ($values['product_id'] ?? 0) !== (int) $product['id']
                ) {
                    $errors[] = 'O produto identificado foi alterado após a análise do CSV.';
                }

                if ($errors !== []) {
                    throw new DomainException(implode(' ', array_unique($errors)));
                }

                $this->inventoryService->create([
                    'clinic_id' => $clinicId,
                    'product_id' => $product['id'],
                    'type' => 'entry',
                    'quantity' => $values['quantity'],
                    'unit_cost' => $values['unit_cost'],
                    'lot_number' => $values['lot_number'],
                    'expires_at' => $values['expires_at'],
                    'occurred_at' => now(),
                    'reason' => 'Estoque inicial importado',
                    'notes' => $values['notes'],
                    'source' => 'implementation_csv',
                ]);

                $importedCount++;
            }

            return ['imported_count' => $importedCount];
        });
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, string|null>
     */
    private function mapRow(array $source): array
    {
        $value = fn (string $column): ?string => $this->normalizer->nullableString(
            $source[$column] ?? null
        );

        return [
            'identifier' => $value('ean_gtin_ou_sku'),
            'quantity' => $this->normalizer->decimal($source['quantidade'] ?? null),
            'unit_cost' => $this->normalizer->decimal($source['custo_unitario'] ?? null),
            'lot_number' => $value('lote'),
            'expires_at' => $this->normalizer->date($source['validade'] ?? null),
            'notes' => $value('observacoes'),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function validateRow(array $values): array
    {
        return Validator::make(
            $values,
            [
                'identifier' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
                'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'lot_number' => ['nullable', 'string', 'max:255'],
                'expires_at' => ['nullable', 'date_format:Y-m-d'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ],
            [
                'identifier.required' => 'Informe o EAN/GTIN ou SKU do produto.',
                'identifier.max' => 'O identificador deve ter no máximo 255 caracteres.',
                'quantity.required' => 'Informe a quantidade do estoque inicial.',
                'quantity.numeric' => 'Informe uma quantidade válida.',
                'quantity.gt' => 'A quantidade deve ser maior que zero.',
                'unit_cost.numeric' => 'Informe um custo unitário válido.',
                'unit_cost.min' => 'O custo unitário não pode ser negativo.',
                'lot_number.max' => 'O lote deve ter no máximo 255 caracteres.',
                'expires_at.date_format' => 'Informe a validade em DD/MM/AAAA ou AAAA-MM-DD.',
                'notes.max' => 'As observações devem ter no máximo 5.000 caracteres.',
            ]
        )->errors()->all();
    }

    /**
     * @return array{
     *     gtin: array<string, array<int, array{id: int, name: string}>>,
     *     sku: array<string, array<int, array{id: int, name: string}>>
     * }
     */
    private function productIndex(int $clinicId): array
    {
        $index = ['gtin' => [], 'sku' => []];

        Product::query()
            ->where('clinic_id', $clinicId)
            ->active()
            ->get(['id', 'name', 'gtin', 'barcode', 'sku'])
            ->each(function (Product $product) use (&$index): void {
                $item = [
                    'id' => $product->id,
                    'name' => $product->name,
                ];

                foreach (Gtin::variants($product->gtin ?: $product->barcode) as $variant) {
                    $index['gtin'][$variant][$product->id] = $item;
                }

                $sku = $this->skuKey($product->sku);

                if ($sku !== null) {
                    $index['sku'][$sku][$product->id] = $item;
                }
            });

        return $index;
    }

    /**
     * @param  array{
     *     gtin: array<string, array<int, array{id: int, name: string}>>,
     *     sku: array<string, array<int, array{id: int, name: string}>>
     * }  $index
     * @return array{0: array{id: int, name: string}|null, 1: array<int, string>}
     */
    private function resolveProduct(?string $identifier, array $index): array
    {
        if ($identifier === null) {
            return [null, []];
        }

        $matches = [];
        $gtin = Gtin::normalize($identifier);

        if (Gtin::looksValid($gtin)) {
            foreach (Gtin::variants($gtin) as $variant) {
                foreach ($index['gtin'][$variant] ?? [] as $id => $product) {
                    $matches[$id] = $product;
                }
            }
        }

        $sku = $this->skuKey($identifier);

        if ($sku !== null) {
            foreach ($index['sku'][$sku] ?? [] as $id => $product) {
                $matches[$id] = $product;
            }
        }

        if ($matches === []) {
            return [
                null,
                ['Nenhum produto ativo com este EAN/GTIN ou SKU foi encontrado na clínica selecionada.'],
            ];
        }

        if (count($matches) > 1) {
            return [
                null,
                ['O identificador corresponde a mais de um produto na clínica selecionada.'],
            ];
        }

        return [array_values($matches)[0], []];
    }

    private function skuKey(mixed $sku): ?string
    {
        $sku = trim((string) ($sku ?? ''));

        return $sku !== '' ? mb_strtolower($sku) : null;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, mixed>
     */
    private function validatedRows(array $analysis, int $clinicId): array
    {
        if (
            ($analysis['clinic_id'] ?? null) !== $clinicId
            || ! ($analysis['can_import'] ?? false)
        ) {
            throw new DomainException('A análise do CSV não está pronta para importação.');
        }

        $rows = $analysis['rows'] ?? [];

        if (! is_array($rows) || $rows === []) {
            throw new DomainException('Não há movimentos de estoque válidos para importar.');
        }

        return $rows;
    }
}
