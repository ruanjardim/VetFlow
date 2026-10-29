<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleaseIdentityTest extends TestCase
{
    public function test_default_marker_path_survives_a_legacy_cached_configuration(): void
    {
        $marker = base_path('bootstrap/cache/vetflow-release.sha');
        $previousContents = is_file($marker) ? file_get_contents($marker) : false;
        $sha = str_repeat('d', 40);

        file_put_contents($marker, $sha.PHP_EOL);

        try {
            config([
                'operations.release.marker_path' => null,
                'operations.release.sha' => str_repeat('a', 40),
            ]);

            $this->get('/ops/release')
                ->assertOk()
                ->assertJsonPath('release.sha', $sha);
        } finally {
            if ($previousContents === false) {
                @unlink($marker);
            } else {
                file_put_contents($marker, $previousContents);
            }
        }
    }

    public function test_release_marker_takes_precedence_over_a_stale_environment_value(): void
    {
        $marker = tempnam(storage_path('framework/testing'), 'release-identity-');
        $sha = str_repeat('b', 40);

        $this->assertNotFalse($marker);
        file_put_contents($marker, strtoupper($sha).PHP_EOL);

        try {
            config([
                'operations.release.marker_path' => $marker,
                'operations.release.sha' => str_repeat('a', 40),
            ]);

            $this->get('/ops/release')
                ->assertOk()
                ->assertJsonPath('release.sha', $sha)
                ->assertJsonPath('release.short_sha', substr($sha, 0, 7));
        } finally {
            @unlink($marker);
        }
    }

    public function test_invalid_release_marker_falls_back_to_the_configured_commit(): void
    {
        $marker = tempnam(storage_path('framework/testing'), 'release-identity-');
        $sha = str_repeat('c', 40);

        $this->assertNotFalse($marker);
        file_put_contents($marker, 'invalid-marker');

        try {
            config([
                'operations.release.marker_path' => $marker,
                'operations.release.sha' => $sha,
            ]);

            $this->get('/ops/release')
                ->assertOk()
                ->assertJsonPath('release.sha', $sha);
        } finally {
            @unlink($marker);
        }
    }

    public function test_release_endpoint_reports_the_exact_configured_commit_without_cache(): void
    {
        $sha = str_repeat('a', 40);
        config(['operations.release.sha' => strtoupper($sha)]);

        $response = $this->get('/ops/release')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'release' => [
                    'sha' => $sha,
                    'short_sha' => substr($sha, 0, 7),
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_release_endpoint_is_unavailable_for_a_missing_or_invalid_commit(): void
    {
        config(['operations.release.sha' => 'not-a-git-sha']);

        $this->get('/ops/release')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
                'release' => [
                    'sha' => null,
                    'short_sha' => null,
                ],
            ]);
    }
}
