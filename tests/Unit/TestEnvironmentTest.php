<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\CreatesApplication;

class TestEnvironmentTest extends TestCase
{
    use CreatesApplication;

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_configuration_is_rejected_without_connecting(array $changes, string $environment = 'testing'): void
    {
        $previousContainer = Container::getInstance();
        $app = new Application;
        $app['env'] = $environment;
        $app['config'] = new Repository([
            'cache' => ['default' => 'array'],
            'database' => [
                'default' => 'mysql',
                'connections' => ['mysql' => ['driver' => 'mysql', 'database' => 'olm_test', 'host' => '127.0.0.1']],
                'redis' => ['default' => ['database' => 2, 'host' => '127.0.0.1']],
            ],
        ]);
        $app['config']->set($changes);
        // - These services must never be opened while rejecting unsafe settings.
        foreach (['db', 'redis'] as $service) {
            $app->bind($service, fn () => throw new \LogicException('Connected before checking test configuration.'));
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe test configuration');
        try {
            $this->assertSafeTestConfiguration($app);
        } finally {
            Container::setInstance($previousContainer);
        }
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'production environment' => [[], 'production'],
            'protected database' => [['database.connections.mysql.database' => 'olm_postmig_6']],
            'database URL override' => [['database.connections.mysql.url' => 'mysql://127.0.0.1/olm_postmig_6']],
            'remote database' => [['database.connections.mysql.host' => 'production.example']],
            'separate write connection' => [['database.connections.mysql.write' => ['database' => 'olm_postmig_6']]],
            'development Redis' => [['database.redis.default.database' => 0]],
            'Redis URL override' => [['database.redis.default.url' => 'redis://127.0.0.1/0']],
            'Redis URL query override' => [['database.redis.default.url' => 'redis://127.0.0.1/2?database=0']],
            'remote Redis' => [['database.redis.default.host' => 'production.example']],
            'shared cache' => [['cache.default' => 'redis']],
        ];
    }

    public function test_phpunit_overrides_inherited_database_settings_before_application_creation(): void
    {
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$config = (new PHPUnit\TextUI\XmlConfiguration\Loader)->load($argv[1].'/phpunit.xml');
(new PHPUnit\TextUI\Configuration\PhpHandler)->handle($config->php());
$bootstrap = new class { use Tests\CreatesApplication; };
$app = $bootstrap->createApplication();
echo json_encode([$app['config']->get('database.connections.mysql.database'),
    $app['config']->get('database.redis.default.database'), getenv('REDIS_DB')]);
PHP;
        $process = new Process([PHP_BINARY, '-r', $script, dirname(__DIR__, 2)], null, [
            'DB_DATABASE' => 'olm_postmig_6', 'REDIS_DB' => '0', 'REDIS_CACHE_DB' => '0',
            'DATABASE_URL' => false, 'REDIS_URL' => false,
        ]);
        $process->mustRun();

        $this->assertSame(['olm_test', '2', '2'], json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR));
    }
}
