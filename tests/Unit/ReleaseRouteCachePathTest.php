<?php

namespace Tests\Unit;

use App\Support\Operations\ReleaseRouteCachePath;
use PHPUnit\Framework\TestCase;

class ReleaseRouteCachePathTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/vetflow-route-cache-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory.'/bootstrap/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        $marker = $this->temporaryDirectory.'/bootstrap/cache/vetflow-release.sha';

        if (is_file($marker)) {
            unlink($marker);
        }

        rmdir($this->temporaryDirectory.'/bootstrap/cache');
        rmdir($this->temporaryDirectory.'/bootstrap');
        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_it_resolves_a_release_specific_route_cache_path(): void
    {
        $sha = str_repeat('a', 40);
        file_put_contents(
            $this->temporaryDirectory.'/bootstrap/cache/vetflow-release.sha',
            strtoupper($sha).PHP_EOL
        );

        $this->assertSame(
            'bootstrap/cache/routes-v7-'.$sha.'.php',
            ReleaseRouteCachePath::resolve($this->temporaryDirectory)
        );
    }

    public function test_it_ignores_a_missing_or_invalid_release_marker(): void
    {
        $this->assertNull(ReleaseRouteCachePath::resolve($this->temporaryDirectory));

        file_put_contents(
            $this->temporaryDirectory.'/bootstrap/cache/vetflow-release.sha',
            'invalid-release'
        );

        $this->assertNull(ReleaseRouteCachePath::resolve($this->temporaryDirectory));
    }
}
