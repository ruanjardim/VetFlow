<?php

namespace App\Modules\Implementation\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class ImplementationWorkflowService
{
    private const SESSION_KEY = 'implementation.tutor_csv';

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = Session::get(self::SESSION_KEY, []);

        if (! is_array($state)) {
            $this->reset();

            return [];
        }

        if ($state !== [] && ($state['user_id'] ?? null) !== Auth::id()) {
            $this->reset();

            return [];
        }

        return $state;
    }

    public function start(int $clinicId): void
    {
        $this->reset();

        Session::put(self::SESSION_KEY, [
            'user_id' => Auth::id(),
            'clinic_id' => $clinicId,
        ]);
    }

    public function selectSource(string $source): void
    {
        $state = $this->state();
        $this->deleteAnalysis($state);

        unset(
            $state['analysis_path'],
            $state['analysis_summary'],
            $state['file_name'],
            $state['completed']
        );

        $state['data_source'] = $source;

        Session::put(self::SESSION_KEY, $state);
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    public function storeAnalysis(array $analysis, string $originalName): void
    {
        $state = $this->state();
        $this->deleteAnalysis($state);

        $path = sprintf(
            'implementation/tutor-csv/%s/%s.json',
            Auth::id(),
            Str::uuid()
        );
        $encoded = json_encode(
            $analysis,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (! Storage::disk('local')->put($path, $encoded)) {
            throw new RuntimeException('Não foi possível armazenar a análise temporária do CSV.');
        }

        $state['analysis_path'] = $path;
        $state['analysis_summary'] = [
            'total_rows' => $analysis['total_rows'],
            'valid_rows' => $analysis['valid_rows'],
            'invalid_rows' => $analysis['invalid_rows'],
            'can_import' => $analysis['can_import'],
        ];
        $state['file_name'] = $originalName;

        unset($state['completed']);

        Session::put(self::SESSION_KEY, $state);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function analysis(): ?array
    {
        $path = $this->state()['analysis_path'] ?? null;

        if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        try {
            $decoded = json_decode(
                Storage::disk('local')->get($path),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            Storage::disk('local')->delete($path);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function complete(array $summary): void
    {
        $state = $this->state();
        $this->deleteAnalysis($state);

        unset($state['analysis_path'], $state['analysis_summary']);

        $state['completed'] = $summary;
        Session::put(self::SESSION_KEY, $state);
    }

    public function reset(): void
    {
        $state = Session::get(self::SESSION_KEY, []);

        if (is_array($state)) {
            $this->deleteAnalysis($state);
        }

        Session::forget(self::SESSION_KEY);
    }

    public function maxAllowedStep(): int
    {
        $state = $this->state();

        if (isset($state['completed'])) {
            return 8;
        }

        if (! isset($state['clinic_id'])) {
            return 1;
        }

        if (($state['data_source'] ?? null) !== 'csv') {
            return 2;
        }

        $analysis = $this->analysis();

        if ($analysis === null) {
            return 3;
        }

        return ($analysis['can_import'] ?? false) ? 7 : 5;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function deleteAnalysis(array $state): void
    {
        $path = $state['analysis_path'] ?? null;

        if (is_string($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
