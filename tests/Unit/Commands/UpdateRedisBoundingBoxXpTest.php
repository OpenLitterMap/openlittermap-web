<?php

namespace Tests\Unit\Commands;

use App\Models\AI\Annotation;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UpdateRedisBoundingBoxXpTest extends TestCase
{
    public function test_it_includes_users_xp_from_adding_bounding_boxes_to_photos()
    {
        $user = User::factory()->create();
        Annotation::factory()->create([
            'added_by' => $user->id,
            'verified_by' => $user->id
        ]);
        Redis::del("xp.users");
        $this->assertNull(Redis::zscore("xp.users", $user->id));

        $this->artisan('users:update-redis-bounding-box-xp');

        $this->assertSame('2', Redis::zscore("xp.users", $user->id));
    }
}
