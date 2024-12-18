<?php

namespace Tests;

use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /**
     * Set up the test environment and flush Redis.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Flush Redis before each test
        Redis::connection()->flushdb();
    }

    /**
     * Tear down the test environment and flush Redis.
     */
    protected function tearDown(): void
    {
        // Flush Redis after each test
        Redis::connection()->flushdb();

        parent::tearDown();
    }
}
