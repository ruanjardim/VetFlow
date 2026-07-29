<?php

namespace App\Modules\Implementation\Services;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\BrazilianDocumentValidator;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Suppliers\Models\Supplier;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinancialCsvImportService implements CsvImportService
{
    private const COLUMNS = [
        'tipo' => [
            'field' => 'type',
            'source_label' => 'tipo',
            'target_label' => 'Tipo',
        ],
        'descricao' => [
            'field' => 'description',
            'source_label' => 'descricao',
            'target_label' => 'Descrição',
        ],
        'pessoa_documento' => [
            'field' => 'supplier_document',
            'source_label' => 'pessoa_documento',
            'target_label' => 'CPF/CNPJ do fornecedor',
        ],
        'valor' => [
            'field' => 'amount',
            'source_label' => 'valor',
            'target_label' => 'Valor',
        ],
        'vencimento' => [
            'field' => 'due_date',
            'source_label' => 'vencimento',
            'target_label' => 'Vencimento',
        ],
        'status' => [
            'field' => 'status',
            'source_label' => 'status',
            'target_label' => 'Status',
        ],
        'forma_pagamento' => [
            'field' => 'payment_method',
            'source_label' => 'forma_pagamento',
            'target_label' => 'Forma de pagamento',
        ],
        'data_pagamento' => [
            'field' => 'paid_at',
            'source_label' => 'data_pagamento',
            'target_label' => 'Data de pagamento',
        ],
        'referencia' => [
            'field' => 'reference',
            'source_label' => 'referencia',
            'target_label' => 'Referência',
        ],
        'observacoes' => [
            'field' => 'notes',
            'source_label' => 'observacoes',
            'target_label' => 'Observações',
        ],
    ];

    private const TYPE_MAP = [
        'income' => 'income',
        'entrada' => 'income',
        'receita' => 'income',
        'recebimento' => 'income',
        'expense' => 'expense',
        'saida' => 'expense',
        'despesa' => 'expense',
        'pagamento' => 'expense',
    ];

    private const STATUS_MAP = [
        'pending' => 'pending',
        'pendente' => 'pending',
        'paid' => 'paid',
        'pago' => 'paid',
        'paga' => 'paid',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'cancelado' => 'cancelled',
        'cancelada' => 'cancelled',
        'overdue' => 'overdue',
        'vencido' => 'overdue',
        'vencida' => 'overdue',
        'atrasado' => 'overdue',
        'atrasada' => 'overdue',
    ];

    private const PAYMENT_METHOD_MAP = [
        'cash' => 'cash',
        'dinheiro' => 'cash',
        'pix' => 'pix',
        'debit_card' => 'debit_card',
        'debito' => 'debit_card',
        'cartao_de_debito' => 'debit_card',
        'credit_card' => 'credit_card',
        'credito' => 'credit_card',
        'cartao_de_credito' => 'credit_card',
        'transfer' => 'transfer',
        'transferencia' => 'transfer',
        'transferencia_bancaria' => 'transfer',
        'bank_slip' => 'bank_slip',
        'boleto' => 'bank_slip',
        'boleto_bancario' => 'bank_slip',
        'other' => 'other',
        'outro' => 'other',
        'outra' => 'other',
    ];

    private const TYPE_LABELS = [
        'income' => 'Entrada',
        'expense' => 'Saída',
    ];

