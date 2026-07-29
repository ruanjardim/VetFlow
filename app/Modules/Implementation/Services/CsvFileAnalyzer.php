<?php

namespace App\Modules\Implementation\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class CsvFileAnalyzer
{
    private const MAX_ROWS = 500;

    /**
     * @param  array<int, string>  $requiredHeaders
     * @param  Closure(array<string, mixed>, int): array{values: array<string, mixed>, errors: array<int, string>}  $rowAnalyzer
     * @return array<string, mixed>
     */
    public function analyze(
        UploadedFile $file,
        int $clinicId,
        array $requiredHeaders,
        Closure $rowAnalyzer
    ): array {
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
            $fileErrors = $this->headerErrors($headers, $requiredHeaders);
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
                $result = $rowAnalyzer(
                    is_array($source) ? $source : [],
                    $line
                );

                $rows[] = [
                    'line' => $line,
                    'values' => $result['values'],
                    'errors' => array_values(array_unique([
                        ...$rowErrors,
                        ...$result['errors'],
                    ])),
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
     * @param  array<int, string>  $requiredHeaders
     * @return array<int, string>
     */
    private function headerErrors(array $headers, array $requiredHeaders): array
    {
        $errors = [];
        $duplicates = array_keys(array_filter(
            array_count_values($headers),
            fn (int $count): bool => $count > 1
        ));

        if ($duplicates !== []) {
            $errors[] = 'O cabeçalho contém colunas duplicadas: '.implode(', ', $duplicates).'.';
        }

        $missing = array_values(array_diff($requiredHeaders, $headers));

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
