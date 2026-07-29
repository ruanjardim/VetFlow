<?php

namespace App\Modules\Implementation\Services;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\Rules\ValidCpf;
use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Tutors\Models\Tutor;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class TutorCsvImportService implements CsvImportService
{
    private const MAX_ROWS = 500;

    private const COLUMNS = [
        'nome' => [
            'field' => 'name',
            'source_label' => 'nome',
            'target_label' => 'Nome',
        ],
        'telefone' => [
            'field' => 'phone',
            'source_label' => 'telefone',
            'target_label' => 'Telefone principal',
        ],
        'whatsapp' => [
            'field' => 'phone_secondary',
            'source_label' => 'whatsapp',
            'target_label' => 'Telefone secundário',
        ],
        'email' => [
            'field' => 'email',
            'source_label' => 'email',
            'target_label' => 'E-mail',
        ],
        'cpf_cnpj' => [
            'field' => 'cpf',
            'source_label' => 'cpf_cnpj',
            'target_label' => 'CPF',
        ],
        'endereco' => [
            'field' => 'street',
            'source_label' => 'endereco',
            'target_label' => 'Logradouro',
        ],
        'observacoes' => [
            'field' => 'notes',
            'source_label' => 'observacoes',
            'target_label' => 'Observações',
        ],
    ];

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
        $realPath = $file->getRealPath();

        if (! is_string($realPath)) {
            throw new RuntimeException('Não foi possível localizar o arquivo CSV enviado.');
        }

        $handle = fopen($realPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo CSV enviado.');
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                return $this->emptyAnalysis(
                    $clinicId,
                    ['O arquivo CSV está vazio.']
                );
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $headerRow = fgetcsv($handle, 0, $delimiter);

            if ($headerRow === false) {
                return $this->emptyAnalysis(
                    $clinicId,
                    ['Não foi possível ler o cabeçalho do arquivo CSV.']
                );
            }

            $headers = array_map(
                fn (mixed $header): string => $this->normalizeHeader((string) $header),
                $headerRow
            );
            $fileErrors = $this->headerErrors($headers);
            $rows = [];
            $seenCpfs = [];
            $line = 1;

            while (($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;

                if ($this->isBlankRow($csvRow)) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    $fileErrors[] = sprintf(
                        'O arquivo excede o limite de %d registros por importação.',
                        self::MAX_ROWS
                    );
                    break;
                }

                $rowErrors = [];

                if (count($csvRow) !== count($headers)) {
                    $rowErrors[] = 'A quantidade de valores é diferente da quantidade de colunas.';
                }

                $csvRow = array_pad($csvRow, count($headers), null);
                $source = array_combine(
                    $headers,
                    array_slice($csvRow, 0, count($headers))
                );
                $values = $this->mapRow(is_array($source) ? $source : []);
                $rowErrors = [
                    ...$rowErrors,
                    ...$this->validateRow($values),
                ];
                $cpf = $values['cpf'];

                if ($cpf !== null && isset($seenCpfs[$cpf])) {
                    $rowErrors[] = sprintf(
                        'O CPF também aparece na linha %d deste arquivo.',
                        $seenCpfs[$cpf]
                    );
                } elseif ($cpf !== null) {
                    $seenCpfs[$cpf] = $line;
                }

                $rows[] = [
                    'line' => $line,
                    'values' => $values,
                    'errors' => array_values(array_unique($rowErrors)),
                ];
            }

            if ($rows === [] && $fileErrors === []) {
                $fileErrors[] = 'O arquivo CSV não possui registros para importar.';
            }

            $invalidRows = count(array_filter(
                $rows,
                fn (array $row): bool => $row['errors'] !== []
            ));
            $totalRows = count($rows);

            return [
                'clinic_id' => $clinicId,
                'delimiter' => $delimiter === ';' ? 'ponto e vírgula' : 'vírgula',
                'headers' => $headers,
                'file_errors' => array_values(array_unique($fileErrors)),
                'rows' => $rows,
                'total_rows' => $totalRows,
                'valid_rows' => $totalRows - $invalidRows,
                'invalid_rows' => $invalidRows,
                'can_import' => $totalRows > 0
                    && $invalidRows === 0
                    && $fileErrors === [],
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{imported_count: int}
     */
    public function import(array $analysis, int $clinicId): array
    {
        if (
            ($analysis['clinic_id'] ?? null) !== $clinicId
            || ! ($analysis['can_import'] ?? false)
        ) {
            throw new DomainException('A análise do CSV não está pronta para importação.');
        }

        $rows = $analysis['rows'] ?? [];

        if (! is_array($rows) || $rows === []) {
            throw new DomainException('Não há tutores válidos para importar.');
        }

        return DB::transaction(function () use ($rows, $clinicId): array {
            $importedCount = 0;

            foreach ($rows as $row) {
                $values = is_array($row['values'] ?? null)
                    ? $row['values']
                    : [];
                $errors = $this->validateRow($values);

                if ($errors !== []) {
                    throw new DomainException(implode(' ', $errors));
                }

                Tutor::query()->create([
                    'clinic_id' => $clinicId,
                    'name' => $values['name'],
                    'phone' => $values['phone'],
                    'phone_secondary' => $values['phone_secondary'],
                    'email' => $values['email'],
                    'cpf' => $values['cpf'],
                    'street' => $values['street'],
                    'notes' => $values['notes'],
                    'active' => true,
                ]);

                $importedCount++;
            }

            return ['imported_count' => $importedCount];
        });
    }

    private function detectDelimiter(string $line): string
    {
        $scores = [];

        foreach ([',', ';'] as $delimiter) {
            $scores[$delimiter] = count(str_getcsv($line, $delimiter));
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $this->toUtf8($header));

        return Str::of($header ?? '')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function headerErrors(array $headers): array
    {
        $errors = [];
        $duplicates = array_keys(array_filter(
            array_count_values($headers),
            fn (int $count): bool => $count > 1
        ));

        if ($duplicates !== []) {
            $errors[] = 'O cabeçalho contém colunas duplicadas: '.implode(', ', $duplicates).'.';
        }

        $missing = array_values(array_diff(array_keys(self::COLUMNS), $headers));

        if ($missing !== []) {
            $errors[] = 'Faltam colunas obrigatórias no cabeçalho: '.implode(', ', $missing).'.';
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return count(array_filter(
            $row,
            fn (mixed $value): bool => trim((string) $value) !== ''
        )) === 0;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, string|null>
     */
    private function mapRow(array $source): array
    {
        $value = fn (string $column): ?string => $this->nullableString(
            $source[$column] ?? null
        );

        return [
            'name' => $value('nome'),
            'phone' => $value('telefone'),
            'phone_secondary' => $value('whatsapp'),
            'email' => $value('email'),
            'cpf' => DocumentNormalizer::onlyNumbers($value('cpf_cnpj')),
            'street' => $value('endereco'),
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
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:20'],
                'phone_secondary' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:255'],
                'cpf' => [
                    'nullable',
                    new ValidCpf,
                    Rule::unique('tutors', 'cpf'),
                ],
                'street' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ],
            [
                'name.required' => 'Informe o nome do tutor.',
                'name.max' => 'O nome deve ter no máximo 255 caracteres.',
                'phone.required' => 'Informe o telefone principal do tutor.',
                'phone.max' => 'O telefone deve ter no máximo 20 caracteres.',
                'phone_secondary.max' => 'O WhatsApp deve ter no máximo 20 caracteres.',
                'email.email' => 'Informe um e-mail válido.',
                'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
                'cpf.unique' => 'Já existe um tutor cadastrado com este CPF.',
                'street.max' => 'O endereço deve ter no máximo 255 caracteres.',
                'notes.max' => 'As observações devem ter no máximo 5.000 caracteres.',
            ]
        )->errors()->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim($this->toUtf8((string) ($value ?? '')));

        return $value !== '' ? $value : null;
    }

    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>
     */
    private function emptyAnalysis(int $clinicId, array $errors): array
    {
        return [
            'clinic_id' => $clinicId,
            'delimiter' => null,
            'headers' => [],
            'file_errors' => $errors,
            'rows' => [],
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'can_import' => false,
        ];
    }
}
