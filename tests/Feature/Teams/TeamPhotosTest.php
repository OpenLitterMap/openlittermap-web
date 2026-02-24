<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeamPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Team $schoolTeam;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create school team type
        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );

        // Create teacher with school_manager role
        $this->teacher = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage school team', 'guard_name' => 'web']);
        $role->givePermissionTo('manage school team');
        $this->teacher->assignRole('school_manager');

        // Create school team
        $this->schoolTeam = Team::factory()->create([
            'type_id' => $schoolType->id,
            'leader' => $this->teacher->id,
            'safeguarding' => true,
        ]);

        $this->schoolTeam->users()->attach($this->teacher->id);

        // Create student
        $this->student = User::factory()->create();
        $this->schoolTeam->users()->attach($this->student->id);

        // Tag taxonomy (needed for tag editing tests)
        Category::firstOrCreate(['key' => 'smoking']);
        Category::firstOrCreate(['key' => 'alcohol']);
        LitterObject::firstOrCreate(['key' => 'cigarette_butt']);
        LitterObject::firstOrCreate(['key' => 'beer_can']);
    }

    // ─── Enum Tests ─────────────────────────────────

    public function test_verification_enum_values()
    {
        $this->assertEquals(0, VerificationStatus::UNVERIFIED->value);
        $this->assertEquals(1, VerificationStatus::VERIFIED->value);
        $this->assertEquals(2, VerificationStatus::ADMIN_APPROVED->value);
        $this->assertEquals(3, VerificationStatus::BBOX_APPLIED->value);
        $this->assertEquals(4, VerificationStatus::BBOX_VERIFIED->value);
        $this->assertEquals(5, VerificationStatus::AI_READY->value);
    }

    public function test_verification_enum_public_ready()
    {
        $this->assertFalse(VerificationStatus::UNVERIFIED->isPublicReady());
        $this->assertFalse(VerificationStatus::VERIFIED->isPublicReady());
        $this->assertTrue(VerificationStatus::ADMIN_APPROVED->isPublicReady());
        $this->assertTrue(VerificationStatus::AI_READY->isPublicReady());
    }

    public function test_verification_enum_ai_ready()
    {
        $this->assertFalse(VerificationStatus::ADMIN_APPROVED->isAiReady());
        $this->assertFalse(VerificationStatus::BBOX_VERIFIED->isAiReady());
        $this->assertTrue(VerificationStatus::AI_READY->isAiReady());
    }

    public function test_verification_enum_labels()
    {
        $this->assertEquals('Unverified', VerificationStatus::UNVERIFIED->label());
        $this->assertEquals('AI Ready', VerificationStatus::AI_READY->label());
    }

    // ─── Privacy: School photos are private by default ────────

    public function test_school_team_photos_are_private_by_default()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
        ]);

        // PhotoObserver should set is_public = false
        // If observer isn't registered, this tests the expected state
        $this->assertFalse((bool) $photo->fresh()->is_public);
    }

    public function test_non_school_team_photos_are_public_by_default()
    {
        $communityType = TeamType::firstOrCreate(
            ['team' => 'community'],
            ['team' => 'community', 'price' => 0]
        );

        $communityTeam = Team::factory()->create([
            'type_id' => $communityType->id,
            'leader' => $this->teacher->id,
        ]);

        $photo = Photo::factory()->create([
            'user_id' => $this->teacher->id,
            'team_id' => $communityTeam->id,
        ]);

        $this->assertTrue((bool) $photo->fresh()->is_public);
    }

    // ─── Photo Listing (members only) ─────────────────

    public function test_team_member_can_list_photos()
    {
        Photo::factory()->count(3)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->student, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'photos.data');
    }

    public function test_non_member_cannot_list_team_photos()
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'not-a-member');
    }

    public function test_photo_list_filters_by_status()
    {
        // 2 pending, 1 approved
        Photo::factory()->count(2)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'team_approved_at' => null,
        ]);

        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => true,
            'team_approved_at' => now(),
        ]);

        $pending = $this->actingAs($this->teacher, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id . '&status=pending');

        $pending->assertJsonCount(2, 'photos.data');

        $approved = $this->actingAs($this->teacher, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id . '&status=approved');

        $approved->assertJsonCount(1, 'photos.data');
    }

    // ─── Approval Flow ────────────────────────────────

    public function test_teacher_can_approve_specific_photos()
    {
        Event::fake([SchoolDataApproved::class, TagsVerifiedByAdmin::class]);

        $photos = Photo::factory()->count(3)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        $response = $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photos[0]->id, $photos[1]->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved_count', 2);

        // Photos 0 and 1 are now public + admin approved
        $this->assertTrue((bool) $photos[0]->fresh()->is_public);
        $this->assertTrue((bool) $photos[1]->fresh()->is_public);
        $this->assertNotNull($photos[0]->fresh()->team_approved_at);
        $this->assertEquals($this->teacher->id, $photos[0]->fresh()->team_approved_by);
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photos[0]->fresh()->verified->value ?? $photos[0]->fresh()->verified
        );

        // Photo 2 still private
        $this->assertFalse((bool) $photos[2]->fresh()->is_public);

        // SchoolDataApproved fires (notifications)
        Event::assertDispatched(SchoolDataApproved::class);

        // TagsVerifiedByAdmin fires for each approved photo (MetricsService)
        Event::assertDispatched(TagsVerifiedByAdmin::class, 2);
    }

    public function test_teacher_can_approve_all_pending()
    {
        Event::fake([SchoolDataApproved::class, TagsVerifiedByAdmin::class]);

        Photo::factory()->count(5)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        $response = $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'approve_all' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('approved_count', 5);

        $publicCount = Photo::where('team_id', $this->schoolTeam->id)
            ->where('is_public', true)
            ->count();

        $this->assertEquals(5, $publicCount);

        // TagsVerifiedByAdmin fires for each → MetricsService processes all 5
        Event::assertDispatched(TagsVerifiedByAdmin::class, 5);
    }

    public function test_student_cannot_approve_photos()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->student, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertStatus(403);
    }

    // ─── Tag Editing ──────────────────────────────────

    public function test_teacher_can_edit_tags_on_team_photo()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->teacher, 'api')
            ->patchJson("/api/teams/photos/{$photo->id}/tags", [
                'tags' => [
                    ['category' => 'smoking', 'object' => 'cigarette_butt', 'quantity' => 5, 'picked_up' => true],
                    ['category' => 'alcohol', 'object' => 'beer_can', 'quantity' => 2, 'picked_up' => false],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(7, $photo->fresh()->total_tags);
        $this->assertCount(2, $photo->fresh()->photoTags);
    }

    public function test_student_cannot_edit_tags_on_team_photo()
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->student, 'api')
            ->patchJson("/api/teams/photos/{$photo->id}/tags", [
                'tags' => [
                    ['category' => 'smoking', 'object' => 'cigarette_butt', 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(403);
    }

    // ─── Team Map (private) ───────────────────────────

    public function test_member_can_view_team_map_points()
    {
        Photo::factory()->count(3)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'lat' => 51.8979,
            'lon' => -8.4706,
        ]);

        $response = $this->actingAs($this->student, 'api')
            ->getJson('/api/teams/photos/map?team_id=' . $this->schoolTeam->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'points');
    }

    public function test_non_member_cannot_view_team_map()
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider, 'api')
            ->getJson('/api/teams/photos/map?team_id=' . $this->schoolTeam->id);

        $response->assertStatus(403);
    }

    // ─── Dashboard with Verification Breakdown ────────

    public function test_dashboard_returns_verification_breakdown()
    {
        // Create photos with different verification statuses
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::VERIFIED->value,
        ]);
        Photo::factory()->count(2)->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'total_tags' => 10,
        ]);

        $response = $this->actingAs($this->teacher, 'api')
            ->getJson('/api/teams/data?team_id=' . $this->schoolTeam->id . '&period=all');

        $response->assertOk()
            ->assertJsonPath('photos_count', 4)
            ->assertJsonPath('litter_count', 20) // 2 admin_approved × 10 total_tags
            ->assertJsonPath('verification.unverified', 1)
            ->assertJsonPath('verification.verified', 1)
            ->assertJsonPath('verification.admin_approved', 2);
    }

    // ─── MetricsService Integration ─────────────────

    /**
     * Critical: School teams must NOT be trusted.
     *
     * If a school team is trusted, TagsVerifiedByAdmin fires on tag creation
     * (before teacher approval), which writes to MetricsService global/country/state
     * totals. The photo is hidden from the map (is_public=false) but aggregate data
     * leaks — Ireland's litter count goes up before the teacher even reviews the data.
     *
     * Teacher approval IS the verification event for school photos.
     */
    public function test_approval_fires_tags_verified_for_metrics()
    {
        Event::fake([TagsVerifiedByAdmin::class, SchoolDataApproved::class]);

        $photo = Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'total_tags' => 5,
        ]);

        // Approve
        $this->actingAs($this->teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $this->schoolTeam->id,
                'photo_ids' => [$photo->id],
            ])
            ->assertOk();

        // TagsVerifiedByAdmin fires with correct photo data
        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id
                && $event->user_id === $photo->user_id
                && $event->team_id === $this->schoolTeam->id;
        });

        // Photo is now ADMIN_APPROVED (not just VERIFIED)
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->fresh()->verified->value ?? $photo->fresh()->verified
        );
    }

    // ─── Points API Exclusion ─────────────────────────

    public function test_private_school_photos_excluded_from_public_scope()
    {
        // School team photo (private)
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        // Regular photo (public)
        Photo::factory()->create([
            'user_id' => $this->teacher->id,
            'team_id' => null,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        // Public scope should only return the public photo
        $publicPhotos = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(1, $publicPhotos);

        // After approval, both should appear
        Photo::where('team_id', $this->schoolTeam->id)->update([
            'is_public' => true,
            'team_approved_at' => now(),
        ]);

        $publicPhotos = Photo::public()
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->count();

        $this->assertEquals(2, $publicPhotos);
    }

    // ─── Safeguarding in Photo List ───────────────────

    public function test_student_names_masked_in_safeguarded_team_photo_list()
    {
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        // Student sees masked names
        $response = $this->actingAs($this->student, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $photos = $response->json('photos.data');
        $this->assertStringStartsWith('Student', $photos[0]['user']['name']);
    }

    public function test_teacher_sees_real_names_in_photo_list()
    {
        Photo::factory()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->schoolTeam->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($this->teacher, 'api')
            ->getJson('/api/teams/photos?team_id=' . $this->schoolTeam->id);

        $response->assertOk();

        $photos = $response->json('photos.data');
        // Teacher is leader, so should see real names
        $this->assertEquals($this->student->name, $photos[0]['user']['name']);
    }
}
