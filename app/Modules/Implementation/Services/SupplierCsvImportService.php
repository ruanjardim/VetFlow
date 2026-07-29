<?php

namespace App\Modules\Implementation\Services;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\BrazilianDocumentValidator;
use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Suppliers\Models\Supplier;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierCsvImportService implements CsvImportService
{
    private const COLUMNS = [
        'nome' => [
            'field' => 'name',
            'source_label' => 'nome',
            'target_label' => 'Nome',
        ],
        'cpf_cnpj' => [
            'field' => 'document',
            'source_label' => 'cpf_cnpj',
            'target_label' => 'CPF ou CNPJ',
        ],
        'telefone' => [
            'field' => 'phone',
            'source_label' => 'telefone',
            'target_label' => 'Telefone',
        ],
        'email' => [
            'field' => 'email',
            'source_label' => 'email',
            'target_label' => 'E-mail',
        ],
        'cidade' => [
            'field' => 'city',
            'source_label' => 'cidade',
            'target_label' => 'Cidade',
        ],
        'estado' => [
            'field' => 'state',
            'source_label' => 'estado',
            'target_label' => 'UF',
        ],
        'observacoes' => [
            'field' => 'notes',
            'source_label' => 'observacoes',
            'target_label' => 'Observações',
        ],
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
        return $this->analyzer->analyze(
            $file,
            $clinicId,
            array_keys(self::COLUMNS),
            function (array $source): array {
                $values = $this->mapRow($source);

                return [
                    'values' => $values,
                    'errors' => $this->validateRow($values),
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
            $importedCount = 0;

            foreach ($rows as $row) {
                $values = is_array($row['values'] ?? null)
                    ? $row['values']
                    : [];
                $errors = $this->validateRow($values);

                if ($errors !== []) {
                    throw new DomainException(implode(' ', array_unique($errors)));
                }

                Supplier::query()->create([
                    'clinic_id' => $clinicId,
                    'name' => $values['name'],
                    'document' => $values['document'],
                    'phone' => $values['phone'],
                    'email' => $values['email'],
                    'city' => $values['city'],
                    'state' => $values['state'],
                    'notes' => $values['notes'],
                    'active' => true,
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
        $state = $value('estado');

        return [
            'name' => $value('nome'),
            'document' => DocumentNormalizer::onlyNumbers($value('cpf_cnpj')),
            'phone' => $value('telefone'),
            'email' => $value('email'),
            'city' => $value('cidade'),
            'state' => $state !== null ? mb_strtoupper($state) : null,
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
                'name' => ['required', 'string', 'max:255'],
                'document' => ['nullable', 'string', 'max:14'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'city' => ['nullable', 'string', 'max:120'],
                'state' => ['nullable', 'string', 'size:2'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ],
            [
                'name.required' => 'Informe o nome do fornecedor.',
                'name.max' => 'O nome deve ter no máximo 255 caracteres.',
                'phone.max' => 'O telefone deve ter no máximo 30 caracteres.',
                'email.email' => 'Informe um e-mail válido.',
                'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
                'city.max' => 'A cidade deve ter no máximo 120 caracteres.',
                'state.size' => 'Informe a UF com duas letras.',
                'notes.max' => 'As observações devem ter no máximo 5.000 caracteres.',
            ]
        )->errors()->all();

        $document = $values['document'] ?? null;

        if (
            is_string($document)
            && ! BrazilianDocumentValidator::cpf($document)
            && ! BrazilianDocumentValidator::cnpj($document)
        ) {
            $errors[] = 'Informe um CPF ou CNPJ válido para o fornecedor.';
        }

        return array_values(array_unique($errors));
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
            throw new DomainException('Não há fornecedores válidos para importar.');
        }

        return $rows;
    }
}
