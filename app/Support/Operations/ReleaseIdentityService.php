<?php

namespace App\Support\Operations;

final class ReleaseIdentityService
{
    public function sha(): ?string
    {
        return $this->markerSha() ?? $this->normalize(config('operations.release.sha'));
    }

    private function markerSha(): ?string
    {
        $path = config('operations.release.marker_path');

        if (! is_string($path) || trim($path) === '') {
            $path = base_path('bootstrap/cache/vetflow-release.sha');
        }

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $this->normalize($contents);
    }

    private function normalize(mixed $value): ?string
    {
        $sha = strtolower(trim((string) $value));

        return preg_match('/^[a-f0-9]{40}$/', $sha) === 1 ? $sha : null;
    }

    /** @return array{status: string, release: array{sha: ?string, short_sha: ?string}} */
    public function payload(): array
    {
        $sha = $this->sha();

        return [
            'status' => $sha === null ? 'unavailable' : 'ok',
            'release' => [
                'sha' => $sha,
                'short_sha' => $sha === null ? null : substr($sha, 0, 7),
            ],
        ];
    }
}
