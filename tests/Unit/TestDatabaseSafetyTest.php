<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\CreatesApplication;

class TestDatabaseSafetyTest extends TestCase
{
    use CreatesApplication;

    /** @dataProvider unsafeConfigurations */
    public function test_unsafe_configuration_is_rejected_before_setup(string $key, mixed $value): void
    {
        $app = $this->safeApplication();
        $app['config']->set($key, $value);
        $this->expectException(RuntimeException::class);
        $this->assertSafeTestConfiguration($app);
    }

    public static function unsafeConfigurations(): array
    {
        return [
            ['database.connections.mysql.database', 'olm_postmig_6'],
            ['database.connections.mysql.host', 'production.example'],
            ['database.connections.mysql.url', 'mysql://root:secret@127.0.0.1/olm_postmig_6'],
            ['database.connections.mysql.read', ['host' => 'production.example']],
            ['database.connections.mysql.write', ['host' => 'production.example']],
            ['database.redis.default.database', 0],
            ['database.redis.default.host', 'production.example'],
            ['database.redis.default.url', 'redis://127.0.0.1:6379/0'],
            ['cache.default', 'redis'],
        ];
    }

    public function test_ci_configuration_is_accepted(): void
    {
        $this->assertSafeTestConfiguration($this->safeApplication());
        $this->addToAssertionCount(1);
    }

    public function test_non_testing_environment_is_rejected(): void
    {
        $app = $this->safeApplication();
        $app['env'] = 'local';
        $this->expectException(RuntimeException::class);
        $this->assertSafeTestConfiguration($app);
    }

    private function safeApplication(): Application
    {
        $app = new Application;
        $app['env'] = 'testing';
        $app['config'] = new Repository([
            'cache' => ['default' => 'array'],
            'database' => [
                'default' => 'mysql',
                'connections' => ['mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'olm_test']],
                'redis' => ['default' => ['host' => '127.0.0.1', 'database' => 2]],
            ],
        ]);
        return $app;
    }
}
