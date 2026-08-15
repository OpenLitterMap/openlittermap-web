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
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Entry 1 retires the canonical `plastic_bag` in favour of the migration-minted `plasticBags`
 * (decision D-4), because the latter holds 10,051 of the 10,304 production rows.
 *
 * That inverts every boundary the pre-D-4 command asserted: the RETIRED side is the one with a
 * pivot and quick tags, and the SURVIVING side is the one that needs a pivot created.
 */
class MigrateTagTest extends TestCase
{
    private const QUEUE = 'storage/framework/testing/tag-retirements.csv';

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
        Storage::fake('local');

        $this->category = Category::where('key', 'other')->firstOrFail();

        // The key being retired. Step B.4 took it out of TagsConfig, so the seeder no longer
        // creates it — but the production row still exists and still holds its pivot, which is
        // exactly the state a run starts from. Build that here rather than lean on the config.
        $this->retired = LitterObject::firstOrCreate(['key' => 'plastic_bag'], ['crowdsourced' => false]);
        $this->retiredClo = CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
        ]);

        // The surviving key is now IN TagsConfig, so the seeder hands it a pivot. Production has
        // none yet — the run creates it (A.2) — so drop the seeded one to restore that premise.
        $this->desired = LitterObject::firstOrCreate(['key' => 'plasticBags'], ['crowdsourced' => true]);
        CategoryObject::where('litter_object_id', $this->desired->id)->delete();

        $user = User::factory()->create();
        $this->photo = Photo::factory()->create(['verified' => 2, 'user_id' => $user->id]);

        $this->tag = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
            'category_litter_object_id' => $this->retiredClo->id,
            'quantity' => 7,
        ]);

        $this->photo->generateSummary();
        $this->photo->refresh();

        $this->writeQueue('IDENTIFIED');
    }

    protected function tearDown(): void
    {
        @unlink(base_path(self::QUEUE));
        parent::tearDown();
    }

    private function writeQueue(string $status, array $overrides = []): void
    {
        $row = array_merge([
            'entry_id' => 'other--plastic_bag',
            'status' => $status,
            'class' => 'twin',
            'category_key' => 'other',
            'category_id' => $this->category->id,
            'retired_key' => 'plastic_bag',
            'retired_id' => $this->retired->id,
            'desired_key' => 'plasticBags',
            'desired_id' => $this->desired->id,
            'desired_clo_id' => '',
            'retired_clo_id' => $this->retiredClo->id,
            'retired_rows' => 1,
            'retired_items' => 7,
            'retired_photos' => 1,
            'desired_rows' => 0,
            'desired_items' => 0,
            'desired_photos' => 0,
            'xp_retired_per_item' => 1,
            'xp_desired_per_item' => 1,
            'xp_equivalent' => 'yes',
            'quick_tags_on_retired_clo' => 0,
            'approver' => '',
            'approved_at' => '',
            'verification_evidence' => '',
            'notes' => '',
        ], $overrides);

        @mkdir(dirname(base_path(self::QUEUE)), 0777, true);
        $fh = fopen(base_path(self::QUEUE), 'w');
        fputcsv($fh, array_keys($row));
        fputcsv($fh, array_values($row));
        fclose($fh);
    }

    /**
     * Read the single entry back out of the queue file.
     *
     * @return array<string, string>
     */
    private function queueRow(): array
    {
        $fh = fopen(base_path(self::QUEUE), 'r');
        $header = fgetcsv($fh);
        $row = fgetcsv($fh);
        fclose($fh);

        return array_combine($header, $row);
    }

    /**
     * The refusal assertion for every "this must not have moved the data" test: the tag is
     * still on the retired object.
     */
    private function assertTagDidNotMove(): void
    {
        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $this->retired->id,
        ]);
    }

    private function migrate(array $opts = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('olm:migrate-tag', array_merge([
            '--entry' => 'other--plastic_bag',
            '--queue' => self::QUEUE,
        ], $opts));
    }

    private function applyReady(): void
    {
        $this->writeQueue('DRY_RUN_VERIFIED');
    }

    /** Bring the entry to the apply gate and run a successful apply. */
    private function applyOk(): void
    {
        $this->applyReady();
        $this->migrate(['--apply' => true])->assertExitCode(0);
    }

    private function insertQuickTag(int $cloId): void
    {
        $this->insertQuickTagFor(User::factory()->create()->id, $cloId);
    }

    private function insertQuickTagFor(int $userId, int $cloId, array $overrides = []): int
    {
        return DB::table('user_quick_tags')->insertGetId(array_merge([
            'user_id' => $userId,
            'clo_id' => $cloId,
            'quantity' => 1,
            'materials' => '[]',
            'brands' => '[]',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Point the default connection at a closed port for the duration of the callback.
     *
     * `RedisManager` captures its config at construction, so rewriting `config()` alone changes
     * nothing — the container instance and the facade's resolved instance both have to go.
     */
    private function withUnreachableRedis(callable $fn): void
    {
        $original = config('database.redis');

        config(['database.redis.default' => array_merge($original['default'], [
            'host' => '127.0.0.1',
            'port' => 63999,
        ])]);
        $this->rebuildRedisManager();

        try {
            $fn();
        } finally {
            config(['database.redis' => $original]);
            $this->rebuildRedisManager();
        }
    }

    private function rebuildRedisManager(): void
    {
        app()->forgetInstance('redis');
        app()->forgetInstance('redis.connection');
        Redis::clearResolvedInstances();
    }

    /** The command's own resume fingerprint, so a hand-written snapshot is accepted. */
    private function mappingFingerprint(): string
    {
        $method = (new \ReflectionClass(\App\Console\Commands\tmp\v5\PostMigAug2026\MigrateTag::class))
            ->getMethod('mappingFingerprint');
        $method->setAccessible(true);

        return $method->invoke(new \App\Console\Commands\tmp\v5\PostMigAug2026\MigrateTag(), $this->queueRow());
    }

    /**
     * Reach the private selector directly — the same code path a dry run and an apply both call.
     *
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    private function samplePhotoIds(array $ids): array
    {
        $method = (new \ReflectionClass(\App\Console\Commands\tmp\v5\PostMigAug2026\MigrateTag::class))
            ->getMethod('samplePhotoIds');
        $method->setAccessible(true);

        return $method->invoke(new \App\Console\Commands\tmp\v5\PostMigAug2026\MigrateTag(), $ids);
    }

    // ── Gating ───────────────────────────────────────────────────────────────

    public function test_apply_is_blocked_until_dry_run_verified(): void
    {
        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertTagDidNotMove();
    }

    public function test_apply_is_still_blocked_at_code_updated(): void
    {
        $this->writeQueue('CODE_UPDATED');

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertTagDidNotMove();
    }

    public function test_status_transitions_must_be_sequential(): void
    {
        $this->migrate(['--advance' => 'LOCAL_APPLIED', '--by' => 'sean'])->assertExitCode(1);
    }

    public function test_advance_requires_an_author(): void
    {
        $this->migrate(['--advance' => 'MAPPING_APPROVED'])->assertExitCode(1);
    }

    public function test_verified_transitions_require_evidence(): void
    {
        $this->writeQueue('CODE_UPDATED');

        $this->migrate(['--advance' => 'DRY_RUN_VERIFIED', '--by' => 'sean'])->assertExitCode(1);
    }

    public function test_advancing_preserves_the_original_approval_and_records_every_transition(): void
    {
        $this->writeQueue('MAPPING_APPROVED', [
            'approver' => 'product-owner',
            'approved_at' => '2026-08-08',
            'verification_evidence' => 'REHEARSED 3x on olm_postmig_3',
        ]);

        $this->migrate(['--advance' => 'CODE_UPDATED', '--by' => 'sean'])->assertExitCode(0);
        $this->migrate([
            '--advance' => 'DRY_RUN_VERIFIED',
            '--by' => 'sean',
            '--evidence' => 'dry run counts matched',
        ])->assertExitCode(0);

        $row = $this->queueRow();

        $this->assertSame('DRY_RUN_VERIFIED', $row['status']);
        $this->assertSame('product-owner', $row['approver'], 'The mapping approval must survive later rungs.');
        $this->assertSame('2026-08-08', $row['approved_at']);

        $this->assertStringContainsString('REHEARSED 3x on olm_postmig_3', $row['verification_evidence']);
        $this->assertStringContainsString('CODE_UPDATED by sean', $row['verification_evidence']);
        $this->assertStringContainsString('DRY_RUN_VERIFIED by sean', $row['verification_evidence']);
        $this->assertStringContainsString('dry run counts matched', $row['verification_evidence']);
    }

    public function test_unknown_entry_fails(): void
    {
        $this->artisan('olm:migrate-tag', ['--entry' => 'nope', '--queue' => self::QUEUE])
            ->assertExitCode(1);
    }

    public function test_expectation_mismatch_aborts(): void
    {
        $this->writeQueue('DRY_RUN_VERIFIED', ['retired_items' => 999]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertTagDidNotMove();
    }

    // ── Failed apply leaves no half-retired object ──────────────────────────

    public function test_expectation_mismatch_leaves_the_object_active(): void
    {
        $this->writeQueue('DRY_RUN_VERIFIED', ['retired_items' => 999]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->retired->refresh();

        $this->assertNull($this->retired->retired_at);
        $this->assertNull($this->retired->merged_into_id);
    }

    public function test_a_failed_apply_does_not_undo_a_prior_runs_retirement(): void
    {
        $this->retired->update(['retired_at' => now(), 'merged_into_id' => $this->desired->id]);

        $this->writeQueue('DRY_RUN_VERIFIED', ['retired_items' => 999]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertNotNull($this->retired->fresh()->retired_at);
    }

    public function test_a_retirement_pointing_somewhere_else_is_refused_without_mutation(): void
    {
        $elsewhere = LitterObject::firstOrCreate(['key' => 'somewhere_else'], ['crowdsourced' => false]);
        $this->retired->update(['retired_at' => now(), 'merged_into_id' => $elsewhere->id]);

        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertSame($elsewhere->id, (int) $this->retired->fresh()->merged_into_id);
        $this->assertTagDidNotMove();
    }

    /**
     * The survivor is where the data lands. Migrating into an object that has itself been retired
     * would mint a pivot for a closed key and bury 10,051 rows behind it, and `--verify` only
     * notices afterwards — by which point apply has already reported success and cleared the
     * snapshot. The assertion therefore belongs ahead of the first mutation.
     */
    public function test_apply_is_refused_when_the_survivor_is_itself_retired(): void
    {
        $this->desired->update(['retired_at' => now()]);

        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertTagDidNotMove();
        $this->assertDatabaseMissing('category_litter_object', [
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);
    }

    public function test_apply_is_refused_when_the_survivor_does_not_exist(): void
    {
        $this->writeQueue('DRY_RUN_VERIFIED', ['desired_id' => 99999999]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertTagDidNotMove();
    }

    public function test_a_snapshot_that_cannot_be_persisted_reopens_the_picker(): void
    {
        // Make the state directory unwritable so the snapshot cannot be persisted. The lock file
        // is pre-created because acquiring the lock must still succeed — the failure under test
        // is the state write, not the lock.
        $dir = Storage::disk('local')->path('migrate-tag');
        @mkdir($dir, 0777, true);
        touch($dir . '/other--plastic_bag.lock');
        chmod($dir, 0555);

        $this->applyReady();

        try {
            $this->migrate(['--apply' => true])->assertExitCode(1);
        } finally {
            chmod($dir, 0777);
        }

        $this->retired->refresh();

        $this->assertNull($this->retired->retired_at);
        $this->assertTagDidNotMove();
    }

    public function test_there_is_no_bulk_apply(): void
    {
        $signature = (new \ReflectionClass(\App\Console\Commands\tmp\v5\PostMigAug2026\MigrateTag::class))
            ->getDefaultProperties()['signature'];

        $this->assertStringNotContainsString('--all', $signature);
        $this->assertStringNotContainsString('bulk', $signature);
    }

    // ── Unimplemented scopes are refused, not half-applied ──────────────────

    public function test_type_expansions_are_refused(): void
    {
        $type = LitterObjectType::factory()->create();
        $this->tag->update(['litter_object_type_id' => $type->id]);

        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(1);
        $this->assertTagDidNotMove();
    }

    public function test_object_tagged_across_several_categories_is_refused(): void
    {
        $other = Category::where('key', '!=', 'other')->firstOrFail();

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $other->id,
            'litter_object_id' => $this->retired->id,
            'quantity' => 1,
        ]);

        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(1);
    }

    /**
     * XP is weighted by object key. bags_litter is worth 10 and bagsLitter 1, so retiring one
     * for the other silently moves user scores unless the owner has accepted it.
     */
    public function test_xp_weight_change_is_refused(): void
    {
        $bagsLitter = LitterObject::firstOrCreate(['key' => 'bags_litter']);
        $camel = LitterObject::firstOrCreate(['key' => 'bagsLitter'], ['crowdsourced' => true]);

        CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => $bagsLitter->id,
        ]);

        $this->tag->update(['litter_object_id' => $bagsLitter->id, 'category_litter_object_id' => null]);

        // 'accepted' was the old override. It no longer exists — the refusal is unconditional.
        $this->writeQueue('DRY_RUN_VERIFIED', [
            'retired_key' => 'bags_litter', 'retired_id' => $bagsLitter->id,
            'desired_key' => 'bagsLitter', 'desired_id' => $camel->id,
            'xp_equivalent' => 'accepted',
        ]);

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $bagsLitter->id,
        ]);
    }

    // ── Apply ────────────────────────────────────────────────────────────────

    public function test_apply_repoints_the_row_and_preserves_everything_else(): void
    {
        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $row = DB::table('photo_tags')->where('id', $this->tag->id)->first();

        $this->assertSame($this->desired->id, (int) $row->litter_object_id);
        $this->assertSame($this->category->id, (int) $row->category_id);
        $this->assertSame(7, (int) $row->quantity);
        $this->assertNull($row->litter_object_type_id);
    }

    public function test_apply_prints_a_migration_overview(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id, 'processed_at' => now()]);

        $this->applyReady();

        $this->migrate(['--apply' => true])
            ->expectsOutputToContain('Overview')
            ->expectsOutputToContain('tags migrated: 1 (7 items)')
            ->expectsOutputToContain('photos affected: 1')
            ->expectsOutputToContain('users affected: 1')
            ->expectsOutputToContain('countries affected: 1')
            ->expectsOutputToContain('sample photo IDs: ' . $this->photo->id)
            ->assertExitCode(0);
    }

    // ── Photo-ID sample ──────────────────────────────────────────────────────

    public function test_photo_id_sample_is_empty_for_no_photos(): void
    {
        $this->assertSame([], $this->samplePhotoIds([]));
    }

    public function test_photo_id_sample_returns_every_id_sorted_for_five_or_fewer(): void
    {
        $this->assertSame([1, 2, 3], $this->samplePhotoIds([3, 1, 2, 1]));
        $this->assertSame([1, 2, 3, 4, 5], $this->samplePhotoIds([5, 4, 3, 2, 1]));
    }

    public function test_photo_id_sample_is_five_evenly_spaced_ids_for_larger_sets(): void
    {
        $ids = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

        $sample = $this->samplePhotoIds($ids);

        // Indices round(i·9/4) for i=0..4 → 0,2,5,7,9.
        $this->assertSame([10, 30, 60, 80, 100], $sample);
        $this->assertSame(10, $sample[0], 'The minimum is always included.');
        $this->assertSame(100, end($sample), 'The maximum is always included.');
    }

    public function test_photo_id_sample_is_order_independent_so_dry_run_and_apply_agree(): void
    {
        $ids = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

        $this->assertSame(
            $this->samplePhotoIds($ids),
            $this->samplePhotoIds(array_reverse($ids)),
            'A dry run reads photo_tags in DB order and an apply reads the snapshot; the sort must make both agree.'
        );
    }

    public function test_apply_creates_the_surviving_pivot(): void
    {
        $this->assertDatabaseMissing('category_litter_object', [
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);

        $this->applyOk();

        $this->assertDatabaseHas('category_litter_object', [
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);
    }

    public function test_picker_is_closed_before_data_moves(): void
    {
        $this->applyOk();

        $this->retired->refresh();

        $this->assertNotNull($this->retired->retired_at);
        $this->assertSame($this->desired->id, (int) $this->retired->merged_into_id);
    }

    /**
     * The seeder `firstOrCreate`s an object AND its pivot from `TagsConfig`, so re-running it
     * after a retirement would make the retired key selectable again. Removing the key from
     * `TagsConfig` (step B.4) is the intended fix, but that is a hand edit on a separate commit —
     * a stale config must not be able to silently undo a completed retirement.
     */
    public function test_reseeding_does_not_resurrect_a_retired_object(): void
    {
        // Retire a key the seeder actually reaches. `plastic_bag` is already out of TagsConfig,
        // so it would pass vacuously; `plasticBags` is in it, and stands in for any future entry
        // whose B.4 config edit has not landed yet.
        $this->desired->update(['retired_at' => now()]);

        $this->seed(GenerateTagsSeeder::class);

        $this->assertDatabaseMissing('category_litter_object', [
            'litter_object_id' => $this->desired->id,
        ]);

        $keys = collect($this->getJson('/api/tags/all')->assertOk()->json('objects') ?? [])->pluck('key');
        $this->assertNotContains('plasticBags', $keys);
    }

    public function test_retired_object_is_excluded_from_the_tag_picker(): void
    {
        $this->applyOk();

        $response = $this->getJson('/api/tags/all')->assertOk();
        $keys = collect($response->json('objects') ?? $response->json('litterObjects') ?? [])->pluck('key');

        $this->assertNotContains('plastic_bag', $keys);
        $this->assertContains('plasticBags', $keys);
    }

    public function test_a_stale_retired_clo_is_written_as_the_survivor(): void
    {
        $this->retired->update(['retired_at' => now(), 'merged_into_id' => $this->desired->id]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [[
                    'category_litter_object_id' => $this->retiredClo->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'litter_object_id' => $this->desired->id,
        ]);
        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
            'litter_object_id' => $this->retired->id,
        ]);
    }

    public function test_legacy_payload_of_a_retired_object_is_written_as_the_survivor(): void
    {
        $this->retired->update(['retired_at' => now(), 'merged_into_id' => $this->desired->id]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [[
                    'category' => 'other',
                    'object' => 'plastic_bag',
                    'quantity' => 1,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'litter_object_id' => $this->desired->id,
        ]);
    }

    public function test_a_retired_object_with_no_survivor_is_still_refused(): void
    {
        $this->retired->update(['retired_at' => now(), 'merged_into_id' => null]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [[
                    'category_litter_object_id' => $this->retiredClo->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('photo_tags')->where('photo_id', $photo->id)->count());
    }

    public function test_retired_object_is_excluded_from_the_grouped_tag_endpoint(): void
    {
        $this->retired->update(['retired_at' => now(), 'merged_into_id' => $this->desired->id]);

        $response = $this->getJson('/api/tags')->assertOk();

        $keys = collect($response->json('tags'))
            ->flatMap(fn ($category) => collect($category['litter_objects'] ?? [])->pluck('key'))
            ->all();

        $this->assertNotContains('plastic_bag', $keys);
    }

    public function test_retired_pivot_is_kept_once_drained(): void
    {
        $this->applyOk();

        $this->assertDatabaseHas('category_litter_object', ['id' => $this->retiredClo->id]);
        $this->assertDatabaseMissing('photo_tags', [
            'category_litter_object_id' => $this->retiredClo->id,
        ]);
    }

    public function test_summary_reflects_the_surviving_object(): void
    {
        $this->applyOk();

        $summary = $this->photo->fresh()->summary;
        $summary = is_string($summary) ? json_decode($summary, true) : $summary;
        $objectKeys = array_map('intval', array_keys($summary['keys']['objects'] ?? []));

        $this->assertContains($this->desired->id, $objectKeys);
        $this->assertNotContains($this->retired->id, $objectKeys);
    }

    public function test_xp_does_not_move(): void
    {
        $before = (int) $this->photo->fresh()->xp;

        $this->applyOk();

        $this->assertSame($before, (int) $this->photo->fresh()->xp);
    }

    public function test_colliding_rows_are_never_merged(): void
    {
        $existing = PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
            'quantity' => 3,
        ]);

        // Settle the photo with BOTH tags present, so the XP baseline the command captures
        // reflects the collision rather than being changed by it.
        $this->photo->generateSummary();
        $this->photo->refresh();

        $this->applyOk();

        $this->assertSame(2, DB::table('photo_tags')
            ->where('photo_id', $this->photo->id)
            ->where('litter_object_id', $this->desired->id)
            ->count());

        $this->assertSame(3, (int) DB::table('photo_tags')->where('id', $existing->id)->value('quantity'));
        $this->assertSame(7, (int) DB::table('photo_tags')->where('id', $this->tag->id)->value('quantity'));
    }

    // ── Quick tags ───────────────────────────────────────────────────────────

    /**
     * Restored. It was deleted on the reasoning that a pivotless source cannot hold a stored
     * CLO reference — true in the pre-D-4 direction, false in this one. 128 production rows
     * sit on the retired pivot.
     */
    public function test_quick_tags_are_repointed_off_the_retired_pivot(): void
    {
        $this->insertQuickTag($this->retiredClo->id);

        $this->applyOk();

        $newClo = DB::table('category_litter_object')
            ->where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->value('id');

        $this->assertSame(0, DB::table('user_quick_tags')->where('clo_id', $this->retiredClo->id)->count());
        $this->assertSame(1, DB::table('user_quick_tags')->where('clo_id', $newClo)->count());
    }

    public function test_quick_tags_in_other_categories_are_untouched(): void
    {
        $otherClo = CategoryObject::where('litter_object_id', '!=', $this->retired->id)->firstOrFail();

        $this->insertQuickTag($otherClo->id);

        $this->applyOk();

        $this->assertSame(1, DB::table('user_quick_tags')->where('clo_id', $otherClo->id)->count());
    }

    /**
     * There is no unique constraint on (user, clo, type) — the old collision-delete protected a
     * constraint that does not exist, and destroyed presets that differ only in quantity,
     * pickup state, materials, brands, custom name or sort order.
     */
    public function test_presets_on_both_sides_of_the_move_all_survive(): void
    {
        $desiredClo = CategoryObject::create([
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
        ]);

        $user = User::factory()->create();
        $onTarget = $this->insertQuickTagFor($user->id, $desiredClo->id, ['quantity' => 4]);
        $onRetired = $this->insertQuickTagFor($user->id, $this->retiredClo->id, ['quantity' => 9]);

        $this->applyOk();

        $this->assertSame($desiredClo->id, (int) DB::table('user_quick_tags')->where('id', $onTarget)->value('clo_id'));
        $this->assertSame($desiredClo->id, (int) DB::table('user_quick_tags')->where('id', $onRetired)->value('clo_id'));
        $this->assertSame(9, (int) DB::table('user_quick_tags')->where('id', $onRetired)->value('quantity'));
    }

    public function test_identical_duplicate_presets_both_survive(): void
    {
        $user = User::factory()->create();
        $first = $this->insertQuickTagFor($user->id, $this->retiredClo->id);
        $second = $this->insertQuickTagFor($user->id, $this->retiredClo->id);

        $this->applyOk();

        $newClo = (int) DB::table('category_litter_object')
            ->where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->value('id');

        $this->assertSame($newClo, (int) DB::table('user_quick_tags')->where('id', $first)->value('clo_id'));
        $this->assertSame($newClo, (int) DB::table('user_quick_tags')->where('id', $second)->value('clo_id'));
    }

    /**
     * A row that appears on the retired CLO after the snapshot was taken is outside the captured
     * set. It must still be drained, or the retired pivot can never be dropped.
     */
    public function test_a_late_quick_tag_is_repointed_on_resume(): void
    {
        $this->applyReady();

        Storage::disk('local')->put('migrate-tag/other--plastic_bag.json', json_encode([
            'version' => 4,
            'entry_id' => 'other--plastic_bag',
            'mapping_fingerprint' => $this->mappingFingerprint(),
            'baseline_items' => 7,
            'baseline_xp' => (int) $this->photo->fresh()->xp,
            'retired_object_id' => $this->retired->id,
            'category_id' => $this->category->id,
            'tag_ids' => [$this->tag->id],
            'all_photo_ids' => [$this->photo->id],
            'pending_photo_ids' => [$this->photo->id],
            'quick_tag_ids' => [],
        ]));

        $late = $this->insertQuickTagFor(User::factory()->create()->id, $this->retiredClo->id);

        $this->migrate(['--apply' => true])->assertExitCode(0);

        $newClo = (int) DB::table('category_litter_object')
            ->where('category_id', $this->category->id)
            ->where('litter_object_id', $this->desired->id)
            ->value('id');

        $this->assertSame($newClo, (int) DB::table('user_quick_tags')->where('id', $late)->value('clo_id'));
    }

    // ── Verify ───────────────────────────────────────────────────────────────

    public function test_verify_passes_after_a_clean_apply(): void
    {
        $this->applyOk();

        $this->migrate(['--verify' => true])->assertExitCode(0);
    }

    public function test_verify_fails_when_a_row_remains_on_the_retired_object(): void
    {
        $this->applyOk();

        PhotoTag::create([
            'photo_id' => $this->photo->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->retired->id,
            'quantity' => 1,
        ]);

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    /**
     * Dropping the retired pivot cascades to user_quick_tags, so a run that forgot to repoint
     * would silently destroy saved presets rather than leave them behind. Verification has to
     * assert survival on the surviving pivot, not absence on the retired one.
     */
    public function test_verify_fails_when_quick_tags_did_not_survive_the_repoint(): void
    {
        $this->insertQuickTag($this->retiredClo->id);

        $this->applyOk();

        DB::table('user_quick_tags')->delete();
        $this->writeQueue('LOCAL_APPLIED', ['quick_tags_on_retired_clo' => 1]);

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    /**
     * Reconciliation must actually READ the per-country and per-user hashes, not just the global
     * one. Caught on the rehearsal database: a grouped `pluck()` re-selects only the columns it
     * is handed, discarding the selectRaw aliases, and returned 0 where the real figure was 951.
     * Every test passed through that bug because no fixture photo carried a country.
     */
    public function test_verify_reconciles_country_and_user_scopes(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id, 'processed_at' => now()]);

        $this->applyOk();

        $this->migrate(['--verify' => true])->assertExitCode(0);

        // Corrupt ONLY the country hash. A global-scope check would not notice this.
        Redis::hset(
            RedisKeys::objects(RedisKeys::country($country->id)),
            (string) $this->desired->id,
            9999
        );

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_a_lingering_clo_reference_is_remounted_onto_the_survivor(): void
    {
        // Object already at the survivor, CLO still names the retired pivot —
        // the object-keyed move misses this. Park it on a photo outside the
        // snapshot so items/XP of the approved set stay still.
        $other = Photo::factory()->create(['user_id' => $this->photo->user_id]);
        $lingering = PhotoTag::create([
            'photo_id' => $other->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->desired->id,
            'category_litter_object_id' => $this->retiredClo->id,
            'quantity' => 1,
        ]);

        $this->applyOk();

        $this->assertDatabaseMissing('photo_tags', [
            'id' => $lingering->id,
            'category_litter_object_id' => $this->retiredClo->id,
        ]);
        $this->assertDatabaseHas('category_litter_object', ['id' => $this->retiredClo->id]);
    }

    public function test_verify_fails_when_the_object_is_not_marked_retired(): void
    {
        $this->applyOk();

        $this->retired->update(['retired_at' => null, 'merged_into_id' => null]);

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_verify_fails_when_the_retirement_points_at_the_wrong_survivor(): void
    {
        $this->applyOk();

        $elsewhere = LitterObject::firstOrCreate(['key' => 'somewhere_else'], ['crowdsourced' => false]);
        $this->retired->update(['merged_into_id' => $elsewhere->id]);

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_verify_fails_when_the_surviving_object_is_itself_retired(): void
    {
        $this->applyOk();

        $this->desired->update(['retired_at' => now()]);

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_verify_fails_when_the_retired_pivot_is_missing(): void
    {
        $this->applyOk();

        CategoryObject::where('id', $this->retiredClo->id)->delete();

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_verify_fails_when_the_retired_object_lingers_in_a_location_scope(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id, 'processed_at' => now()]);

        $this->applyOk();

        // Draining MySQL removes the retired object from every derived expectation, so a
        // MySQL-derived check alone can no longer see a stale country hash.
        Redis::hset(
            RedisKeys::objects(RedisKeys::country($country->id)),
            (string) $this->retired->id,
            4
        );

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    /**
     * `RedisMetricsCollector::updateTags()` writes each object count to a `:obj` hash AND a
     * `rank:objects` ZSET, and `LocationService::getTopTags()` reads the ZSET as its fast path —
     * the hash is only the fallback. A reconciliation that reads hashes alone therefore passes
     * while the surface users actually see is wrong.
     */
    public function test_verify_fails_when_the_survivor_ranking_zset_disagrees(): void
    {
        $this->applyOk();

        Redis::zAdd(
            RedisKeys::ranking(RedisKeys::global(), 'objects'),
            9999,
            (string) $this->desired->id
        );

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_verify_fails_when_the_retired_object_lingers_in_a_ranking_zset(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id, 'processed_at' => now()]);

        $this->applyOk();

        Redis::zAdd(
            RedisKeys::ranking(RedisKeys::country($country->id), 'objects'),
            4,
            (string) $this->retired->id
        );

        $this->migrate(['--verify' => true])->assertExitCode(1);
    }

    public function test_a_redis_mismatch_fails_the_apply_and_keeps_the_snapshot(): void
    {
        Redis::hset(RedisKeys::objects(RedisKeys::global()), (string) $this->desired->id, 12345);

        $this->applyReady();

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertTrue(Storage::disk('local')->exists('migrate-tag/other--plastic_bag.json'));
    }

    /**
     * The whole recovery path, end to end, from a mismatch raised DURING an apply — the only
     * state that leaves a snapshot behind.
     *
     * `--repair-redis` fixes Redis but does not finish the retirement: `clearState()` is reached
     * only by a successful `--apply`, so a repair-then-verify would pass every assertion while
     * leaving the entry looking mid-run to the next operator. The rerun is what clears it.
     *
     * Deliberately NOT the same scenario as `test_repair_redis_rewrites_the_objects_dimension...`
     * below, which corrupts Redis *after* a clean apply and so never has a snapshot to clear.
     */
    public function test_a_redis_mismatch_recovers_through_repair_then_rerun_then_verify(): void
    {
        $snapshot = 'migrate-tag/other--plastic_bag.json';

        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id, 'processed_at' => now()]);

        // A metrics write the collector swallowed: Redis carries a count MySQL cannot justify,
        // and the end-of-run reconciliation is the first thing to notice.
        Redis::hset(RedisKeys::objects(RedisKeys::global()), (string) $this->desired->id, 12345);

        $this->applyReady();

        // 1. The apply moves MySQL, then fails on the mismatch and retains the snapshot.
        $this->migrate(['--apply' => true])->assertExitCode(1);
        $this->assertTrue(Storage::disk('local')->exists($snapshot));
        $this->assertDatabaseHas('photo_tags', [
            'id' => $this->tag->id,
            'litter_object_id' => $this->desired->id,
        ]);

        // 2. The repair succeeds — and leaves the snapshot exactly where it was.
        $this->migrate(['--repair-redis' => true])->assertExitCode(0);
        $this->assertTrue(Storage::disk('local')->exists($snapshot));

        // 3. Rerunning the apply is the step that finishes the run. Every MySQL stage is
        //    idempotent: the rows are already at the target.
        $this->migrate(['--apply' => true])->assertExitCode(0);
        $this->assertFalse(Storage::disk('local')->exists($snapshot));

        // 4. Only now does verify mean the retirement is complete.
        $this->migrate(['--verify' => true])->assertExitCode(0);
    }

    /**
     * `RedisMetricsCollector::processPhoto()` logs and swallows every Redis error, so a run
     * against a dead Redis would repoint 10,051 rows in MySQL while silently discarding every
     * matching metrics write — and `MetricsService` will not produce those deltas a second time
     * once `processed_fp` has advanced. Refusing at the door is the only cheap remedy.
     */
    public function test_apply_refuses_to_start_when_redis_is_unreachable(): void
    {
        $this->applyReady();

        $this->withUnreachableRedis(function (): void {
            $this->migrate(['--apply' => true])->assertExitCode(1);
        });

        // The exit code alone proves nothing — the end-of-run reconciliation already fails on an
        // unreadable Redis. What the preflight adds is that it fails having moved NOTHING.
        $this->assertNull($this->retired->fresh()->retired_at);
        $this->assertTagDidNotMove();
    }

    /**
     * A retirement moves nothing but the objects dimension — quantities, category and XP are all
     * held equal by `supportedScope()`, so the litter and XP deltas are zero and no stats hash,
     * HLL or leaderboard ZSET is touched. That makes the lost writes exactly recoverable from
     * MySQL, without the global `olm:redis:rebuild` that §8a says must not run on production.
     */
    public function test_repair_redis_rewrites_the_objects_dimension_from_mysql(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id, 'processed_at' => now()]);

        $this->applyOk();
        $this->migrate(['--verify' => true])->assertExitCode(0);

        // The state a swallowed Redis error leaves behind: the survivor never credited, the
        // retired object never drained.
        $scope = RedisKeys::country($country->id);
        Redis::hset(RedisKeys::objects($scope), (string) $this->desired->id, 0);
        Redis::zAdd(RedisKeys::ranking($scope, 'objects'), 0, (string) $this->desired->id);
        Redis::hset(RedisKeys::objects($scope), (string) $this->retired->id, 7);
        Redis::zAdd(RedisKeys::ranking($scope, 'objects'), 7, (string) $this->retired->id);

        $this->migrate(['--verify' => true])->assertExitCode(1);

        $this->migrate(['--repair-redis' => true])->assertExitCode(0);

        $this->migrate(['--verify' => true])->assertExitCode(0);
    }

    /**
     * The production shape: the photo was already processed while its tag sat on the retired
     * object, so `processed_tags` names 92 and `MetricsService` must take its delta path —
     * moving the count to 149 rather than crediting 149 from empty and leaving 92 behind.
     * Most apply tests leave `processed_at` null, so the delta path never runs in them.
     */
    public function test_apply_moves_metrics_for_a_photo_already_processed_on_the_retired_object(): void
    {
        $country = Country::factory()->create();
        $this->photo->update(['country_id' => $country->id]);

        app(MetricsService::class)->processPhoto($this->photo->fresh());

        $scope = RedisKeys::country($country->id);
        $this->assertSame(7, (int) Redis::hGet(RedisKeys::objects($scope), (string) $this->retired->id));
        $litterBefore = (int) Redis::hGet(RedisKeys::stats($scope), 'litter');

        $this->applyOk();

        $this->assertSame(7, (int) Redis::hGet(RedisKeys::objects($scope), (string) $this->desired->id));
        $this->assertSame(0, (int) Redis::hGet(RedisKeys::objects($scope), (string) $this->retired->id));

        // A retirement moves a count between objects; it must not change the litter total.
        $this->assertSame($litterBefore, (int) Redis::hGet(RedisKeys::stats($scope), 'litter'));

        $this->migrate(['--verify' => true])->assertExitCode(0);
    }

    /**
     * `repointRows()` moves every row before the per-photo metrics loop, so mid-loop MySQL reads
     * as fully retired while the pending photos' `processed_tags` still name the retired object.
     * A repair there writes final counts the apply rerun would then add to again.
     */
    public function test_repair_redis_refuses_while_photos_are_pending_metrics(): void
    {
        $this->applyReady();

        Storage::disk('local')->put('migrate-tag/other--plastic_bag.json', json_encode([
            'version' => 4,
            'entry_id' => 'other--plastic_bag',
            'mapping_fingerprint' => $this->mappingFingerprint(),
            'baseline_items' => 7,
            'baseline_xp' => 0,
            'retired_object_id' => $this->retired->id,
            'category_id' => $this->category->id,
            'tag_ids' => [$this->tag->id],
            'all_photo_ids' => [$this->photo->id],
            'pending_photo_ids' => [$this->photo->id],
            'quick_tag_ids' => [],
        ]));

        $this->migrate(['--repair-redis' => true])->assertExitCode(1);
    }

    /**
     * Zero and absent both reconcile, but only absence keeps a dead key out of the ranking ZSET
     * that `getTopTags()` reads — in a small city scope a zero-scored member is still a member.
     */
    public function test_repair_redis_erases_the_retired_object_rather_than_zeroing_it(): void
    {
        $this->applyOk();

        $global = RedisKeys::global();
        Redis::hset(RedisKeys::objects($global), (string) $this->retired->id, 7);
        Redis::zAdd(RedisKeys::ranking($global, 'objects'), 7, (string) $this->retired->id);

        $this->migrate(['--repair-redis' => true])->assertExitCode(0);

        $this->assertNotContains(
            (string) $this->retired->id,
            Redis::hkeys(RedisKeys::objects($global))
        );
        $this->assertNotContains(
            (string) $this->retired->id,
            Redis::zrange(RedisKeys::ranking($global, 'objects'), 0, -1)
        );
    }

    /**
     * `olm:fix-orphaned-tags` encodes the pre-D-4 direction (149 → 92) plus other unapproved
     * taxonomy moves. Under D-4 it would move data backwards onto a retired object.
     */
    public function test_the_obsolete_orphan_fix_command_refuses_to_run(): void
    {
        $this->artisan('olm:fix-orphaned-tags', ['--apply' => true])->assertExitCode(1);

        $this->assertTagDidNotMove();
    }

    public function test_complete_is_refused_when_verification_fails(): void
    {
        $this->writeQueue('PRODUCTION_VERIFIED');

        $this->migrate([
            '--advance' => 'COMPLETE',
            '--by' => 'sean',
        ])->assertExitCode(1);
    }

    // ── Resumability ─────────────────────────────────────────────────────────

    public function test_rerun_after_completion_is_a_clean_no_op(): void
    {
        $this->applyOk();

        // The retired object is drained, so the expectation check no longer matches.
        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertSame(1, DB::table('photo_tags')
            ->where('id', $this->tag->id)
            ->where('litter_object_id', $this->desired->id)
            ->count());
    }

    public function test_editing_the_entry_blocks_a_resume(): void
    {
        $this->applyReady();

        Storage::disk('local')->put('migrate-tag/other--plastic_bag.json', json_encode([
            'version' => 4,
            'entry_id' => 'other--plastic_bag',
            'mapping_fingerprint' => 'stale-fingerprint',
            'baseline_items' => 7,
            'baseline_xp' => 0,
            'retired_object_id' => $this->retired->id,
            'category_id' => $this->category->id,
            'tag_ids' => [$this->tag->id],
            'all_photo_ids' => [$this->photo->id],
            'pending_photo_ids' => [$this->photo->id],
            'quick_tag_ids' => [],
        ]));

        $this->migrate(['--apply' => true])->assertExitCode(1);

        $this->assertTagDidNotMove();
    }
}
