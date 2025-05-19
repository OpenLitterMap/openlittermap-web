<?php
namespace Tests\Helpers;

use Illuminate\Contracts\Redis\Factory as RedisFactory;

class InMemoryRedis
{
    public array $data = [];
    public function command($method, array $parameters = [])
    {
        // Not used
    }
    public function get($key)
    {
        return $this->data[$key] ?? null;
    }
    public function hget($key, $field)
    {
        return $this->data[$key][$field] ?? null;
    }
    public function hgetall($key)
    {
        return $this->data[$key] ?? [];
    }
    public function hmget($key, ...$fields)
    {
        return array_map(fn($f) => $this->data[$key][$f] ?? null, $fields);
    }
    public function hSetNx($key, $field, $val)
    {
        if (! isset($this->data[$key][$field])) {
            $this->data[$key][$field] = $val;
            return true;
        }
        return false;
    }
    public function hIncrBy($key, $field, $inc)
    {
        $cur = $this->data[$key][$field] ?? 0;
        $this->data[$key][$field] = $cur + $inc;
        return $this->data[$key][$field];
    }
    public function hIncrByFloat($key, $field, $inc)
    {
        $cur = $this->data[$key][$field] ?? 0.0;
        $this->data[$key][$field] = $cur + $inc;
        return $this->data[$key][$field];
    }
    public function sAdd($key, ...$members)
    {
        if (! isset($this->data[$key])) $this->data[$key] = [];
        $count = 0;
        foreach ($members as $m) {
            if (! in_array($m, $this->data[$key], true)) {
                $this->data[$key][] = $m;
                $count++;
            }
        }
        return $count;
    }
    public function sIsMember($key, $member)
    {
        return in_array($member, $this->data[$key] ?? [], true);
    }
    public function script($cmd, $script)
    {
        return 'fake-sha';
    }
    public function evalSha($sha, $numKeys, ...$args)
    {
        // identical logic to your old mock’s evalSha
        $keys = array_slice($args, 0, $numKeys);
        $argv = array_slice($args, $numKeys);
        [$achKey, $statsKey] = $keys;
        $xpAdd = $argv[0] ?? 0;
        $slugs = array_slice($argv, 1);
        foreach ($slugs as $slug) {
            if (! in_array($slug, $this->data[$achKey] ?? [], true)) {
                $this->sAdd($achKey, $slug);
                $curr = (int)($this->data[$statsKey]['xp'] ?? 0);
                $this->data[$statsKey]['xp'] = $curr + $xpAdd;
                break;
            }
        }
        return 1;
    }
    public function pipeline(callable $cb)
    {
        // in RedisMetricsCollector you do Redis::pipeline(fn($pipe)=> … )
        return $cb($this);
    }
    public function pExpire($key, $ttl) { /* no-op */ }
    public function exists($key) { return isset($this->data[$key]); }
}

class InMemoryRedisFactory implements RedisFactory
{
    private InMemoryRedis $conn;
    public function __construct()
    {
        $this->conn = new InMemoryRedis;
    }
    public function connection($name = null)
    {
        return $this->conn;
    }
}
