<?php
declare(strict_types=1);

namespace Tests\Helpers;

use Mockery;

/**
 * A lightweight, in‑memory Redis mock.
 *
 *  • Keeps hash / string writes so later reads see the changes
 *  • Supports both camel‑case and lower‑case command names
 *  • Takes an optional seed array:  key → scalar  OR  key → [field => value]
 */
trait MockRedisTrait
{
    /** @var \Mockery\MockInterface */
    protected $redisConn;

    /** @var array<string, mixed> */
    private array $redisData = [];

    /**
     * Build and bind the mock.
     *
     * @param array<string, mixed> $seed
     */
    protected function mockRedis(array $seed = []): void
    {
        /* -----------------------------------------------------------------
         | 1. Prime the in‑memory store and enable “missing method” support
         |------------------------------------------------------------------*/
        $this->redisData = $seed;
        Mockery::getConfiguration()->allowMockingNonExistentMethods(true);

        /* -----------------------------------------------------------------
         | 2. Create the mock connection
         |------------------------------------------------------------------*/
        $this->redisConn = Mockery::mock(\Illuminate\Redis\Connections\Connection::class)
            ->shouldIgnoreMissing();

        /* ---------- Helper to register the same stub under many names ---*/
        $alias = fn (array $names, callable $impl) =>
        array_map(fn ($n) => $this->redisConn->shouldReceive($n)->withAnyArgs()->andReturnUsing($impl)->byDefault(), $names);

        /* ---------- Hash reads -----------------------------------------*/
        $alias(['hGet', 'hget'], fn ($key, $field) =>
            isset($this->redisData[$key][$field]) ? (string) $this->redisData[$key][$field] : null
        );

        $alias(['hmget'], function ($key, ...$fields) {
            // allow either hmget($key, 'a','b','c') or hmget($key, ['a','b','c'])
            if (count($fields) === 1 && is_array($fields[0])) {
                $fields = $fields[0];
            }
            return array_map(fn($f) => $this->redisData[$key][$f] ?? null, $fields);
        });

        $alias(['hgetall'], fn ($key) => $this->redisData[$key] ?? []);

        $alias(['hMSet','hmset'], function ($key, array $map) {
            foreach ($map as $field => $value) {
                $this->redisData[$key][$field] = $value;
            }
            return true;
        });

        /* ---------- Hash writes ----------------------------------------*/
        $alias(['hIncrBy', 'hincrby'], function ($key, $field, $delta) {
            $cur = $this->redisData[$key][$field] ?? 0;
            $new = $cur + (int) $delta;
            $this->redisData[$key][$field] = $new;
            // return the mock so we can chain pExpire()
            return $this->redisConn;
        });

        $alias(['hIncrByFloat','hincrbyfloat'], function ($key, $field, $delta) {
            $cur = $this->redisData[$key][$field] ?? 0.0;
            $new = $cur + (float) $delta;
            $this->redisData[$key][$field] = $new;
            return $this->redisConn;
        });


        $this->redisConn
            ->shouldReceive('hSetNx')
            ->withAnyArgs()
            ->andReturnUsing(function($key, $field, $value) {
                if (! isset($this->redisData[$key][$field])) {
                    $this->redisData[$key][$field] = $value;
                    return true;
                }
                return false;
            })
            ->byDefault();

        /* ---------- Sets -----------------------------------------------*/
        $alias(['sIsMember', 'sismember'], function ($key, $slug) {
            return in_array($slug, $this->redisData[$key] ?? [], true);
        });

        /* ---------- Expire helper for pipeline chaining ---------------*/
        $alias(['pExpire','pexpire'], function ($key, $ttl) {
            // no-op, but return the connection so chaining works
            return $this->redisConn;
        });

        // actually add into the in-memory set
        $alias(['sAdd', 'sadd'], function ($key, ...$members) {
            if (! isset($this->redisData[$key]) || ! is_array($this->redisData[$key])) {
                $this->redisData[$key] = [];
            }
            $count = 0;
            foreach ($members as $m) {
                if (! in_array($m, $this->redisData[$key], true)) {
                    $this->redisData[$key][] = $m;
                    $count++;
                }
            }

            return $count;
        });

        /* ---------- Simple‑string helpers ------------------------------*/
        $alias(['setnx'], fn () => 1);                     // “key was set”

        /* ---------- Lua / script helpers -------------------------------*/
        $this->redisConn->shouldReceive('script')->andReturn('fake‑sha')->byDefault();

        $alias(['evalSha', 'evalsha'], function ($sha, $numKeys, ...$flatArgs) {
            $keys = array_slice($flatArgs, 0, $numKeys);
            $argv = array_slice($flatArgs, $numKeys);

            $achSetKey = $keys[0] ?? null;
            $statsKey  = $keys[1] ?? null;
            $xpAdd     = $argv[0] ?? 0;
            $slugs     = array_slice($argv, 1);

            foreach ($slugs as $slug) {
                if (!isset($this->redisData[$achSetKey])) {
                    $this->redisData[$achSetKey] = [];
                }

                if (!in_array($slug, $this->redisData[$achSetKey], true)) {
                    $this->redisData[$achSetKey][] = $slug;

                    if (!isset($this->redisData[$statsKey])) {
                        $this->redisData[$statsKey] = [];
                    }

                    $currentXp = (int)($this->redisData[$statsKey]['xp'] ?? 0);
                    $this->redisData[$statsKey]['xp'] = (string)($currentXp + (int)$xpAdd);

                    break; // only apply XP once per unlock
                }
            }

            return 1;
        });

        /* ---------- Pipeline helper ------------------------------------*/
        $this->redisConn->shouldReceive('pipeline')
            ->withAnyArgs()
            ->andReturnUsing(fn ($cb) => $cb($this->redisConn))
            ->byDefault();

        /* ---------- String reads ---------------------------------------*/
        $alias(['get'],    fn ($key) => $this->redisData[$key] ?? null);
        $alias(['exists'], fn ()     => 0);

        $this->redisConn
            ->shouldReceive('hMget')
            ->withAnyArgs()
            ->andReturnUsing(function($key, ...$fields) {
                // same unwrap logic
                if (count($fields) === 1 && is_array($fields[0])) {
                    $fields = $fields[0];
                }
                return array_map(fn($f) => $this->redisData[$key][$f] ?? null, $fields);
            })
            ->byDefault();

        /* -----------------------------------------------------------------
         | 3. Bind the mock into Laravel’s container and facade
         |------------------------------------------------------------------*/
        $factory = Mockery::mock(\Illuminate\Contracts\Redis\Factory::class);
        $factory->shouldReceive('connection')->andReturn($this->redisConn);

        $factory
            ->shouldReceive('pipeline')
            ->withAnyArgs()
            ->andReturnUsing(fn($cb) => $cb($this->redisConn))
            ->byDefault();

        // ── Stub script() on the *factory* itself so Redis::script(...) works ──
        $factory
            ->shouldReceive('script')
            ->withAnyArgs()
            ->andReturn('fake-sha')
            ->byDefault();

        $factory
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturnUsing(fn($key) => $this->redisData[$key] ?? null)
            ->byDefault();

        $factory
            ->shouldReceive('hSet')
            ->withAnyArgs()
            ->andReturnUsing(function ($key, $field, $value) {
                $this->redisData[$key][$field] = $value;
                return 1;
            })
            ->byDefault();

        $this->app->instance(\Illuminate\Contracts\Redis\Factory::class, $factory);
        \Illuminate\Support\Facades\Redis::swap($factory);
    }

    /**
     * Close Mockery after each test.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
