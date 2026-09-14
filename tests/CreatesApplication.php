<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->assertSafeTestConfiguration($app);

        return $app;
    }

    /** - Refuse destructive test setup outside the dedicated local test databases. */
    protected function assertSafeTestConfiguration(Application $app): void
    {
        $config = $app['config'];
        $parser = new ConfigurationUrlParser;
        $database = $parser->parseConfiguration($config->get('database.connections.'.$config->get('database.default'), []));
        $redis = $parser->parseConfiguration($config->get('database.redis.default', []));
        $localHosts = ['127.0.0.1', 'localhost', '::1'];

        if (! $app->environment('testing')
            || $config->get('cache.default') !== 'array'
            || ($database['driver'] ?? null) !== 'mysql'
            || ($database['database'] ?? null) !== 'olm_test'
            || ! in_array($database['host'] ?? null, $localHosts, true)
            || isset($database['read']) || isset($database['write'])
            || (string) ($redis['database'] ?? '') !== '2'
            || ! in_array($redis['host'] ?? null, $localHosts, true)) {
            throw new RuntimeException('Tests require testing, array cache, local MySQL olm_test and local Redis database 2.');
        }
    }
}
