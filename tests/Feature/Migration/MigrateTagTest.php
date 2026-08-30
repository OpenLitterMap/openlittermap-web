<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
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

    /**
     * The v5 migration wrote rows straight onto the desired object before any pivot existed for
     * it, leaving `category_litter_object_id` null. Those rows never carry the retired object, so
     * the per-photo loop does not reach them — the backfill has to be a set-based update against
     * the pivot the run creates. Code that derives the pivot from (category_id, litter_object_id)
     * reads them fine; code that follows the stored pointer (quick tags, team tag editing) does not.
     */
    public function test_apply_backfills_the_clo_on_rows_already_on_the_desired_object(): void
    {
        $orphan = PhotoTag::create([
            'photo_id' => Photo::factory()->create(['verified' => 2, 'user_id' => $this->photo->user_id])->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
            'category_litter_object_id' => null,
            'quantity' => 3,
        ]);

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $desiredCloId = CategoryObject::where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->value('id');

        $this->assertSame($desiredCloId, $orphan->fresh()->category_litter_object_id);
    }

    /**
     * The run creates the survivor pivot and then regenerates summaries in the same process. A
     * resolver map memoised before that insert still answers "no pivot" for the survivor pairing,
     * which would write a null `clo_id` into every summary it touches.
     */
    public function test_apply_resolves_the_new_pivot_even_with_a_warm_resolver_cache(): void
    {
        CategoryObject::resolveId($this->category->id, $this->desired->id);

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $desiredCloId = CategoryObject::where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->value('id');

        $this->assertSame($desiredCloId, $this->photo->fresh()->summary['tags'][0]['clo_id']);
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

    /**
     * v4 baked the subtype into the object key (`beer_can` = `can` + type `beer`). Repointing the
     * object without setting the type would discard the subtype on a clean exit code, so the
     * split is expressed as one approved operation.
     */
    public function test_apply_sets_an_approved_type_on_the_migrated_rows(): void
    {
        [$type, ] = $this->approveTypeOnDesiredClo('beer');

        $this->migrate(['--apply' => true, '--type' => 'beer'])->assertExitCode(0);

        $tag = $this->tag->fresh();

        $this->assertSame($this->desired->id, $tag->litter_object_id);
        $this->assertSame($type->id, $tag->litter_object_type_id);
    }

    public function test_dry_run_reports_the_type(): void
    {
        $this->approveTypeOnDesiredClo('beer');

        $this->migrate(['--type' => 'beer'])
            ->expectsOutputToContain('+ type beer')
            ->assertExitCode(0);

        $this->assertNull($this->retired->fresh()->retired_at);
    }

    public function test_an_unknown_type_fails_without_changes(): void
    {
        $this->approveTypeOnDesiredClo('beer');

        $this->migrate(['--apply' => true, '--type' => 'nosuchtype'])->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertSame($this->retired->id, $this->tag->fresh()->litter_object_id);
    }

    /**
     * The type must already be approved for the survivor pairing. Attaching it here would be a
     * migration inventing taxonomy, which is the failure mode that created this mess.
     */
    public function test_a_type_not_approved_for_the_survivor_fails_without_changes(): void
    {
        LitterObjectType::firstOrCreate(['key' => 'beer']);
        CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);

        $this->migrate(['--apply' => true, '--type' => 'beer'])->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertNull($this->tag->fresh()->litter_object_type_id);
    }

    /** @return array{0: LitterObjectType, 1: CategoryObject} */
    private function approveTypeOnDesiredClo(string $typeKey): array
    {
        $type = LitterObjectType::firstOrCreate(['key' => $typeKey]);

        $clo = CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);

        DB::table('category_object_types')->insertOrIgnore([
            'category_litter_object_id' => $clo->id,
            'litter_object_type_id' => $type->id,
        ]);

        CategoryObject::flushResolverCache();

        return [$type, $clo];
    }

    /**
     * Some approved mappings move the pairing to another category (`other/dump` becomes
     * `dumping/dumping`). Without this the run would repoint the object, leave `category_id`
     * alone, and create a pivot for the survivor under the *old* category — inventing taxonomy
     * and landing on a pairing nobody approved.
     */
    public function test_apply_moves_the_pairing_to_an_approved_category(): void
    {
        $target = Category::where('key', 'dumping')->firstOrFail();
        $targetClo = CategoryObject::firstOrCreate([
            'category_id' => $target->id,
            'litter_object_id' => $this->desired->id,
        ]);
        CategoryObject::flushResolverCache();

        $this->migrate(['--apply' => true, '--category' => 'dumping'])->assertExitCode(0);

        $tag = $this->tag->fresh();

        $this->assertSame($this->desired->id, $tag->litter_object_id);
        $this->assertSame($target->id, $tag->category_id);
        $this->assertSame($targetClo->id, $tag->category_litter_object_id);
    }

    /**
     * Some approved mappings only move the shelf: `other/dogshit` becomes `pets/dogshit` with the
     * same object. That is not a retirement — the object stays in use — so the run must move the
     * rows without setting `retired_at`, and the same-object guard has to allow it.
     */
    public function test_a_pure_category_move_relocates_rows_without_retiring_the_object(): void
    {
        $target = Category::where('key', 'dumping')->firstOrFail();
        CategoryObject::firstOrCreate([
            'category_id' => $target->id,
            'litter_object_id' => $this->retired->id,
        ]);
        CategoryObject::flushResolverCache();

        $this->artisan('olm:migrate-tag', [
            'retired' => 'plastic_bag',
            'desired' => 'plastic_bag',
            '--category' => 'dumping',
            '--apply' => true,
        ])->assertExitCode(0);

        $tag = $this->tag->fresh();

        $this->assertSame($target->id, $tag->category_id);
        $this->assertSame($this->retired->id, $tag->litter_object_id);
        $this->assertNull(
            $this->retired->fresh()->retired_at,
            'a category move must leave the object in use'
        );
    }

    public function test_an_unknown_target_category_fails_without_changes(): void
    {
        $this->migrate(['--apply' => true, '--category' => 'nosuchcategory'])->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertSame($this->category->id, $this->tag->fresh()->category_id);
    }

    /**
     * A category move must land on a pairing the taxonomy already sanctions. Creating the pivot
     * here is the difference between applying an approved mapping and inventing one.
     */
    public function test_a_category_move_without_an_existing_target_pivot_fails(): void
    {
        Category::where('key', 'dumping')->firstOrFail();

        $this->migrate(['--apply' => true, '--category' => 'dumping'])->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertSame($this->category->id, $this->tag->fresh()->category_id);
    }

    /**
     * The v5 migration routed v4 `bags_litter` onto a shadow key with no special XP, so the
     * survivor is worth 10 XP and the retired key 1. Repointing the rows re-scores every tag, so
     * the guard refuses by default — an XP change reaches the leaderboard and must be a decision,
     * never a side effect of a naming fix.
     */
    public function test_a_mapping_that_changes_xp_is_refused_by_default(): void
    {
        $this->approveXpDifferentSurvivor();

        $this->artisan('olm:migrate-tag', [
            'retired' => 'plastic_bag',
            'desired' => 'bags_litter',
            '--apply' => true,
        ])->expectsOutputToContain('different XP values')->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertSame($this->retired->id, $this->tag->fresh()->litter_object_id);
    }

    public function test_an_approved_xp_correction_applies_with_the_explicit_flag(): void
    {
        $survivor = $this->approveXpDifferentSurvivor();

        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id]);
        app(MetricsService::class)->processPhoto($this->photo->fresh());

        $xpBefore = (int) User::find($this->photo->user_id)->xp;

        $this->artisan('olm:migrate-tag', [
            'retired' => 'plastic_bag',
            'desired' => 'bags_litter',
            '--apply' => true,
            '--allow-xp-change' => true,
        ])->expectsOutputToContain('XP per item: 1 → 10')->assertExitCode(0);

        $this->assertSame($survivor->id, $this->tag->fresh()->litter_object_id);
        $this->assertGreaterThan(
            $xpBefore,
            (int) User::find($this->photo->user_id)->xp,
            'the approved correction must re-score the tag'
        );
    }

    private function approveXpDifferentSurvivor(): LitterObject
    {
        $survivor = LitterObject::firstOrCreate(['key' => 'bags_litter']);

        CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $survivor->id,
        ]);
        CategoryObject::flushResolverCache();

        return $survivor;
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
