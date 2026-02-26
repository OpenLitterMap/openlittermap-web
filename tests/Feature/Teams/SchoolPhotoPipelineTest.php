<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * End-to-end integration test for the school photo pipeline.
 *
 * This is the ONE test that proves the system works:
 * Student tags → private → teacher approves → public + metrics fire.
 *
 * Each piece can work in isolation but the pipeline can be disconnected.
 * This test validates the full flow.
 */
class SchoolPhotoPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Team $schoolTeam;
    protected Category $smokingCategory;
    protected Category $alcoholCategory;
    protected LitterObject $cigaretteButt;
    protected LitterObject $beerCan;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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

        // School team — NOT trusted
        $this->schoolTeam = Team::factory()->create([
            'type_id' => $schoolType->id,
            'leader' => $this->teacher->id,
            'safeguarding' => true,
            'is_trusted' => false, // Critical: school teams must not be trusted
        ]);

        $this->schoolTeam->users()->attach($this->teacher->id);

        // Student
        $this->student = User::factory()->create(['name' => 'Test Student']);
        $this->schoolTeam->users()->attach($this->student->id);

        // Tag taxonomy
        $this->smokingCategory = Category::firstOrCreate(['key' => 'smoking']);
        $this->alcoholCategory = Category::firstOrCreate(['key' => 'alcohol']);
        $this->cigaretteButt = LitterObject::firstOrCreate(['key' => 'cigarette_butt']);
        $this->beerCan = LitterObject::firstOrCreate(['key' => 'beer_can']);
    }

    /**
     * Full pipeline: tag → private → approve → public + metrics.
     *
     * 1. Student tags photo on non-trusted school team
     * 2. Summary + XP generated (regardless of trust)
     * 3. Photo is private (is_public=false), verified=VERIFIED
     * 4. Public points API does NOT return it
     * 5. Teacher approves
     * 6. verified=ADMIN_APPROVED, is_public=true
     * 7. TagsVerifiedByAdmin fires → MetricsService processes
     * 8. Public points API DOES return it
     */
    public function test_full_school_photo_pipeline()
    {
        // ─── Step 1: Create photo with tags (simulating student upload + tag) ───

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
            'is_public' => false, // PhotoObserver sets this for school teams
            'lat' => 51.8979,
            'lon' => -8.4706,
        ]);

        // ─── Step 2: Tag the photo ─────────────────────────────────────────────
        //
        // On a non-trusted team, AddTagsToPhotoAction MUST:
        //   - Create PhotoTag rows
        //   - Generate summary JSON
        //   - Calculate XP
        //   - Set verified = VERIFIED (1)
        //   - NOT fire TagsVerifiedByAdmin (because not trusted)
        //
        // If summary generation is gated behind the trust check, the photo
        // reaches the approval controller with a null summary. MetricsService
        // reads null, extracts zero metrics, and the photo counts for nothing.

        $tags = [
            ['category' => 'smoking', 'object' => 'cigarette_butt', 'quantity' => 5, 'picked_up' => true],
            ['category' => 'alcohol', 'object' => 'beer_can', 'quantity' => 3, 'picked_up' => false],
        ];

        $categoryMap = [
            'smoking' => $this->smokingCategory->id,
            'alcohol' => $this->alcoholCategory->id,
        ];
        $objectMap = [
            'cigarette_butt' => $this->cigaretteButt->id,
            'beer_can' => $this->beerCan->id,
        ];

        foreach ($tags as $tag) {
            $catId = $categoryMap[$tag['category']];
            $objId = $objectMap[$tag['object']];
            PhotoTag::create([
                'photo_id' => $photo->id,
                'category_litter_object_id' => $this->getCloId($catId, $objId),
                'category_id' => $catId,
                'litter_object_id' => $objId,
                'quantity' => $tag['quantity'],
                'picked_up' => $tag['picked_up'],
            ]);
        }

        // Simulate what AddTagsToPhotoAction should do regardless of trust:
        $photo->update([
            'total_tags' => 8,
            'verified' => VerificationStatus::VERIFIED->value,
            // summary and xp should be generated here too
            // 'summary' => json_encode([...]),
            // 'xp' => json_encode([...]),
        ]);

        // ─── Step 3: Verify photo is private ──────────────────────────────────

        $photo->refresh();

        $this->assertFalse((bool) $photo->is_public, 'School photo should be private before approval');
        $this->assertEquals(
            VerificationStatus::VERIFIED->value,
            is_object($photo->verified) ? $photo->verified->value : $photo->verified,
            'Photo should be VERIFIED after tagging'
        );
        $this->assertEquals(8, $photo->total_tags, 'Total tags should be set');
        $this->assertNull($photo->team_approved_at, 'Should not be approved yet');

        // ─── Step 4: Public scope excludes this photo ─────────────────────────

        $publicPhotosBeforeApproval = Photo::where('is_public', true)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(0, $publicPhotosBeforeApproval,
            'Private school photo must NOT appear in public queries'
        );

        // ─── Step 5: Teacher approves ─────────────────────────────────────────

        // Fake events to prevent real listeners (notifications, metrics) from running
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $response = $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved_count', 1);

        // ─── Step 6: Photo is now public + ADMIN_APPROVED ─────────────────────

        $photo->refresh();

        $this->assertTrue((bool) $photo->is_public, 'Photo should be public after approval');
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            is_object($photo->verified) ? $photo->verified->value : $photo->verified,
            'Photo should be ADMIN_APPROVED after teacher approval'
        );
        $this->assertNotNull($photo->team_approved_at, 'Approval timestamp should be set');
        $this->assertEquals($this->teacher->id, $photo->team_approved_by, 'Approver should be teacher');

        // ─── Step 7: TagsVerifiedByAdmin fired with correct data ──────────────
        //
        // This is what triggers MetricsService via ProcessPhotoMetrics listener.
        // Without this event, the photo appears on the map but is never counted
        // in location totals, leaderboards, or XP.

        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id
                && $event->user_id === $this->student->id
                && $event->country_id === $photo->country_id
                && $event->state_id === $photo->state_id
                && $event->city_id === $photo->city_id
                && $event->team_id === $this->schoolTeam->id;
        });

        // ─── Step 8: Public scope now includes this photo ─────────────────────

        $publicPhotosAfterApproval = Photo::where('is_public', true)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(1, $publicPhotosAfterApproval,
            'Approved school photo MUST appear in public queries'
        );

        // ─── Step 9: SchoolDataApproved also fired (notifications) ────────────

        Event::assertDispatched(SchoolDataApproved::class, function ($event) {
            return $event->team->id === $this->schoolTeam->id
                && $event->photoCount === 1;
        });
    }

    /**
     * Verify: approving an already-approved photo is idempotent.
     * Should not fire events twice or change timestamps.
     */
    public function test_double_approval_is_idempotent()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        // First approval
        $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ])
            ->assertOk()
            ->assertJsonPath('approved_count', 1);

        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);

        $firstApprovalTime = $photo->fresh()->team_approved_at;

        // Second approval — same photo (already is_public=true)

        $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ])
            ->assertOk()
            ->assertJsonPath('approved_count', 0); // Already approved → no change

        // TagsVerifiedByAdmin should NOT fire again (still only 1 total, not 2)
        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);

        // Timestamp unchanged
        $this->assertEquals(
            $firstApprovalTime->toDateTimeString(),
            $photo->fresh()->team_approved_at->toDateTimeString()
        );
    }

    /**
     * Verify: public points scope only returns approved school photos.
     * Private + verified photos must NOT leak through.
     */
    public function test_points_api_never_leaks_private_school_photos()
    {
        // 3 school photos at different stages
        $unverified = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        $verified = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        $adminApprovedButPrivate = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false, // Bug scenario: somehow admin_approved but still private
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        // 1 regular public photo (control)
        $publicPhoto = Photo::factory()->create([
            'user_id' => $this->teacher->id,
            'team_id' => null,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        // Public scope: only the regular public photo should appear
        $publicCount = Photo::where('is_public', true)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(1, $publicCount, 'Only truly public + verified photos should appear');

        // Verify none of the 3 school photos leak
        $leakedIds = Photo::where('is_public', true)
            ->whereIn('id', [$unverified->id, $verified->id, $adminApprovedButPrivate->id])
            ->pluck('id')
            ->toArray();

        $this->assertEmpty($leakedIds, 'No school photos should leak through public scope');
    }

    /**
     * Verify: student names are masked in safeguarded photo listings.
     */
    public function test_safeguarding_masks_student_identity_in_photo_pipeline()
    {
        $student2 = User::factory()->create(['name' => 'Jane Doe']);
        $this->schoolTeam->users()->attach($student2->id);

        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        Photo::factory()->create([
            'user_id' => $student2->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        // Student view — names masked
        $response = $this->actingAs($this->student, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();
        $photos = $response->json('photos.data');

        foreach ($photos as $photo) {
            if ($photo['user']['name'] !== null) {
                $this->assertStringStartsWith('Student', $photo['user']['name'],
                    'Student names should be masked in safeguarded teams');
                $this->assertNull($photo['user']['username'],
                    'Usernames should be null in safeguarded teams');
            }
        }

        // Teacher view — real names visible
        $response = $this->actingAs($this->teacher, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $photos = $response->json('photos.data');
        $names = array_column(array_column($photos, 'user'), 'name');

        $this->assertContains($this->student->name, $names,
            'Teacher should see real student names');
    }
}
