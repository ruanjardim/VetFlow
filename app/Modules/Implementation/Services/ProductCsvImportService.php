<?php

namespace App\Modules\Implementation\Services;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\BrazilianDocumentValidator;
use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductService;
use App\Modules\Products\Support\Gtin;
use App\Modules\Suppliers\Models\Supplier;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductCsvImportService implements CsvImportService
{
    private const COLUMNS = [
        'nome' => [
            'field' => 'name',
            'source_label' => 'nome',
            'target_label' => 'Nome',
        ],
        'ean_gtin' => [
            'field' => 'gtin',
            'source_label' => 'ean_gtin',
            'target_label' => 'EAN/GTIN',
        ],
        'sku' => [
            'field' => 'sku',
            'source_label' => 'sku',
            'target_label' => 'SKU',
        ],
        'categoria' => [
            'field' => 'category',
            'source_label' => 'categoria',
            'target_label' => 'Categoria',
        ],
        'fornecedor_documento' => [
            'field' => 'supplier_document',
            'source_label' => 'fornecedor_documento',
            'target_label' => 'CPF/CNPJ do fornecedor',
        ],
        'custo' => [
            'field' => 'cost_price',
            'source_label' => 'custo',
            'target_label' => 'Preço de custo',
        ],
        'preco_venda' => [
            'field' => 'sale_price',
            'source_label' => 'preco_venda',
            'target_label' => 'Preço de venda',
        ],
        'estoque_atual' => [
            'field' => 'initial_stock',
            'source_label' => 'estoque_atual',
            'target_label' => 'Estoque inicial',
        ],
        'estoque_minimo' => [
            'field' => 'minimum_stock',
            'source_label' => 'estoque_minimo',
            'target_label' => 'Estoque mínimo',
        ],
    ];

    public function __construct(
        private readonly CsvFileAnalyzer $analyzer,
        private readonly CsvValueNormalizer $normalizer,
        private readonly ProductService $productService,
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
        $suppliers = $this->supplierIndex($clinicId);
        $products = $this->productIdentifierIndex($clinicId);
        $seenGtins = [];
        $seenSkus = [];

        return $this->analyzer->analyze(
            $file,
            $clinicId,
            array_keys(self::COLUMNS),
            function (array $source, int $line) use (
                $suppliers,
                $products,
                &$seenGtins,
                &$seenSkus
            ): array {
                $values = $this->mapRow($source);
                $errors = $this->validateRow($values);
                [$supplier, $supplierErrors] = $this->resolveSupplier(
                    $values['supplier_document'],
                    $suppliers
                );
                $errors = [
                    ...$errors,
                    ...$supplierErrors,
                    ...$this->identifierErrors(
                        $values,
                        $products,
                        $seenGtins,
                        $seenSkus,
                        $line
                    ),
                ];
                $values['supplier_id'] = $supplier['id'] ?? null;
                $values['supplier_name'] = $supplier['name'] ?? null;

                return [
                    'values' => $values,
                    'errors' => array_values(array_unique($errors)),
                ];
            }
        );
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{imported_count: int, movement_count: int}
     */
    public function import(array $analysis, int $clinicId): array
    {
        $rows = $this->validatedRows($analysis, $clinicId);

        return DB::transaction(function () use ($rows, $clinicId): array {
            $suppliers = $this->supplierIndex($clinicId);
            $products = $this->productIdentifierIndex($clinicId);
            $importedCount = 0;
            $movementCount = 0;

            foreach ($rows as $row) {
                $values = is_array($row['values'] ?? null)
                    ? $row['values']
                    : [];
                $errors = $this->validateRow($values);
                [$supplier, $supplierErrors] = $this->resolveSupplier(
                    $values['supplier_document'] ?? null,
                    $suppliers
                );
                $errors = [
                    ...$errors,
                    ...$supplierErrors,
                    ...$this->existingIdentifierErrors($values, $products),
                ];

                if (
                    $supplier !== null
                    && (int) ($values['supplier_id'] ?? 0) !== (int) $supplier['id']
                ) {
                    $errors[] = 'O fornecedor do produto foi alterado após a análise do CSV.';
                }

                if ($errors !== []) {
                    throw new DomainException(implode(' ', array_unique($errors)));
                }

                $metadata = [
                    'source' => 'implementation_csv',
                    'imported_at' => now()->toDateTimeString(),
                ];

                if ($supplier !== null) {
                    $metadata['supplier_name'] = $supplier['name'];
                    $metadata['supplier_document'] = $values['supplier_document'];
                }

                /** @var Product $product */
                $product = $this->productService->create([
                    'clinic_id' => $clinicId,
                    'name' => $values['name'],
                    'category' => $values['category'],
                    'sku' => $values['sku'],
                    'barcode' => $values['gtin'],
                    'gtin' => $values['gtin'],
                    'cost_price' => $values['cost_price'] ?? 0,
                    'sale_price' => $values['sale_price'] ?? 0,
                    'stock_quantity' => 0,
                    'minimum_stock' => $values['minimum_stock'] ?? 0,
                    'unit' => 'un',
                    'lookup_source' => 'implementation_csv',
                    'lookup_metadata' => $metadata,
                    'looked_up_at' => now(),
                    'active' => true,
                ]);

                $this->addProductToIdentifierIndex($product, $products);

                if ((float) ($values['initial_stock'] ?? 0) > 0) {
                    $this->inventoryService->create([
                        'clinic_id' => $clinicId,
                        'product_id' => $product->id,
                        'type' => 'entry',
                        'quantity' => $values['initial_stock'],
                        'unit_cost' => $values['cost_price'],
                        'occurred_at' => now(),
                        'reason' => 'Estoque inicial importado',
                        'notes' => 'Saldo criado durante a importação CSV de produtos.',
                        'source' => 'implementation_csv',
                    ]);

                    $movementCount++;
                }

                $importedCount++;
            }

            return [
                'imported_count' => $importedCount,
                'movement_count' => $movementCount,
            ];
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
            'name' => $value('nome'),
            'gtin' => Gtin::normalize($value('ean_gtin')),
            'sku' => $value('sku'),
            'category' => $value('categoria'),
            'supplier_document' => DocumentNormalizer::onlyNumbers(
                $value('fornecedor_documento')
            ),
            'cost_price' => $this->normalizer->decimal($source['custo'] ?? null),
            'sale_price' => $this->normalizer->decimal($source['preco_venda'] ?? null),
            'initial_stock' => $this->normalizer->decimal($source['estoque_atual'] ?? null),
            'minimum_stock' => $this->normalizer->decimal($source['estoque_minimo'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function validateRow(array $values): array
    {
        $errors = Validator::make(
            $values,
            [
                'name' => ['required', 'string', 'max:255'],
                'gtin' => ['nullable', 'string', 'max:32'],
                'sku' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:255'],
                'supplier_document' => ['nullable', 'string', 'max:14'],
                'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'initial_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
                'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            ],
            [
                'name.required' => 'Informe o nome do produto.',
                'name.max' => 'O nome deve ter no máximo 255 caracteres.',
                'sku.max' => 'O SKU deve ter no máximo 255 caracteres.',
                'category.max' => 'A categoria deve ter no máximo 255 caracteres.',
                'cost_price.numeric' => 'Informe um custo válido.',
                'cost_price.min' => 'O custo não pode ser negativo.',
                'sale_price.numeric' => 'Informe um preço de venda válido.',
                'sale_price.min' => 'O preço de venda não pode ser negativo.',
                'initial_stock.numeric' => 'Informe um estoque atual válido.',
                'initial_stock.min' => 'O estoque atual não pode ser negativo.',
                'minimum_stock.numeric' => 'Informe um estoque mínimo válido.',
                'minimum_stock.min' => 'O estoque mínimo não pode ser negativo.',
            ]
        )->errors()->all();

        $gtin = $values['gtin'] ?? null;

        if ($gtin !== null && ! Gtin::looksValid($gtin)) {
            $errors[] = 'Informe um EAN/GTIN com 8 a 14 dígitos.';
        }

        $supplierDocument = $values['supplier_document'] ?? null;

        if (
            is_string($supplierDocument)
            && ! $this->validBrazilianDocument($supplierDocument)
        ) {
            $errors[] = 'Informe um CPF ou CNPJ válido para o fornecedor.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    private function supplierIndex(int $clinicId): array
    {
        $index = [];

        Supplier::query()
            ->where('clinic_id', $clinicId)
            ->active()
            ->get(['id', 'name', 'document'])
            ->each(function (Supplier $supplier) use (&$index): void {
                $document = DocumentNormalizer::onlyNumbers($supplier->document);

                if ($document !== null) {
                    $index[$document][] = [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                    ];
                }
            });

        return $index;
    }

    /**
     * @param  array<string, array<int, array{id: int, name: string}>>  $index
     * @return array{0: array{id: int, name: string}|null, 1: array<int, string>}
     */
    private function resolveSupplier(?string $document, array $index): array
    {
        if ($document === null || ! $this->validBrazilianDocument($document)) {
            return [null, []];
        }

        $matches = $index[$document] ?? [];

        if ($matches === []) {
            return [
                null,
                ['Nenhum fornecedor com este CPF/CNPJ foi encontrado na clínica selecionada.'],
            ];
        }

        if (count($matches) > 1) {
            return [
                null,
                ['Mais de um fornecedor usa este CPF/CNPJ na clínica selecionada.'],
            ];
        }

        return [$matches[0], []];
    }

    /**
     * @return array{gtin: array<string, bool>, sku: array<string, bool>}
     */
    private function productIdentifierIndex(int $clinicId): array
    {
        $index = ['gtin' => [], 'sku' => []];

        Product::query()
            ->where('clinic_id', $clinicId)
            ->get(['id', 'gtin', 'barcode', 'sku'])
            ->each(function (Product $product) use (&$index): void {
                $this->addProductToIdentifierIndex($product, $index);
            });

        return $index;
    }

    /**
     * @param  array{gtin: array<string, bool>, sku: array<string, bool>}  $index
     */
    private function addProductToIdentifierIndex(Product $product, array &$index): void
    {
        foreach (Gtin::variants($product->gtin ?: $product->barcode) as $variant) {
            $index['gtin'][$variant] = true;
        }

        $sku = $this->skuKey($product->sku);

        if ($sku !== null) {
            $index['sku'][$sku] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array{gtin: array<string, bool>, sku: array<string, bool>}  $products
     * @param  array<string, int>  $seenGtins
     * @param  array<string, int>  $seenSkus
     * @return array<int, string>
     */
    private function identifierErrors(
        array $values,
        array $products,
        array &$seenGtins,
        array &$seenSkus,
        int $line
    ): array {
        $errors = $this->existingIdentifierErrors($values, $products);
        $gtin = $values['gtin'] ?? null;

        if (is_string($gtin) && Gtin::looksValid($gtin)) {
            $variants = Gtin::variants($gtin);
            $previousLine = null;

            foreach ($variants as $variant) {
                if (isset($seenGtins[$variant])) {
                    $previousLine = $seenGtins[$variant];
                    break;
                }
            }

            if ($previousLine !== null) {
                $errors[] = sprintf(
                    'O EAN/GTIN também aparece na linha %d deste arquivo.',
                    $previousLine
                );
            } else {
                foreach ($variants as $variant) {
                    $seenGtins[$variant] = $line;
                }
            }
        }

        $sku = $this->skuKey($values['sku'] ?? null);

        if ($sku !== null) {
            if (isset($seenSkus[$sku])) {
                $errors[] = sprintf(
                    'O SKU também aparece na linha %d deste arquivo.',
                    $seenSkus[$sku]
                );
            } else {
                $seenSkus[$sku] = $line;
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array{gtin: array<string, bool>, sku: array<string, bool>}  $products
     * @return array<int, string>
     */
    private function existingIdentifierErrors(array $values, array $products): array
    {
        $errors = [];
        $gtin = $values['gtin'] ?? null;

        if (is_string($gtin) && Gtin::looksValid($gtin)) {
            foreach (Gtin::variants($gtin) as $variant) {
                if (isset($products['gtin'][$variant])) {
                    $errors[] = 'Já existe um produto com este EAN/GTIN na clínica selecionada.';
                    break;
                }
            }
        }

        $sku = $this->skuKey($values['sku'] ?? null);

        if ($sku !== null && isset($products['sku'][$sku])) {
            $errors[] = 'Já existe um produto com este SKU na clínica selecionada.';
        }

        return $errors;
    }

    private function validBrazilianDocument(string $document): bool
    {
        return BrazilianDocumentValidator::cpf($document)
            || BrazilianDocumentValidator::cnpj($document);
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
            throw new DomainException('Não há produtos válidos para importar.');
        }

        return $rows;
    }
}
