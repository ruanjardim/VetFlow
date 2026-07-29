<?php

namespace App\Modules\Implementation\Services;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\Rules\ValidCpf;
use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class PatientCsvImportService implements CsvImportService
{
    private const MAX_ROWS = 500;

    private const COLUMNS = [
        'tutor_documento' => [
            'field' => 'tutor_document',
            'source_label' => 'tutor_documento',
            'target_label' => 'CPF do tutor responsável',
        ],
        'nome_pet' => [
            'field' => 'name',
            'source_label' => 'nome_pet',
            'target_label' => 'Nome do paciente',
        ],
        'especie' => [
            'field' => 'species',
            'source_label' => 'especie',
            'target_label' => 'Espécie',
        ],
        'raca' => [
            'field' => 'breed',
            'source_label' => 'raca',
            'target_label' => 'Raça',
        ],
        'sexo' => [
            'field' => 'gender',
            'source_label' => 'sexo',
            'target_label' => 'Sexo',
        ],
        'nascimento' => [
            'field' => 'birth_date',
            'source_label' => 'nascimento',
            'target_label' => 'Data de nascimento',
        ],
        'peso' => [
            'field' => 'weight',
            'source_label' => 'peso',
            'target_label' => 'Peso',
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
            throw new RuntimeException('Não foi possível localizar o arquivo enviado.');
        }

        $handle = fopen($realPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo enviado.');
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                return $this->emptyAnalysis(
                    $clinicId,
                    ['O arquivo está vazio.']
                );
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $headerRow = fgetcsv($handle, 0, $delimiter);

            if ($headerRow === false) {
                return $this->emptyAnalysis(
                    $clinicId,
                    ['Não foi possível ler o cabeçalho do arquivo.']
                );
            }

            $headers = array_map(
                fn (mixed $header): string => $this->normalizeHeader((string) $header),
                $headerRow
            );
            $fileErrors = $this->headerErrors($headers);
            $tutors = $this->tutorsByCpf($clinicId);
            $rows = [];
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
                $tutor = $values['tutor_document'] !== null
                    ? $tutors->get($values['tutor_document'])
                    : null;

                if ($values['tutor_document'] !== null && $tutor === null) {
                    $rowErrors[] = 'Nenhum tutor com este CPF foi encontrado na clínica selecionada.';
                }

                $values['tutor_id'] = $tutor?->id;
                $values['tutor_name'] = $tutor?->name;

                $rows[] = [
                    'line' => $line,
                    'values' => $values,
                    'errors' => array_values(array_unique($rowErrors)),
                ];
            }

            if ($rows === [] && $fileErrors === []) {
                $fileErrors[] = 'O arquivo não possui registros para importar.';
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
            throw new DomainException('A análise do arquivo não está pronta para importação.');
        }

        $rows = $analysis['rows'] ?? [];

        if (! is_array($rows) || $rows === []) {
            throw new DomainException('Não há pacientes válidos para importar.');
        }

        return DB::transaction(function () use ($rows, $clinicId): array {
            $importedCount = 0;

            foreach ($rows as $row) {
                $values = is_array($row['values'] ?? null)
                    ? $row['values']
                    : [];
                $errors = $this->validateRow($values);
                $tutor = Tutor::query()
                    ->where('clinic_id', $clinicId)
                    ->whereKey($values['tutor_id'] ?? null)
                    ->where('cpf', $values['tutor_document'] ?? null)
                    ->first();

                if ($tutor === null) {
                    $errors[] = 'O tutor responsável não está mais disponível para esta clínica.';
                }

                if ($errors !== []) {
                    throw new DomainException(implode(' ', array_unique($errors)));
                }

                Patient::query()->create([
                    'clinic_id' => $clinicId,
                    'tutor_id' => $tutor->id,
                    'name' => $values['name'],
                    'species' => $values['species'],
                    'breed' => $values['breed'],
                    'gender' => $values['gender'],
                    'birth_date' => $values['birth_date'],
                    'weight' => $values['weight'],
                    'notes' => $values['notes'],
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
            'tutor_document' => DocumentNormalizer::onlyNumbers($value('tutor_documento')),
            'name' => $value('nome_pet'),
            'species' => $value('especie'),
            'breed' => $value('raca'),
            'gender' => $value('sexo'),
            'birth_date' => $this->normalizeDate($value('nascimento')),
            'weight' => $this->normalizeWeight($value('peso')),
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
                'tutor_document' => ['required', new ValidCpf],
                'name' => ['required', 'string', 'max:255'],
                'species' => ['nullable', 'string', 'max:255'],
                'breed' => ['nullable', 'string', 'max:255'],
                'gender' => ['nullable', 'string', 'max:50'],
                'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'weight' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ],
            [
                'tutor_document.required' => 'Informe o CPF do tutor responsável.',
                'name.required' => 'Informe o nome do paciente.',
                'name.max' => 'O nome deve ter no máximo 255 caracteres.',
                'species.max' => 'A espécie deve ter no máximo 255 caracteres.',
                'breed.max' => 'A raça deve ter no máximo 255 caracteres.',
                'gender.max' => 'O sexo deve ter no máximo 50 caracteres.',
                'birth_date.date_format' => 'Informe o nascimento em DD/MM/AAAA ou AAAA-MM-DD.',
                'birth_date.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
                'weight.numeric' => 'Informe um peso válido.',
                'weight.gt' => 'O peso deve ser maior que zero.',
                'notes.max' => 'As observações devem ter no máximo 5.000 caracteres.',
            ]
        )->errors()->all();
    }

    /**
     * @return Collection<string, Tutor>
     */
    private function tutorsByCpf(int $clinicId): Collection
    {
        return Tutor::query()
            ->where('clinic_id', $clinicId)
            ->whereNotNull('cpf')
            ->get(['id', 'name', 'cpf'])
            ->keyBy('cpf');
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            $isValid = $date !== false
                && ($errors === false || (
                    $errors['warning_count'] === 0
                    && $errors['error_count'] === 0
                ))
                && $date->format($format) === $value;

            if ($isValid) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    private function normalizeWeight(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return $value;
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
