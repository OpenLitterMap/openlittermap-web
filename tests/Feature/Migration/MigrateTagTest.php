<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Location\Country;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Metrics\MetricsService;
use App\Services\Redis\RedisKeys;
use App\Services\Tags\GeneratePhotoSummaryService;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MigrateTagTest extends TestCase
{
    private Category $category;
    private LitterObject $retired;
    private LitterObject $desired;
    private CategoryObject $retiredClo;
    private Photo $photo;
    private PhotoTag $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);

        $this->category = Category::where('key', 'other')->firstOrFail();
        $this->retired = LitterObject::firstOrCreate(['key' => 'plastic_bag']);
        $this->desired = LitterObject::firstOrCreate(['key' => 'plasticBags']);
        $this->retiredClo = CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
        ]);

        CategoryObject::where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->delete();

        $this->photo = Photo::factory()->create([
            'verified' => 2,
            'user_id' => User::factory()->create()->id,
        ]);
        $this->tag = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
            'category_litter_object_id' => $this->retiredClo->id,
            'quantity' => 7,
        ]);

        $this->photo->generateSummary();
    }

    public function test_dry_run_reports_the_change_without_applying_it(): void
    {
        $this->migrate()
            ->expectsOutputToContain("DRY RUN: plastic_bag ({$this->retired->id}) → plasticBags ({$this->desired->id})")
            ->expectsOutputToContain('Rows: 1')
            ->expectsOutputToContain('Tags: 7')
            ->expectsOutputToContain("Example photo IDs: {$this->photo->id}")
            ->assertExitCode(0);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertSame($this->retired->id, $this->tag->fresh()->litter_object_id);
    }

    public function test_apply_retires_a_into_b_and_updates_the_tag_data(): void
    {
        $this->migrate(['--apply' => true])
            ->expectsOutputToContain('APPLY: plastic_bag')
            ->expectsOutputToContain('1/1 rows')
            ->assertExitCode(0);

        $retired = $this->retired->fresh();
        $tag = $this->tag->fresh();

        $this->assertNotNull($retired->retired_at);
        $this->assertSame($this->desired->id, $retired->merged_into_id);
        $this->assertSame($this->desired->id, $tag->litter_object_id);
        $this->assertSame($this->category->id, $tag->category_id);
        $this->assertSame(7, $tag->quantity);
        $this->assertDatabaseHas('category_litter_object', [
            'id' => $tag->category_litter_object_id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);
    }

    public function test_apply_refreshes_the_photo_summary(): void
    {
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $objects = $this->photo->fresh()->summary['keys']['objects'];

        $this->assertArrayNotHasKey($this->retired->id, $objects);
        $this->assertSame('plasticBags', $objects[$this->desired->id]);
    }

    public function test_apply_updates_quick_tags(): void
    {
        $quickTagId = DB::table('user_quick_tags')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'clo_id' => $this->retiredClo->id,
            'quantity' => 1,
            'materials' => '[]',
            'brands' => '[]',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $desiredCloId = CategoryObject::where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->value('id');

        $this->assertSame($desiredCloId, DB::table('user_quick_tags')->where('id', $quickTagId)->value('clo_id'));
    }

    public function test_apply_moves_metrics_for_processed_photos(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id]);
        app(MetricsService::class)->processPhoto($this->photo->fresh());

        $scope = RedisKeys::country($country->id);

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $this->assertSame(0, (int) Redis::hGet(RedisKeys::objects($scope), (string) $this->retired->id));
        $this->assertSame(7, (int) Redis::hGet(RedisKeys::objects($scope), (string) $this->desired->id));
    }

    public function test_apply_processes_more_than_one_photo_batch(): void
    {
        $photos = Photo::factory()->count(200)->create([
            'verified' => 2,
            'user_id' => $this->photo->user_id,
            'country_id' => null,
            'state_id' => null,
        ]);
        $now = now();

        DB::table('photo_tags')->insert($photos->map(fn (Photo $photo) => [
            'photo_id' => $photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
            'category_litter_object_id' => $this->retiredClo->id,
            'quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $this->assertSame(0, PhotoTag::where('litter_object_id', $this->retired->id)->count());
        $this->assertSame(201, PhotoTag::where('litter_object_id', $this->desired->id)->count());
        $this->assertArrayHasKey($this->desired->id, $photos->last()->fresh()->summary['keys']['objects']);
    }

    public function test_a_failed_batch_can_be_rerun(): void
    {
        $this->app->instance(GeneratePhotoSummaryService::class, new class extends GeneratePhotoSummaryService
        {
            public function run(Photo $photo): Photo
            {
                throw new \RuntimeException('summary failed');
            }
        });

        $this->migrate(['--apply' => true])
            ->expectsOutputToContain('Migration stopped: summary failed')
            ->assertExitCode(1);

        $this->assertSame($this->retired->id, $this->tag->fresh()->litter_object_id);

        $this->app->instance(GeneratePhotoSummaryService::class, new GeneratePhotoSummaryService());

        $this->migrate(['--apply' => true])->assertExitCode(0);
        $this->assertSame($this->desired->id, $this->tag->fresh()->litter_object_id);
    }

    public function test_invalid_mappings_fail_without_changes(): void
    {
        $this->artisan('olm:migrate-tag', [
            'retired' => 'plastic_bag',
            'desired' => 'plastic_bag',
            '--apply' => true,
        ])->assertExitCode(1);

        $this->desired->update(['retired_at' => now()]);
        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertSame($this->retired->id, $this->tag->fresh()->litter_object_id);
    }

    public function test_unknown_tag_fails(): void
    {
        $this->artisan('olm:migrate-tag', [
            'retired' => 'missing',
            'desired' => 'plasticBags',
        ])->assertExitCode(1);
    }

    private function migrate(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('olm:migrate-tag', array_merge([
            'retired' => 'plastic_bag',
            'desired' => 'plasticBags',
        ], $options));
    }
}
