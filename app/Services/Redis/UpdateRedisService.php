<?php

namespace App\Services\Redis;

use App\Models\Photo;

final class UpdateRedisService
{
    public function updateRedis(Photo $photo): void
    {
        RedisMetricsCollector::queue($photo);
    }
}
