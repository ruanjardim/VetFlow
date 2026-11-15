<?php

namespace App\Modules\Patients\Services;

use App\Modules\Patients\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientPhotoService
{
    private const DISK = 'local';

    public function store(UploadedFile $photo, int $clinicId): string
    {
        $extension = match ($photo->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('O formato da foto do paciente não é suportado.'),
        };

        $path = $photo->storeAs(
            $this->directory($clinicId),
            Str::ulid().'.'.$extension,
            self::DISK
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Não foi possível armazenar a foto do paciente.');
        }

        return $path;
    }

    public function delete(?string $path, int $clinicId): void
    {
        if (! $this->belongsToClinic($path, $clinicId)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public function response(Patient $patient): StreamedResponse
    {
        $path = $patient->photo_path;
        $clinicId = (int) $patient->clinic_id;

        abort_unless(
            $this->belongsToClinic($path, $clinicId)
                && Storage::disk(self::DISK)->exists($path),
            404
        );

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = (Str::slug((string) $patient->name) ?: 'paciente').'.'.$extension;

        return Storage::disk(self::DISK)->response($path, $filename, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function directory(int $clinicId): string
    {
        if ($clinicId <= 0) {
            throw new RuntimeException('A foto do paciente exige uma clínica válida.');
        }

        return 'patients/'.$clinicId.'/photos';
    }

    private function belongsToClinic(?string $path, int $clinicId): bool
    {
        if (! is_string($path) || $path === '' || $clinicId <= 0) {
            return false;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        return ! str_contains($normalized, '..')
            && str_starts_with($normalized, $this->directory($clinicId).'/');
    }
}
