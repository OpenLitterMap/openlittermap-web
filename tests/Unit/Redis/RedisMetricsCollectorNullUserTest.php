<?php

declare(strict_types=1);

namespace Tests\Unit\Redis;

use App\Models\Location\Country;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * photos.user_id is nullable, and 151 processed rows on the production snapshot have no owner.
 *
 * Location and global metrics must still count that litter; every user-scoped write must be
 * skipped. Casting a null id to string yields "", which would otherwise add a phantom member to
 * the contributor HLL and the XP leaderboard.
 */
class RedisMetricsCollectorNullUserTest extends TestCase
{
    /** @return array<string, mixed> */
    private function metrics(): array
    {
        return [
            'litter' => 5,
            'xp' => 10,
            'tags' => [
                'categories' => [1 => 3],
                'objects' => [2 => 5],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ],
        ];
    }

    private function ownerlessPhoto(): Photo
    {
        $country = Country::factory()->create();

        return Photo::factory()->create([
            'user_id' => null,
            'country_id' => $country->id,
        ]);
    }

    public function test_ownerless_photo_does_not_throw(): void
    {
        $photo = $this->ownerlessPhoto();

        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'create');

        $this->assertEquals('1', Redis::hGet(RedisKeys::stats('{g}'), 'photos'));
    }

    public function test_ownerless_photo_still_records_global_and_location_metrics(): void
    {
        $photo = $this->ownerlessPhoto();

        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'create');

        $this->assertEquals('5', Redis::hGet(RedisKeys::stats('{g}'), 'litter'));
        $this->assertEquals('10', Redis::hGet(RedisKeys::stats('{g}'), 'xp'));

        $countryScope = RedisKeys::country($photo->country_id);
        $this->assertEquals('5', Redis::hGet(RedisKeys::stats($countryScope), 'litter'));

        $this->assertEquals('5', Redis::hGet(RedisKeys::objects('{g}'), '2'));
        $this->assertEquals('5', Redis::hGet(RedisKeys::objects($countryScope), '2'));
    }

    public function test_ownerless_photo_writes_no_user_scoped_keys(): void
    {
        $photo = $this->ownerlessPhoto();

        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'create');

        $this->assertEmpty(Redis::keys('*u:*'), 'No user-scoped key may be written for an ownerless photo.');
        $this->assertSame(0, (int) Redis::exists(RedisKeys::stats('{u:}')));
        $this->assertSame(0, (int) Redis::exists('{u:}:tags'));
    }

    public function test_ownerless_photo_does_not_pollute_contributor_or_xp_rankings(): void
    {
        $photo = $this->ownerlessPhoto();

        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'create');

        $this->assertSame(0, (int) Redis::zcard(RedisKeys::contributorRanking('{g}')));
        $this->assertSame(0, (int) Redis::zcard(RedisKeys::xpRanking('{g}')));
        $this->assertSame(0, (int) Redis::exists(RedisKeys::hll('{g}')));
    }

    public function test_ownerless_photo_delete_does_not_throw_or_touch_rankings(): void
    {
        $photo = $this->ownerlessPhoto();

        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'create');
        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'delete');

        $this->assertEquals('0', Redis::hGet(RedisKeys::stats('{g}'), 'photos'));
        $this->assertEquals('0', Redis::hGet(RedisKeys::stats('{g}'), 'litter'));
        $this->assertSame(0, (int) Redis::zcard(RedisKeys::xpRanking('{g}')));
    }

    public function test_owned_photo_still_writes_user_scoped_keys(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'create');

        $userScope = RedisKeys::user($user->id);
        $this->assertEquals('1', Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals('10', Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertSame(1, (int) Redis::zcard(RedisKeys::xpRanking('{g}')));
    }

    /**
     * A TypeError or any other Error raised inside Redis processing must be logged and
     * swallowed, not propagated. catch (\Exception) did not cover Error, so a null created_at
     * killed the calling process instead of degrading.
     */
    public function test_error_inside_redis_processing_is_logged_not_thrown(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();
        $photo->created_at = null;

        RedisMetricsCollector::processPhoto($photo, $this->metrics(), 'create');

        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => $message === 'Redis update failed')->once();
    }
}
