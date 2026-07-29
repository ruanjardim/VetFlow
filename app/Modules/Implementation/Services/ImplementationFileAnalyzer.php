<?php

namespace App\Modules\Implementation\Services;

use App\Modules\Implementation\Contracts\CsvImportService;
use DateInterval;
use DateTimeInterface;
use DomainException;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;
use ZipArchive;

class ImplementationFileAnalyzer
{
    private const MAX_COLUMNS = 50;

    private const MAX_ROWS_WITH_HEADER = 502;

    private const MAX_ARCHIVE_ENTRIES = 500;

    private const MAX_UNCOMPRESSED_BYTES = 25 * 1024 * 1024;

    /**
     * @return array<string, mixed>
     */
    public function analyze(
        CsvImportService $importer,
        UploadedFile $file,
        int $clinicId,
        string $dataSource
    ): array {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if ($dataSource === 'csv') {
            if ($extension !== 'csv') {
                throw new DomainException('A origem CSV exige um arquivo com a extensão .csv.');
            }

            $analysis = $importer->analyze($file, $clinicId);
            $analysis['data_source'] = 'csv';

            return $analysis;
        }

        if ($dataSource !== 'excel' || $extension !== 'xlsx') {
            throw new DomainException('A origem Excel exige um arquivo com a extensão .xlsx.');
        }

        return $this->analyzeExcel($importer, $file, $clinicId);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeExcel(
        CsvImportService $importer,
        UploadedFile $file,
        int $clinicId
    ): array {
        $sourcePath = $file->getRealPath();

        if (! is_string($sourcePath)) {
            throw new DomainException('Não foi possível localizar a planilha Excel enviada.');
        }

        $this->assertSafeArchive($sourcePath);

        $csvPath = tempnam(sys_get_temp_dir(), 'vetflow-xlsx-');

        if ($csvPath === false) {
            throw new RuntimeException('Não foi possível preparar a análise temporária da planilha.');
        }

        try {
            $worksheetName = $this->convertFirstWorksheetToCsv($sourcePath, $csvPath);
            $csvUpload = new UploadedFile(
                $csvPath,
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.csv',
                'text/csv',
                null,
                true
            );
            $analysis = $importer->analyze($csvUpload, $clinicId);
            $analysis['data_source'] = 'excel';
            $analysis['worksheet'] = $worksheetName;
            $analysis['delimiter'] = 'planilha Excel';

            return $analysis;
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new DomainException(
                'Não foi possível ler a planilha Excel. Confirme se o arquivo .xlsx não está corrompido.'
            );
        } finally {
            @unlink($csvPath);
        }
    }

    private function assertSafeArchive(string $path): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new DomainException('O arquivo enviado não é uma planilha .xlsx válida.');
        }

        try {
            if ($archive->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new DomainException('A planilha Excel possui uma estrutura interna muito grande.');
            }

            $totalSize = 0;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                $totalSize += is_array($entry) ? (int) ($entry['size'] ?? 0) : 0;

                if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new DomainException('A planilha Excel excede o limite interno de processamento.');
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function convertFirstWorksheetToCsv(
        string $sourcePath,
        string $csvPath
    ): string {
        $reader = new Reader;
        $handle = fopen($csvPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário de análise.');
        }

        $worksheetName = '';
        $headerCount = null;

        try {
            $reader->open($sourcePath);

            foreach ($reader->getSheetIterator() as $worksheet) {
                $worksheetName = $worksheet->getName();
                $rowCount = 0;

                foreach ($worksheet->getRowIterator() as $row) {
                    $values = array_map(
                        fn (Cell $cell): string => $this->normalizeCell($cell),
                        $row->getCells()
                    );
                    $values = $this->trimTrailingEmptyValues($values);

                    if ($values === []) {
                        continue;
                    }

                    if (count($values) > self::MAX_COLUMNS) {
                        throw new DomainException(
                            sprintf(
                                'A primeira aba da planilha excede o limite de %d colunas.',
                                self::MAX_COLUMNS
                            )
                        );
                    }

                    if ($headerCount === null) {
                        $headerCount = count($values);
                    } else {
                        $values = array_pad($values, $headerCount, '');
                    }

                    fputcsv($handle, $values, ',', '"', '');
                    $rowCount++;

                    if ($rowCount >= self::MAX_ROWS_WITH_HEADER) {
                        break;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
            fclose($handle);
        }

        if ($worksheetName === '') {
            throw new DomainException('A planilha Excel não possui uma aba para importar.');
        }

        return $worksheetName;
    }

    private function normalizeCell(Cell $cell): string
    {
        $value = $cell instanceof FormulaCell
            ? $cell->getComputedValue()
            : $cell->getValue();

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof DateInterval) {
            throw new DomainException('A planilha contém uma duração não suportada.');
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function trimTrailingEmptyValues(array $values): array
    {
        while ($values !== [] && end($values) === '') {
            array_pop($values);
        }

        return $values;
    }
}
