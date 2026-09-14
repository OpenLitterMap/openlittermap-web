<?php

namespace Tests\Feature\Tags;

use App\Console\Commands\Tags\MigrateTag;
use App\Exports\CreateCSVExport;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use App\Models\Users\UserQuickTag;
use App\Services\Metrics\MetricsService;
use App\Services\Redis\RedisKeys;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MigrateTagTest extends TestCase
{
    private Category $category;
    private LitterObject $old;
    private LitterObject $new;
    private CategoryObject $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::factory()->create(['key' => 'other']);
        $this->old = LitterObject::factory()->create(['key' => 'plasticBags']);
        $this->new = LitterObject::factory()->create(['key' => 'plastic_bag']);
        $this->destination = CategoryObject::create(['category_id' => $this->category->id, 'litter_object_id' => $this->new->id]);
    }

    private function observation(?Photo $photo = null, array $attributes = []): PhotoTag
    {
        return PhotoTag::create(array_merge([
            'photo_id' => ($photo ?? Photo::factory()->create())->id,
            'category_id' => $this->category->id,
            'litter_object_id' => $this->old->id,
            'quantity' => 2,
            'picked_up' => null,
            'created_at' => '2020-01-02 12:00:00',
            'updated_at' => '2020-01-03 12:00:00',
        ], $attributes))->fresh();
    }

    private function migrate(array $options = [], int $exit = 0): void
    {
        $this->artisan('olm:migrate-tag', array_merge(['old' => $this->old->key, 'new' => $this->new->key, '--apply' => true], $options))
            ->assertExitCode($exit);
    }

    private function oldClo(): CategoryObject
    {
        return CategoryObject::create(['category_id' => $this->category->id, 'litter_object_id' => $this->old->id]);
    }

    private function allowType(CategoryObject $clo, string $key): LitterObjectType
    {
        $type = LitterObjectType::factory()->create(['key' => $key]);
        $clo->types()->attach($type->id);
        return $type;
    }

    public function test_missing_retirement_schema_is_refused_even_in_preview(): void
    {
        $schema = \Mockery::mock(\Illuminate\Support\Facades\Schema::getFacadeRoot());
        \Illuminate\Support\Facades\Schema::swap($schema);
        $schema->shouldReceive('hasColumns')
            ->with('litter_objects', ['retired_at', 'merged_into_id', 'merged_into_type_id'])
            ->once()->andReturn(false);
        $this->artisan('olm:migrate-tag', ['old' => 'plasticBags', 'new' => 'plastic_bag'])
            ->expectsOutput('Apply the pending object-retirement schema migrations first.')->assertFailed();
        $this->assertNull($this->old->fresh()->retired_at);
    }

    public function test_preview_changes_nothing_and_never_requires_a_source_clo(): void
    {
        $tag = $this->observation();
        $before = $tag->getRawOriginal();
        $this->artisan('olm:migrate-tag', ['old' => 'plasticBags', 'new' => 'plastic_bag'])
            ->expectsOutput('Rows: 1')->expectsOutput('Quantity: 2')->assertSuccessful();
        $this->assertSame($before, $tag->fresh()->getRawOriginal());
        $this->assertNull($this->old->fresh()->retired_at);
        $this->assertDatabaseCount('category_litter_object', 1);
        $this->assertNull($tag->photo->summary);
    }

    public function test_apply_preserves_observations_extras_totals_and_existing_new_tags(): void
    {
        $photo = Photo::factory()->create(['verified' => 0, 'is_public' => false, 'team_approved_at' => '2020-01-01 00:00:00']);
        $tags = collect([true, false, null])->map(fn ($status) => $this->observation($photo, ['picked_up' => $status]));
        $tag = $tags->first();
        $tag->attachExtraTags([['id' => BrandList::factory()->create()->id, 'quantity' => 3]], 'brand');
        $tag->attachExtraTags([['id' => Materials::factory()->create()->id]], 'material');
        $tag->attachExtraTags([['id' => CustomTagNew::factory()->create()->id]], 'custom_tag');
        $new = $this->observation($photo, ['litter_object_id' => $this->new->id, 'category_litter_object_id' => $this->destination->id, 'quantity' => 5]);
        $unresolved = LitterObject::factory()->create(['key' => 'randomLitter']);
        $other = $this->observation($photo, ['litter_object_id' => $unresolved->id]);
        $before = $tags->map(fn ($tag) => $tag->fresh()->getRawOriginal());
        $newBefore = $new->getRawOriginal();
        $otherBefore = $other->getRawOriginal();
        $extras = DB::table('photo_tag_extra_tags')->get()->toArray();
        app(GeneratePhotoSummaryService::class)->run($photo);
        app(MetricsService::class)->processPhoto($photo);
        $photo->refresh();
        $totals = $photo->summary['totals'];
        $xp = $photo->xp;
        $userXp = $photo->user->xp;
        $metricTotals = DB::table('metrics')->get()->map(fn ($r) => [$r->tags, $r->litter, $r->xp, $r->uploads])->all();
        $this->artisan('olm:migrate-tag', ['old' => 'plasticBags', 'new' => 'plastic_bag', '--apply' => true])
            ->expectsOutput('Photo-tag records migrated this run: 3')
            ->expectsOutput('Total quantity migrated this run: 6')
            ->expectsOutput('Photos with summaries regenerated this run: 1')
            ->expectsOutput('Saved quick tags repointed this run: 0')
            ->expectsOutput('Source photo-tag records remaining: 0')
            ->expectsOutput('Source quick tags remaining: 0')
            ->expectsOutput('Current plastic_bag totals (all categories/types, including existing records): 4 photo-tag records, 11 total quantity.')
            ->assertSuccessful();
        foreach ($tags as $index => $row) {
            $expected = $before[$index];
            $expected['litter_object_id'] = $this->new->id;
            $expected['category_litter_object_id'] = $this->destination->id;
            $this->assertSame($expected, $row->fresh()->getRawOriginal());
        }
        $this->assertSame($newBefore, $new->fresh()->getRawOriginal());
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
        $this->assertEquals($extras, DB::table('photo_tag_extra_tags')->get()->toArray());
        $photo->refresh();
        $this->assertSame($totals, $photo->summary['totals']);
        $this->assertEquals($xp, $photo->xp);
        $this->assertEquals($userXp, $photo->user->fresh()->xp);
        $this->assertEquals($metricTotals, DB::table('metrics')->get()->map(fn ($r) => [$r->tags, $r->litter, $r->xp, $r->uploads])->all());
        $this->assertEquals(0, $photo->verified->value);
        $this->assertFalse($photo->is_public);
        $this->assertEquals('2020-01-01 00:00:00', $photo->team_approved_at->format('Y-m-d H:i:s'));
        $this->assertArrayNotHasKey($this->old->id, $photo->summary['keys']['objects']);
        foreach (RedisKeys::getPhotoScopes($photo) as $scope) {
            $this->assertEquals(0, Redis::hget(RedisKeys::objects($scope), $this->old->id));
            $this->assertEquals(11, Redis::hget(RedisKeys::objects($scope), $this->new->id));
        }
        $this->assertEquals(11, Redis::hget(RedisKeys::user($photo->user_id).':tags', 'obj:'.$this->new->id));
        $this->assertEquals(0, Redis::hget(RedisKeys::user($photo->user_id).':tags', 'obj:'.$this->old->id));
        $this->assertDatabaseCount('category_litter_object', 1);
        $this->assertEquals($this->new->id, $this->old->fresh()->merged_into_id);
        $this->migrate();
        $this->assertEquals(11, Redis::hget(RedisKeys::objects(RedisKeys::global()), $this->new->id));
    }

    public function test_missing_destination_requires_explicit_creation_and_declaration(): void
    {
        $this->destination->delete();
        $this->observation();
        $this->migrate([], 1);
        $this->assertNull($this->old->fresh()->retired_at);
        $this->migrate(['--create-destination' => true, '--apply' => false]);
        $this->assertDatabaseCount('category_litter_object', 0);
        $this->migrate(['--create-destination' => true]);
        $this->assertDatabaseCount('category_litter_object', 1);
        $this->assertDatabaseHas('category_litter_object', ['litter_object_id' => $this->new->id, 'category_id' => $this->category->id]);
    }

    public function test_undeclared_destination_is_not_created(): void
    {
        $this->destination->delete();
        $this->new->update(['key' => 'unapproved_destination']);
        $this->observation();
        $this->migrate(['--create-destination' => true], 1);
        $this->assertDatabaseCount('category_litter_object', 0);
        $this->assertNull($this->old->fresh()->retired_at);
    }

    public function test_missing_category_and_already_typed_observations_are_refused(): void
    {
        $tag = $this->observation(null, ['category_id' => null]);
        $this->migrate([], 1);
        $type = $this->allowType($this->destination, 'energy');
        $tag->update(['category_id' => $this->category->id, 'litter_object_type_id' => $type->id]);
        $this->migrate([], 1);
        $this->assertNull($this->old->fresh()->retired_at);
        $this->assertEquals($this->old->id, $tag->fresh()->litter_object_id);
    }

    public function test_empty_source_clos_do_not_require_a_destination(): void
    {
        $empty = Category::factory()->create();
        CategoryObject::create(['category_id' => $empty->id, 'litter_object_id' => $this->old->id]);
        $tag = $this->observation();
        $this->migrate();
        $this->assertEquals($this->new->id, $tag->fresh()->litter_object_id);
        $this->assertDatabaseMissing('category_litter_object', ['category_id' => $empty->id, 'litter_object_id' => $this->new->id]);
    }

    public function test_incompatible_quick_tags_refuse_every_write(): void
    {
        $clo = $this->oldClo();
        $type = $this->allowType($clo, 'energy');
        $quick = UserQuickTag::create(['materials' => [], 'brands' => [], 'sort_order' => 0, 'user_id' => User::factory()->create()->id, 'clo_id' => $clo->id, 'type_id' => $type->id, 'quantity' => 4]);
        $tag = $this->observation();
        $this->migrate(['--apply' => false], 1);
        $this->migrate([], 1);
        $this->assertNull($this->old->fresh()->retired_at);
        $this->assertEquals($this->old->id, $tag->fresh()->litter_object_id);
        $this->assertEquals($clo->id, $quick->fresh()->clo_id);
    }

    public function test_energy_type_quick_tags_stale_requests_and_conflicting_retries(): void
    {
        $this->category->update(['key' => 'softdrinks']);
        $this->old->update(['key' => 'energy_can']);
        $this->new->update(['key' => 'can']);
        $type = $this->allowType($this->destination, 'energy');
        $wrong = $this->allowType($this->destination, 'water');
        $otherCategory = Category::factory()->create(['key' => 'alcohol']);
        CategoryObject::create(['category_id' => $otherCategory->id, 'litter_object_id' => $this->new->id]);
        $clo = $this->oldClo();
        $user = User::factory()->create();
        $quick = UserQuickTag::create(['materials' => [], 'brands' => [], 'sort_order' => 0, 'user_id' => $user->id, 'clo_id' => $clo->id, 'type_id' => $wrong->id, 'quantity' => 4, 'picked_up' => false, 'brands' => [['id' => 9, 'quantity' => 3]]]);
        $before = $quick->fresh()->getRawOriginal();
        $tag = $this->observation();
        app(GeneratePhotoSummaryService::class)->run($tag->photo);
        app(MetricsService::class)->processPhoto($tag->photo);
        $this->artisan('olm:migrate-tag', ['old' => $this->old->key, 'new' => $this->new->key, '--type' => 'energy', '--apply' => true])
            ->expectsOutput('Saved quick tags repointed this run: 1')
            ->assertSuccessful();
        $this->assertEquals(2, Redis::hget(RedisKeys::types(RedisKeys::global()), $type->id));
        $before['clo_id'] = $this->destination->id;
        $before['type_id'] = $type->id;
        $this->assertSame($before, $quick->fresh()->getRawOriginal());
        $this->assertEquals($type->id, $tag->fresh()->litter_object_type_id);
        $this->migrate(['--type' => 'water'], 1);
        $this->migrate([], 1);
        $this->migrate(['--type' => 'energy']);
        foreach ([['object' => 'energy_can'], ['category_litter_object_id' => $clo->id, 'litter_object_type_id' => $wrong->id]] as $input) {
            $photo = Photo::factory()->create(['user_id' => $user->id]);
            $this->actingAs($user)->postJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [array_merge($input, ['quantity' => 3])]])->assertOk();
            $this->assertDatabaseHas('photo_tags', ['photo_id' => $photo->id, 'litter_object_id' => $this->new->id, 'category_id' => $this->category->id, 'litter_object_type_id' => $type->id]);
        }
        $this->actingAs($user)->putJson('/api/v3/user/quick-tags', ['tags' => [['materials' => [], 'brands' => [], 'clo_id' => $clo->id, 'type_id' => $wrong->id, 'quantity' => 6]]])->assertOk();
        $this->assertEquals($type->id, $user->quickTags()->first()->type_id);
    }

    public function test_mixed_unresolved_photo_rejection_is_atomic(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $this->observation($photo);
        $unresolved = LitterObject::factory()->create(['key' => 'randomLitter']);
        $this->observation($photo, ['litter_object_id' => $unresolved->id]);
        $this->migrate();
        $before = $photo->fresh()->getRawOriginal();
        $rows = DB::table('photo_tags')->get()->toArray();
        $this->actingAs($user)->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [
            ['category_litter_object_id' => $this->destination->id, 'quantity' => 3],
            ['object' => 'randomLitter', 'category_id' => $this->category->id, 'quantity' => 2],
        ]])->assertUnprocessable();
        $this->assertSame($before, $photo->fresh()->getRawOriginal());
        $this->assertEquals($rows, DB::table('photo_tags')->get()->toArray());
    }

    public function test_explicit_category_is_preserved_or_rejected_without_deletion(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $tag = $this->observation($photo);
        $this->migrate();
        $wrong = Category::factory()->create();
        foreach ([$this->old->key, $this->new->key, 'unknown_object'] as $key) {
            $this->actingAs($user)->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [['object' => $key, 'category_id' => $wrong->id, 'quantity' => 3]]])->assertUnprocessable();
            $this->assertDatabaseHas('photo_tags', ['id' => $tag->id, 'quantity' => 2]);
        }
        $this->actingAs($user)->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [['object' => $this->old->key, 'category_id' => $this->category->id, 'quantity' => 3]]])->assertOk();
        $this->assertDatabaseHas('photo_tags', ['photo_id' => $photo->id, 'category_id' => $this->category->id, 'litter_object_id' => $this->new->id, 'quantity' => 3]);
    }

    public function test_unapproved_client_type_preserves_saved_tags_and_quick_tags(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $tag = $this->observation($photo);
        $clo = $this->oldClo();
        $type = $this->allowType($clo, 'wrong_type');
        $this->migrate();
        $quick = UserQuickTag::create(['materials' => [], 'brands' => [], 'sort_order' => 0, 'user_id' => $user->id, 'clo_id' => $this->destination->id, 'quantity' => 4]);
        $this->actingAs($user)->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [['category_litter_object_id' => $clo->id, 'litter_object_type_id' => $type->id, 'quantity' => 3]]])->assertUnprocessable();
        $this->assertDatabaseHas('photo_tags', ['id' => $tag->id, 'quantity' => 2]);
        $this->actingAs($user)->putJson('/api/v3/user/quick-tags', ['tags' => [['materials' => [], 'brands' => [], 'clo_id' => $clo->id, 'type_id' => $type->id, 'quantity' => 3]]])->assertUnprocessable();
        $this->assertDatabaseHas('user_quick_tags', ['id' => $quick->id, 'quantity' => 4]);
    }

    public function test_retired_objects_are_absent_from_catalogue_search_and_top_tags(): void
    {
        $user = User::factory()->create();
        $clo = $this->oldClo();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $this->observation($photo, ['category_litter_object_id' => $clo->id, 'quantity' => 3]);
        $this->old->update(['retired_at' => now(), 'merged_into_id' => $this->new->id]);
        $all = $this->getJson('/api/tags/all')->assertOk();
        $this->assertNotContains($this->old->id, array_column($all->json('objects'), 'id'));
        $this->assertNotContains($clo->id, array_column($all->json('category_objects'), 'id'));
        $this->getJson('/api/tags?search=plasticBags')->assertOk()->assertJsonCount(0, 'tags');
        $this->actingAs($user)->getJson('/api/v3/user/top-tags')->assertOk()->assertJsonCount(0, 'tags');
    }

    public function test_chain_and_matching_replay_after_a_second_migration(): void
    {
        $tag = $this->observation();
        $this->migrate();
        $third = LitterObject::factory()->create();
        $thirdClo = CategoryObject::create(['category_id' => $this->category->id, 'litter_object_id' => $third->id]);
        $this->migrate(['old' => $this->new->key, 'new' => $third->key]);
        $this->assertEquals($third->id, $tag->fresh()->litter_object_id);
        $this->migrate();
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->postJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [['object' => $this->old->key, 'quantity' => 3]]])->assertOk();
        $this->assertDatabaseHas('photo_tags', ['photo_id' => $photo->id, 'category_litter_object_id' => $thirdClo->id]);
    }

    public function test_unfinished_predecessor_blocks_next_migration(): void
    {
        $this->observation();
        $this->old->update(['retired_at' => now(), 'merged_into_id' => $this->new->id]);
        $third = LitterObject::factory()->create();
        $this->migrate(['old' => $this->new->key, 'new' => $third->key], 1);
        $this->assertNull($this->new->fresh()->retired_at);
        $this->artisan('olm:migrate-tag', ['old' => $this->old->key, 'new' => $this->new->key])
            ->expectsOutput('Resume: matching retirement is recorded; processing remaining rows.')->assertSuccessful();
        $this->migrate();
    }

    public function test_ownerless_and_soft_deleted_photos_are_migrated(): void
    {
        $photo = Photo::factory()->create(['user_id' => null]);
        $tag = $this->observation($photo);
        app(GeneratePhotoSummaryService::class)->run($photo);
        app(MetricsService::class)->processPhoto($photo);
        $deleted = Photo::factory()->create(['deleted_at' => now()]);
        $deletedTag = $this->observation($deleted);
        $this->migrate();
        $this->assertEquals($this->new->id, $tag->fresh()->litter_object_id);
        $this->assertEquals($this->new->id, $deletedTag->fresh()->litter_object_id);
        $this->assertArrayHasKey($this->new->id, Photo::withTrashed()->find($deleted->id)->summary['keys']['objects']);
        $this->assertFalse(DB::table('metrics')->where('user_id', '>', 0)->exists());
        $this->assertEmpty(Redis::zrange(RedisKeys::xpRanking(RedisKeys::global()), 0, -1));
    }

    public function test_school_photo_summary_changes_but_metrics_wait_for_approval(): void
    {
        $teacher = User::factory()->create();
        $student = User::factory()->create();
        $school = \App\Models\Teams\TeamType::factory()->create(['team' => 'school']);
        $team = \App\Models\Teams\Team::factory()->create(['type_id' => $school->id, 'leader' => $teacher->id, 'safeguarding' => true]);
        $team->users()->attach([$teacher->id, $student->id]);
        $photo = Photo::factory()->create(['user_id' => $student->id, 'team_id' => $team->id, 'verified' => 1, 'is_public' => false]);
        $this->observation($photo);
        $this->migrate();
        $photo->refresh();
        $this->assertArrayHasKey($this->new->id, $photo->summary['keys']['objects']);
        $this->assertNull($photo->processed_at);
        $this->assertNull($photo->team_approved_at);
        $this->assertEquals(1, $photo->verified->value);
        $this->assertFalse($photo->is_public);
        $this->assertDatabaseCount('metrics', 0);
        \Illuminate\Support\Facades\Event::fake([\App\Events\SchoolDataApproved::class]);
        $this->actingAs($teacher)->postJson('/api/teams/photos/approve', ['team_id' => $team->id, 'photo_ids' => [$photo->id]])->assertOk();
        $photo->refresh();
        $this->assertNotNull($photo->processed_at);
        $this->assertEquals(2, Redis::hget(RedisKeys::objects(RedisKeys::global()), $this->new->id));
        $this->assertEquals(0, Redis::hget(RedisKeys::objects(RedisKeys::global()), $this->old->id));
    }

    public function test_recorded_chain_type_is_validated_at_the_final_destination(): void
    {
        $type = $this->allowType($this->destination, 'energy');
        $this->old->update(['retired_at' => now(), 'merged_into_id' => $this->new->id, 'merged_into_type_id' => $type->id]);
        $final = LitterObject::factory()->create();
        $finalClo = CategoryObject::create(['category_id' => $this->category->id, 'litter_object_id' => $final->id]);
        $this->new->update(['retired_at' => now(), 'merged_into_id' => $final->id]);
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $payload = ['photo_id' => $photo->id, 'tags' => [['object' => $this->old->key, 'category_id' => $this->category->id, 'quantity' => 1]]];
        $this->actingAs($user)->postJson('/api/v3/tags', $payload)->assertUnprocessable();
        $replacementType = $this->allowType($finalClo, 'beer');
        $this->new->update(['merged_into_type_id' => $replacementType->id]);
        $this->postJson('/api/v3/tags', $payload)->assertOk();
        $this->assertDatabaseHas('photo_tags', ['photo_id' => $photo->id, 'litter_object_type_id' => $replacementType->id]);
    }

    public function test_cycles_missing_targets_and_ambiguous_replacements_are_rejected(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $this->old->update(['retired_at' => now(), 'merged_into_id' => $this->new->id]);
        $another = Category::factory()->create();
        CategoryObject::create(['category_id' => $another->id, 'litter_object_id' => $this->new->id]);
        $payload = ['photo_id' => $photo->id, 'tags' => [['object' => $this->old->key, 'quantity' => 1]]];
        $this->actingAs($user)->postJson('/api/v3/tags', $payload)->assertUnprocessable();
        $this->new->update(['retired_at' => now(), 'merged_into_id' => $this->old->id]);
        $this->postJson('/api/v3/tags', $payload)->assertUnprocessable();
        $this->new->update(['merged_into_id' => null]);
        $this->postJson('/api/v3/tags', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('photo_tags', 0);
    }

    public function test_creating_a_destination_lists_and_creates_only_declared_existing_types(): void
    {
        $this->destination->delete();
        $this->category->update(['key' => 'dumping']);
        $this->old->update(['key' => 'dumping_small']);
        $this->new->update(['key' => 'dumping']);
        $this->observation();
        $small = LitterObjectType::factory()->create(['key' => 'small']);
        $this->migrate(['--create-destination' => true, '--type' => 'small', '--allow-xp-change' => true], 1);
        $this->assertNull($this->old->fresh()->retired_at);
        LitterObjectType::factory()->create(['key' => 'medium']);
        LitterObjectType::factory()->create(['key' => 'large']);
        $options = ['old' => $this->old->key, 'new' => $this->new->key, '--create-destination' => true, '--type' => 'small', '--allow-xp-change' => true];
        $this->artisan('olm:migrate-tag', $options)
            ->expectsOutput("Would create category_object_types: new destination CLO, litter_object_type_id={$small->id}.")->assertSuccessful();
        $this->assertDatabaseCount('category_object_types', 0);
        $this->migrate($options);
        $this->assertDatabaseCount('category_object_types', 3);
        $this->assertDatabaseCount('taggables', 0);
        $this->assertDatabaseCount('litter_object_types', 3);
    }

    public function test_catalogue_creation_failure_rolls_back_the_retirement_transaction(): void
    {
        $this->destination->delete();
        $tag = $this->observation();
        \Illuminate\Support\Facades\Event::listen('eloquent.created: '.CategoryObject::class, function () {
            throw new \RuntimeException('Simulated catalogue write failure.');
        });
        $this->migrate(['--create-destination' => true], 1);
        $this->assertDatabaseCount('category_litter_object', 0);
        $this->assertNull($this->old->fresh()->retired_at);
        $this->assertEquals($this->old->id, $tag->fresh()->litter_object_id);
    }

    public function test_type_option_does_not_leak_to_the_next_command_in_the_same_process(): void
    {
        $type = $this->allowType($this->destination, 'energy');
        $this->observation();
        $this->migrate(['--type' => $type->key]);
        $old = LitterObject::factory()->create();
        $new = LitterObject::factory()->create();
        CategoryObject::create(['category_id' => $this->category->id, 'litter_object_id' => $new->id]);
        $tag = $this->observation(null, ['litter_object_id' => $old->id]);
        $this->migrate(['old' => $old->key, 'new' => $new->key]);
        $this->assertEquals($new->id, $tag->fresh()->litter_object_id);
        $this->assertNull($old->fresh()->merged_into_type_id);
        $this->assertNull($tag->fresh()->litter_object_type_id);
    }

    public function test_unknown_identical_and_retired_destinations_are_refused(): void
    {
        $this->observation();
        $this->migrate(['new' => 'unknown_destination'], 1);
        $this->migrate(['new' => $this->old->key], 1);
        $this->migrate(['--type' => 'unknown_type'], 1);
        $this->new->update(['retired_at' => now(), 'merged_into_id' => $this->old->id]);
        $this->migrate([], 1);
        $this->assertNull($this->old->fresh()->retired_at);
    }

    public function test_actual_wide_and_long_csv_use_replacement_keys(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id, 'verified' => 2, 'is_public' => true]);
        $this->observation($photo);
        $this->migrate();
        foreach (['wide', 'long'] as $format) {
            $csv = (new CreateCSVExport(null, null, null, $user->id, [], [], [], $format))->raw(\Maatwebsite\Excel\Excel::CSV);
            $this->assertStringContainsString('plastic_bag', $csv);
            $this->assertStringNotContainsString('plasticBags', $csv);
        }
    }

    public function test_another_connection_holding_the_lock_blocks_apply(): void
    {
        config(['database.connections.migration_lock_test' => config('database.connections.mysql')]);
        $connection = DB::connection('migration_lock_test');
        try {
            $this->assertEquals(1, $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [MigrateTag::LOCK_NAME])->acquired);
            $this->observation();
            $this->migrate([], 1);
            $this->assertNull($this->old->fresh()->retired_at);
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?)', [MigrateTag::LOCK_NAME]);
            DB::purge('migration_lock_test');
        }
    }
}
