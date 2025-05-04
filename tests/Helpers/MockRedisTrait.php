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
            $this->redisData[$key][$field] ?? null);

        $alias(['hmget'], fn ($key, ...$fields) =>
        array_map(fn ($f) => $this->redisData[$key][$f] ?? null, $fields));

        $alias(['hgetall'], fn ($key) =>
            $this->redisData[$key] ?? []);

        /* ---------- Hash writes ----------------------------------------*/
        $alias(['hIncrBy', 'hincrby'], function ($key, $field, $delta) {
            $cur = $this->redisData[$key][$field] ?? 0;
            $new = $cur + (int) $delta;
            $this->redisData[$key][$field] = $new;
            return (string) $new;           // Redis returns bulk‑string
        });

        /* ---------- Sets -----------------------------------------------*/
        $alias(['sIsMember', 'sismember'], fn () => false);
        $alias(['sAdd', 'sadd'],          fn () => 1);    // “member added”

        /* ---------- Simple‑string helpers ------------------------------*/
        $alias(['setnx'], fn () => 1);                     // “key was set”

        /* ---------- Lua / script helpers -------------------------------*/
        $this->redisConn->shouldReceive('script')->andReturn('fake‑sha')->byDefault();
        $alias(['evalSha', 'evalsha'], fn () => 1);        // always “success”

        /* ---------- Pipeline helper ------------------------------------*/
        $this->redisConn->shouldReceive('pipeline')
            ->withAnyArgs()
            ->andReturnUsing(fn ($cb) => $cb($this->redisConn))
            ->byDefault();

        /* ---------- String reads ---------------------------------------*/
        $alias(['get'],    fn ($key) => $this->redisData[$key] ?? null);
        $alias(['exists'], fn ()     => 0);

        /* ---------- Fallback for hmget on unknown keys -----------------*/
        $this->redisConn->shouldReceive('hmget')
            ->andReturnUsing(fn ($k, ...$f) => array_fill(0, count($f), null))
            ->byDefault();

        /* -----------------------------------------------------------------
         | 3. Bind the mock into Laravel’s container and facade
         |------------------------------------------------------------------*/
        $factory = Mockery::mock(\Illuminate\Contracts\Redis\Factory::class);
        $factory->shouldReceive('connection')->andReturn($this->redisConn);
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
