<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deployment markers belong to the checked-out runtime, not to an
        // isolated test case. Tests opt in explicitly when exercising them.
        config(['operations.release.marker_path' => null]);
    }
}
