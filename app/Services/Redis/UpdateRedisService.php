<?php

namespace App\Services\Redis;

use App\Models\Photo;

final class UpdateRedisService
{
    public function updateRedis(Photo $photo): void
    {
        if (empty($photo->summary)) {
            return;
        }

        RedisMetricsCollector::queue($photo);
    }
}
