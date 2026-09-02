<?php

namespace App\Support\Operations;

final class ReleaseIdentityService
{
    public function sha(): ?string
    {
        $sha = strtolower(trim((string) config('operations.release.sha')));

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
