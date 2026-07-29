<?php

namespace App\Modules\Implementation\Contracts;

use Illuminate\Http\UploadedFile;

interface CsvImportService
{
    /**
     * @return array<int, array<string, string>>
     */
    public function mappingDefinitions(): array;

    /**
     * @return array<string, mixed>
     */
    public function analyze(UploadedFile $file, int $clinicId): array;

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, int>
     */
    public function import(array $analysis, int $clinicId): array;
}