    private const STATUS_LABELS = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'cancelled' => 'Cancelado',
        'overdue' => 'Vencido',
    ];

    private const PAYMENT_METHOD_LABELS = [
        'cash' => 'Dinheiro',
        'pix' => 'Pix',
        'debit_card' => 'Cartão de débito',
        'credit_card' => 'Cartão de crédito',
        'transfer' => 'Transferência',
        'bank_slip' => 'Boleto',
        'other' => 'Outro',
    ];

    public function __construct(
        private readonly CsvFileAnalyzer $analyzer,
        private readonly CsvValueNormalizer $normalizer
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

        return $this->analyzer->analyze(
            $file,
            $clinicId,
            array_keys(self::COLUMNS),
            function (array $source) use ($suppliers): array {
                $values = $this->mapRow($source);
                $errors = $this->validateRow($values);
                [$supplier, $supplierErrors] = $this->resolveSupplier(
                    $values['supplier_document'],
                    $suppliers
                );
                $values['supplier_id'] = $supplier['id'] ?? null;
                $values['supplier_name'] = $supplier['name'] ?? null;

                return [
                    'values' => $values,
                    'errors' => array_values(array_unique([
                        ...$errors,
                        ...$supplierErrors,
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
            $suppliers = $this->supplierIndex($clinicId);
            $importedCount = 0;

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
                ];

                if (
                    $supplier !== null
                    && (int) ($values['supplier_id'] ?? 0) !== (int) $supplier['id']
                ) {
                    $errors[] = 'O fornecedor do lançamento foi alterado após a análise do arquivo.';
                }

                if ($errors !== []) {
                    throw new DomainException(implode(' ', array_unique($errors)));
                }

                FinancialTransaction::query()->create([
                    'clinic_id' => $clinicId,
                    'supplier_id' => $supplier['id'] ?? null,
                    'purchase_entry_id' => null,
                    'installment_number' => 1,
                    'installment_total' => 1,
                    'type' => $values['type'],
                    'description' => $values['description'],
                    'amount' => $values['amount'],
                    'due_date' => $values['due_date'],
                    'paid_at' => $values['status'] === 'paid'
                        ? $values['paid_at']
                        : null,
                    'status' => $values['status'],
                    'payment_method' => $values['payment_method'],
                    'reference' => $values['reference'],
                    'notes' => $values['notes'],
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
        $type = $this->normalizeMappedValue($value('tipo'), self::TYPE_MAP);
        $status = $this->normalizeMappedValue(
            $value('status') ?? 'pending',
            self::STATUS_MAP
        );
        $paymentMethod = $this->normalizeMappedValue(
            $value('forma_pagamento'),
            self::PAYMENT_METHOD_MAP
        );

        return [
            'type' => $type,
            'type_label' => self::TYPE_LABELS[$type] ?? $type,
            'description' => $value('descricao'),
            'supplier_document' => DocumentNormalizer::onlyNumbers(
                $value('pessoa_documento')
            ),
            'amount' => $this->normalizer->decimal($source['valor'] ?? null),
            'due_date' => $this->normalizer->date($source['vencimento'] ?? null),
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? $status,
            'payment_method' => $paymentMethod,
            'payment_method_label' => $paymentMethod !== null
                ? (self::PAYMENT_METHOD_LABELS[$paymentMethod] ?? $paymentMethod)
                : null,
            'paid_at' => $this->normalizer->date($source['data_pagamento'] ?? null),
            'reference' => $value('referencia'),
            'notes' => $value('observacoes'),
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
                'type' => ['required', 'string', Rule::in(['income', 'expense'])],
                'description' => ['required', 'string', 'max:255'],
                'supplier_document' => ['nullable', 'string', 'max:14'],
                'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'due_date' => ['nullable', 'date_format:Y-m-d'],
                'status' => [
                    'required',
                    'string',
                    Rule::in(['pending', 'paid', 'cancelled', 'overdue']),
                ],
                'payment_method' => [
                    'nullable',
                    'string',
                    Rule::in([
                        'cash',
                        'pix',
                        'debit_card',
                        'credit_card',
                        'transfer',
                        'bank_slip',
                        'other',
                    ]),
                ],
                'paid_at' => ['nullable', 'date_format:Y-m-d'],
                'reference' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ],
            [
                'type.required' => 'Informe o tipo do lançamento.',
                'type.in' => 'Use entrada/receita ou saída/despesa no tipo.',
                'description.required' => 'Informe a descrição do lançamento.',
                'description.max' => 'A descrição deve ter no máximo 255 caracteres.',
                'amount.required' => 'Informe o valor do lançamento.',
                'amount.numeric' => 'Informe um valor válido.',
                'amount.min' => 'O valor não pode ser negativo.',
                'due_date.date_format' => 'Informe o vencimento em DD/MM/AAAA ou AAAA-MM-DD.',
                'status.in' => 'Use pendente, pago, cancelado ou vencido no status.',
                'payment_method.in' => 'Informe uma forma de pagamento válida.',
                'paid_at.date_format' => 'Informe a data de pagamento em DD/MM/AAAA ou AAAA-MM-DD.',
                'reference.max' => 'A referência deve ter no máximo 255 caracteres.',
                'notes.max' => 'As observações devem ter no máximo 5.000 caracteres.',
            ]
        )->errors()->all();

        $document = $values['supplier_document'] ?? null;

        if (
            is_string($document)
            && ! BrazilianDocumentValidator::cpf($document)
            && ! BrazilianDocumentValidator::cnpj($document)
        ) {
            $errors[] = 'Informe um CPF ou CNPJ válido para o fornecedor.';
        }

        $status = $values['status'] ?? null;
        $paidAt = $values['paid_at'] ?? null;

        if ($status === 'paid' && $paidAt === null) {
            $errors[] = 'Informe a data de pagamento para lançamentos pagos.';
        }

        if (
            in_array($status, ['pending', 'cancelled', 'overdue'], true)
            && $paidAt !== null
        ) {
            $errors[] = 'A data de pagamento deve ficar vazia para lançamentos não pagos.';
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
        if (
            $document === null
            || (
                ! BrazilianDocumentValidator::cpf($document)
                && ! BrazilianDocumentValidator::cnpj($document)
            )
        ) {
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
     * @param  array<string, string>  $map
     */
    private function normalizeMappedValue(?string $value, array $map): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return $map[$key] ?? $key;
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
            throw new DomainException('A análise do arquivo não está pronta para importação.');
        }

        $rows = $analysis['rows'] ?? [];

        if (! is_array($rows) || $rows === []) {
            throw new DomainException('Não há lançamentos financeiros válidos para importar.');
        }

        return $rows;
    }
}
