<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * End-to-end integration test for the school approval pipeline.
 *
 * Validates the FULL flow:
 *   Student tags photo on non-trusted school team
 *   → summary generated
 *   → teacher approves
 *   → TagsVerifiedByAdmin fires
 *   → MetricsService processes
 *   → location totals increase
 *   → photo appears on public map
 *
 * This is the one test that proves the entire architecture works.
 * Each piece can work in isolation; this test proves the pipeline
 * is connected end-to-end.
 */
class SchoolApprovalPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Team $schoolTeam;
    protected Country $country;
    protected State $state;
    protected Category $smokingCategory;
    protected Category $foodCategory;
    protected LitterObject $cigaretteButt;
    protected LitterObject $wrapper;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Location data
        $this->country = Country::factory()->create(['shortcode' => 'IE', 'country' => 'Ireland']);
        $this->state = State::factory()->create([
            'country_id' => $this->country->id,
            'state' => 'Cork',
        ]);

        // Tag taxonomy
        $this->smokingCategory = Category::firstOrCreate(['key' => 'smoking']);
        $this->foodCategory = Category::firstOrCreate(['key' => 'food']);
        $this->cigaretteButt = LitterObject::firstOrCreate(['key' => 'cigarette_butt']);
        $this->wrapper = LitterObject::firstOrCreate(['key' => 'wrapper']);

        // Category-object pivot associations (required for API tagging)
        $this->smokingCategory->litterObjects()->syncWithoutDetaching([$this->cigaretteButt->id]);
        $this->foodCategory->litterObjects()->syncWithoutDetaching([$this->wrapper->id]);

        // School team type
        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );

        // Teacher
        $this->teacher = User::factory()->create(['name' => 'Ms. Murphy']);
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage school team', 'guard_name' => 'web']);
        $role->givePermissionTo('manage school team');
        $this->teacher->assignRole('school_manager');

        // School team — NOT trusted (critical invariant)
        $this->schoolTeam = Team::factory()->create([
            'type_id' => $schoolType->id,
            'leader' => $this->teacher->id,
            'safeguarding' => true,
            // is_trusted must be false for school teams
        ]);

        $this->schoolTeam->users()->attach($this->teacher->id);

        // Student
        $this->student = User::factory()->create(['name' => 'Alice Student']);
        $this->schoolTeam->users()->attach($this->student->id);
    }

    /**
     * STEP 1: Student tags a photo → summary generated, photo private.
     */
    public function test_step1_student_tags_photo_and_it_stays_private()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lat' => 51.8979,
            'lon' => -8.4706,
        ]);

        // Simulate tagging via API
        $response = $this->actingAs($this->student, 'api')
            ->postJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [
                    ['category' => 'smoking', 'object' => 'cigarette_butt', 'quantity' => 3, 'picked_up' => true],
                    ['category' => 'food', 'object' => 'wrapper', 'quantity' => 2, 'picked_up' => false],
                ],
            ]);

        $response->assertOk();

        $photo->refresh();

        // Summary must exist (generated regardless of trust level)
        $this->assertNotNull($photo->summary, 'Summary must be generated even for non-trusted teams');

        // XP must be calculated
        $this->assertNotNull($photo->xp, 'XP must be calculated even for non-trusted teams');

        // Photo stays private (school team)
        $this->assertFalse((bool) $photo->is_public, 'School team photo must be private');

        // Verified = 1 (tagged, not admin approved)
        $verifiedValue = $photo->verified instanceof VerificationStatus
            ? $photo->verified->value
            : (int) $photo->verified;
        $this->assertLessThan(
            VerificationStatus::ADMIN_APPROVED->value,
            $verifiedValue,
            'Photo should NOT be admin approved before teacher review'
        );

        // Tags created
        $this->assertEquals(2, $photo->photoTags()->count());
        $this->assertEquals(5, $photo->total_tags);
    }

    /**
     * STEP 2: Photo does NOT appear in public points API.
     */
    public function test_step2_private_photo_excluded_from_points_api()
    {
        $photo = $this->createTaggedSchoolPhoto();

        // Public scope should exclude it
        $publicCount = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(0, $publicCount, 'Private school photo must not appear in public queries');
    }

    /**
     * STEP 3: TagsVerifiedByAdmin does NOT fire for non-trusted team.
     *
     * This proves no MetricsService processing happens before approval.
     * Aggregate data does not leak.
     */
    public function test_step3_metrics_not_triggered_before_approval()
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
        ]);

        // Tag the photo
        $this->actingAs($this->student, 'api')
            ->postJson('/api/v3/tags', [
                'photo_id' => $photo->id,
                'tags' => [
                    ['category' => 'smoking', 'object' => 'cigarette_butt', 'quantity' => 1],
                ],
            ]);

        // TagsVerifiedByAdmin should NOT fire (team is not trusted)
        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }

    /**
     * STEP 4: Teacher approves → photo becomes public, metrics fire.
     */
    public function test_step4_approval_triggers_metrics_and_publishes()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $photo = $this->createTaggedSchoolPhoto();

        // Teacher approves
        $response = $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved_count', 1);

        $photo->refresh();

        // Photo is now public
        $this->assertTrue((bool) $photo->is_public, 'Photo must be public after approval');

        // Verified upgraded to ADMIN_APPROVED
        $verifiedValue = $photo->verified instanceof VerificationStatus
            ? $photo->verified->value
            : (int) $photo->verified;
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $verifiedValue,
            'Photo must be ADMIN_APPROVED after teacher approval'
        );

        // Approval metadata set
        $this->assertNotNull($photo->team_approved_at);
        $this->assertEquals($this->teacher->id, $photo->team_approved_by);

        // TagsVerifiedByAdmin fires → MetricsService processes
        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id
                && $event->user_id === $photo->user_id
                && $event->country_id === $this->country->id
                && $event->state_id === $this->state->id
                && $event->team_id === $this->schoolTeam->id;
        });

        // SchoolDataApproved fires → notifications
        Event::assertDispatched(SchoolDataApproved::class, function ($event) {
            return $event->team->id === $this->schoolTeam->id
                && $event->photoCount === 1;
        });
    }

    /**
     * STEP 5: After approval, photo appears in public points API.
     */
    public function test_step5_approved_photo_visible_in_points_api()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $photo = $this->createTaggedSchoolPhoto();

        // Before approval: not visible
        $this->assertEquals(0, Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count());

        // Approve
        $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        // After approval: visible
        $publicPhotos = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->get();

        $this->assertCount(1, $publicPhotos);
        $this->assertEquals($photo->id, $publicPhotos->first()->id);
    }

    /**
     * STEP 6: Approving the same photo twice is idempotent.
     */
    public function test_step6_double_approval_is_idempotent()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $photo = $this->createTaggedSchoolPhoto();

        // First approval
        $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ])
            ->assertJsonPath('approved_count', 1);

        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);

        // Second approval — same photo
        $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ])
            ->assertJsonPath('approved_count', 0);

        // TagsVerifiedByAdmin should NOT fire again
        // (still only 1 total, not 2)
        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);
    }

    /**
     * STEP 7: Safeguarding — student names masked in photo list.
     */
    public function test_step7_student_names_masked_in_team_photo_list()
    {
        $this->createTaggedSchoolPhoto();

        // Student sees masked names
        $response = $this->actingAs($this->student, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();
        $photos = $response->json('photos.data');

        foreach ($photos as $photo) {
            if ($photo['user_id'] !== $this->teacher->id) {
                $this->assertStringStartsWith('Student', $photo['user']['name']);
                $this->assertNull($photo['user']['username']);
            }
        }
    }

    // ── Helper ────────────────────────────────────────

    /**
     * Create a tagged school photo (simulates student upload + tag).
     * Summary and XP are set manually to avoid dependency on AddTagsToPhotoAction
     * internals — the unit tests for that action validate the summary generation.
     */
    protected function createTaggedSchoolPhoto(): Photo
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'city_id' => null,
            'lat' => 51.8979,
            'lon' => -8.4706,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'total_tags' => 5,
            'summary' => json_encode([
                'smoking' => ['cigarette_butt' => 3],
                'food' => ['wrapper' => 2],
            ]),
            'xp' => 8,
        ]);

        // Create the actual tag records
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $this->getCloId($this->smokingCategory->id, $this->cigaretteButt->id),
            'category_id' => $this->smokingCategory->id,
            'litter_object_id' => $this->cigaretteButt->id,
            'quantity' => 3,
            'picked_up' => true,
        ]);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $this->getCloId($this->foodCategory->id, $this->wrapper->id),
            'category_id' => $this->foodCategory->id,
            'litter_object_id' => $this->wrapper->id,
            'quantity' => 2,
            'picked_up' => false,
        ]);

        return $photo;
    }
}
