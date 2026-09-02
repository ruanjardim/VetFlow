<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleaseIdentityTest extends TestCase
{
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
