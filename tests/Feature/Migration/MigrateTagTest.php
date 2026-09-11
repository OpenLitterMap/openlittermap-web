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

        // The survivor pairing is declared taxonomy: in production the seeder creates it before
        // any mapping runs. The command never invents it.
        CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);
        CategoryObject::flushResolverCache();

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
     * Summaries regenerated by the run derive the CLO through the memoised resolver. A map warmed
     * before the run must still answer with the survivor pivot for every summary it touches.
     */
    public function test_apply_resolves_the_survivor_pivot_with_a_warm_resolver_cache(): void
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
    /**
     * The shadow objects came from a migration that created pairings nobody declared. A survivor
     * pairing missing from the taxonomy is a decision for TagsConfig and the seeder, so the run
     * refuses rather than inventing it in place.
     */
    public function test_a_mapping_without_a_declared_survivor_pairing_fails_without_changes(): void
    {
        CategoryObject::where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->delete();
        CategoryObject::flushResolverCache();

        $this->migrate(['--apply' => true])
            ->expectsOutputToContain('No approved pivot')
            ->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertSame($this->retired->id, $this->tag->fresh()->litter_object_id);
        $this->assertDatabaseMissing('category_litter_object', [
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);
    }

    /**
     * Mappings are applied one at a time and each is an approved decision. A later mapping on the
     * same object must leave earlier tombstones alone: the pairing already moved, and its chain
     * continues through the survivor it recorded.
     */
    public function test_an_earlier_category_move_survives_a_later_object_retirement(): void
    {
        $dumping = Category::where('key', 'dumping')->firstOrFail();
        $movedClo = CategoryObject::firstOrCreate(['category_id' => $dumping->id, 'litter_object_id' => $this->retired->id]);
        $finalClo = CategoryObject::firstOrCreate(['category_id' => $dumping->id, 'litter_object_id' => $this->desired->id]);
        CategoryObject::flushResolverCache();

        $this->artisan('olm:migrate-tag', [
            'retired' => 'plastic_bag', 'desired' => 'plastic_bag', '--category' => 'dumping', '--apply' => true,
        ])->assertExitCode(0);
        $this->migrate(['--apply' => true])->assertExitCode(0);

        $firstTombstone = CategoryObject::find($this->retiredClo->id);

        $this->assertSame($movedClo->id, $firstTombstone->merged_into_clo_id, 'earlier approved move must not be overwritten');
        $this->assertSame($finalClo->id, $firstTombstone->resolveActiveClo()?->id);
        $this->assertSame($dumping->id, $this->tag->fresh()->category_id);
        $this->assertSame($this->desired->id, $this->tag->fresh()->litter_object_id);
    }

    public function test_a_retry_with_a_different_type_is_refused(): void
    {
        [$beer, $clo] = $this->approveTypeOnDesiredClo('beer');
        $wine = LitterObjectType::firstOrCreate(['key' => 'wine']);
        DB::table('category_object_types')->insertOrIgnore(['category_litter_object_id' => $clo->id, 'litter_object_type_id' => $wine->id]);

        $this->migrate(['--apply' => true, '--type' => 'beer'])->assertExitCode(0);
        $this->migrate(['--apply' => true, '--type' => 'wine'])
            ->expectsOutputToContain('already mapped')
            ->assertExitCode(1);

        $this->assertSame($beer->id, CategoryObject::find($this->retiredClo->id)->merged_into_type_id, 'recorded mapping is immutable');
    }

    /**
     * Rows with no category still belong to the retired object. They take the same object, type
     * and target category as every other row, or a type split leaves them half-migrated.
     */
    public function test_rows_without_a_category_take_the_type_and_target_category_too(): void
    {
        $dumping = Category::where('key', 'dumping')->firstOrFail();
        $targetClo = CategoryObject::firstOrCreate(['category_id' => $dumping->id, 'litter_object_id' => $this->desired->id]);
        $beer = LitterObjectType::firstOrCreate(['key' => 'beer']);
        DB::table('category_object_types')->insertOrIgnore(['category_litter_object_id' => $targetClo->id, 'litter_object_type_id' => $beer->id]);
        CategoryObject::flushResolverCache();
        $this->tag->update(['category_id' => null, 'category_litter_object_id' => null]);

        $this->migrate(['--apply' => true, '--type' => 'beer', '--category' => 'dumping'])->assertExitCode(0);

        $tag = $this->tag->fresh();

        $this->assertSame($this->desired->id, $tag->litter_object_id);
        $this->assertSame($beer->id, $tag->litter_object_type_id, 'type split must reach category-less rows');
        $this->assertSame($dumping->id, $tag->category_id);
        $this->assertSame($targetClo->id, $tag->category_litter_object_id);
    }

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

    /**
     * A saved quick tag is a preset the user re-applies. Repointing only its CLO turns a saved
     * `beer_can` into a plain `can`, silently dropping the subtype the approved split preserved
     * on every photo tag. `user_quick_tags.type_id` exists precisely to carry it.
     */
    public function test_apply_carries_the_approved_type_onto_repointed_quick_tags(): void
    {
        [$type, $survivorClo] = $this->approveTypeOnDesiredClo('beer');

        $quickTagId = DB::table('user_quick_tags')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'clo_id' => $this->retiredClo->id,
            'type_id' => null,
            'quantity' => 1,
            'materials' => '[]',
            'brands' => '[]',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrate(['--apply' => true, '--type' => 'beer'])->assertExitCode(0);

        $quickTag = DB::table('user_quick_tags')->where('id', $quickTagId)->first();

        $this->assertSame($survivorClo->id, (int) $quickTag->clo_id);
        $this->assertSame($type->id, (int) $quickTag->type_id, 'the subtype must survive the repoint');
    }

    /**
     * `litter_objects.merged_into_id` names the survivor object only. The approved mapping is a
     * triple, and the source pivot is the only place that can hold all of it — a retirement can
     * span several categories with a different survivor in each. Recording it there lets a stale
     * client resolve the old CLO to the exact approved pairing and subtype.
     */
    public function test_apply_records_the_survivor_clo_and_type_on_the_tombstone_pivot(): void
    {
        [$type, $survivorClo] = $this->approveTypeOnDesiredClo('beer');

        $this->migrate(['--apply' => true, '--type' => 'beer'])->assertExitCode(0);

        $tombstone = CategoryObject::find($this->retiredClo->id);

        $this->assertSame($survivorClo->id, $tombstone->merged_into_clo_id);
        $this->assertSame($type->id, $tombstone->merged_into_type_id);
        $this->assertSame($survivorClo->id, $tombstone->resolveActiveClo()?->id);
    }

    /**
     * Resolving by searching the ORIGINAL category finds nothing once the survivor lives in a
     * different category, so a stale submission of `other/automobile` was rejected as retired
     * instead of landing on `vehicles/car_part`. The recorded survivor pivot answers directly.
     */
    public function test_a_stale_clo_resolves_into_the_target_category_after_an_object_and_category_move(): void
    {
        $target = Category::where('key', 'dumping')->firstOrFail();
        $targetClo = CategoryObject::firstOrCreate([
            'category_id' => $target->id,
            'litter_object_id' => $this->desired->id,
        ]);
        CategoryObject::flushResolverCache();

        $this->migrate(['--apply' => true, '--category' => 'dumping'])->assertExitCode(0);

        $tombstone = CategoryObject::find($this->retiredClo->id);

        $this->assertSame($targetClo->id, $tombstone->resolveActiveClo()?->id, 'must not 422 a stale client');
    }

    /**
     * A pure category move keeps the object live, so nothing on the object hides the old
     * pairing — the source pivot stayed selectable and writable, quietly re-creating the pairing
     * the run had just emptied. The source pivot is now marked with its target and excluded from
     * the active set the picker serves.
     */
    public function test_a_pure_category_move_retires_the_source_pivot_and_hides_it_from_the_picker(): void
    {
        $target = Category::where('key', 'dumping')->firstOrFail();
        $targetClo = CategoryObject::firstOrCreate([
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

        $source = CategoryObject::find($this->retiredClo->id);

        $this->assertSame($targetClo->id, $source->merged_into_clo_id);
        $this->assertSame($targetClo->id, $source->resolveActiveClo()?->id);
        $this->assertFalse(CategoryObject::active()->whereKey($source->id)->exists(), 'source pivot must leave the picker');
        $this->assertTrue(CategoryObject::active()->whereKey($targetClo->id)->exists());
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
