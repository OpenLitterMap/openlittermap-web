<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use App\Services\Redis\RedisKeys;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MigrateTagResumeTest extends TestCase
{
    /** - Use real batch commits in olm_test; the next test must rebuild its own database. */
    protected function refreshTestDatabase(): void
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
        $this->app[Kernel::class]->setArtisan(null);
        RefreshDatabaseState::$migrated = false;
    }

    public function test_interruption_after_a_committed_batch_resumes_without_double_counting(): void
    {
        $category = Category::factory()->create(['key' => 'other']);
        $old = LitterObject::factory()->create(['key' => 'plasticBags']);
        $new = LitterObject::factory()->create(['key' => 'plastic_bag']);
        CategoryObject::create(['category_id' => $category->id, 'litter_object_id' => $new->id]);
        $first = Photo::factory()->create();
        $photos = collect([$first])->merge(Photo::factory()->count(200)->create([
            'user_id' => $first->user_id, 'country_id' => $first->country_id, 'state_id' => $first->state_id,
        ]));
        $summaries = new GeneratePhotoSummaryService;
        foreach ($photos as $photo) {
            PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $old->id, 'quantity' => 2]);
            $summaries->run($photo);
            app(MetricsService::class)->processPhoto($photo);
        }
        $xp = DB::table('users')->where('id', $first->user_id)->value('xp');
        $totals = DB::table('metrics')->get()->map(fn ($r) => [$r->uploads, $r->litter, $r->xp])->all();
        $calls = 0;
        $mock = Mockery::mock(GeneratePhotoSummaryService::class);
        $mock->shouldReceive('run')->andReturnUsing(function ($photo) use (&$calls, $summaries) {
            if (++$calls === 201) {
                throw new RuntimeException('Simulated interruption in the second batch.');
            }
            return $summaries->run($photo);
        });
        $this->app->instance(GeneratePhotoSummaryService::class, $mock);
        $options = ['old' => 'plasticBags', 'new' => 'plastic_bag', '--apply' => true];
        $this->artisan('olm:migrate-tag', $options)->assertFailed();
        $this->assertEquals(0, DB::transactionLevel());
        $this->assertEquals(200, PhotoTag::where('litter_object_id', $new->id)->count());
        $this->assertEquals(1, PhotoTag::where('litter_object_id', $old->id)->count());
        $this->assertNotNull($old->fresh()->retired_at);
        $this->assertEquals(400, Redis::hget(RedisKeys::objects(RedisKeys::global()), $new->id));
        $this->assertEquals(2, Redis::hget(RedisKeys::objects(RedisKeys::global()), $old->id));
        $this->app->instance(GeneratePhotoSummaryService::class, $summaries);
        $this->artisan('olm:migrate-tag', $options)->assertSuccessful();
        $this->artisan('olm:migrate-tag', $options)->assertSuccessful();
        $this->assertEquals(201, PhotoTag::where('litter_object_id', $new->id)->count());
        $this->assertEquals(0, PhotoTag::where('litter_object_id', $old->id)->count());
        foreach (RedisKeys::getPhotoScopes($first) as $scope) {
            $this->assertEquals(402, Redis::hget(RedisKeys::objects($scope), $new->id));
            $this->assertEquals(0, Redis::hget(RedisKeys::objects($scope), $old->id));
        }
        Redis::connection()->flushdb();
        $this->artisan('olm:redis:rebuild')->expectsConfirmation('This will FLUSHDB Redis. Continue?', 'yes')->assertSuccessful();
        foreach (RedisKeys::getPhotoScopes($first) as $scope) {
            $this->assertEquals(402, Redis::hget(RedisKeys::objects($scope), $new->id));
            $this->assertEquals(0, Redis::hget(RedisKeys::objects($scope), $old->id));
        }
        $this->assertEquals(402, Redis::hget(RedisKeys::user($first->user_id).':tags', 'obj:'.$new->id));
        $this->assertEquals(0, Redis::hget(RedisKeys::user($first->user_id).':tags', 'obj:'.$old->id));
        $this->assertEquals($xp, DB::table('users')->where('id', $first->user_id)->value('xp'));
        $this->assertEquals($totals, DB::table('metrics')->get()->map(fn ($r) => [$r->uploads, $r->litter, $r->xp])->all());
    }
}
