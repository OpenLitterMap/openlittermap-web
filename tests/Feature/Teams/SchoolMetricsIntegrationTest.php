<?php

namespace Tests\Feature\Teams;

use App\Enums\VerificationStatus;
use App\Events\SchoolDataApproved;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Services\Tags\GeneratePhotoSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integration test: school photo pipeline → MetricsService.
 *
 * Catches the "null summary at approval time" failure mode where
 * MetricsService extracts zero from a photo with no summary JSON.
 */
class SchoolMetricsIntegrationTest extends TestCase
{
    public function test_metrics_table_is_populated_after_teacher_approves_school_photo()
    {
        // Fake only SchoolDataApproved (its listener needs a notifications table we don't have)
        // Let TagsVerifiedByAdmin fire naturally → ProcessPhotoMetrics → MetricsService
        Event::fake([SchoolDataApproved::class]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Setup school team ──────────────────────────────────────────────

        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );

        $teacher = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage school team', 'guard_name' => 'web']);
        $role->givePermissionTo('manage school team');
        $teacher->assignRole('school_manager');

        $schoolTeam = Team::factory()->create([
            'type_id' => $schoolType->id,
            'leader' => $teacher->id,
            'safeguarding' => true,
            'is_trusted' => false,
        ]);
        $schoolTeam->users()->attach($teacher->id);

        $student = User::factory()->create(['verification_required' => true]);
        $schoolTeam->users()->attach($student->id);

        // ─── Step 1: Student creates a photo (private, on school team) ──────

        $photo = Photo::factory()->create([
            'user_id' => $student->id,
            'team_id' => $schoolTeam->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
            'is_public' => false,
        ]);

        // ─── Step 2: Student tags it (simulate AddTagsToPhotoAction output) ─

        $smokingCategory = Category::firstOrCreate(['key' => 'smoking']);
        $buttsObject = LitterObject::firstOrCreate(['key' => 'cigarette_butt']);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $smokingCategory->id,
            'litter_object_id' => $buttsObject->id,
            'quantity' => 5,
            'picked_up' => true,
        ]);

        // Generate summary + XP (this is what AddTagsToPhotoAction does)
        $photo->refresh();
        app(GeneratePhotoSummaryService::class)->run($photo);

        $photo->refresh();
        $photo->update([
            'verified' => VerificationStatus::VERIFIED->value,
        ]);

        // ─── Step 3: Verify pre-conditions ──────────────────────────────────

        $photo->refresh();

        $this->assertNotNull($photo->summary, 'Summary must exist before approval');
        $this->assertFalse((bool) $photo->is_public, 'Photo should be private before approval');
        $this->assertNull($photo->processed_at, 'Photo should not be processed yet');
        $this->assertDatabaseCount('metrics', 0);

        // ─── Step 4: Teacher approves → TagsVerifiedByAdmin fires ───────────
        //     → ProcessPhotoMetrics listener → MetricsService::processPhoto()

        $response = $this->actingAs($teacher, 'api')
            ->postJson('/api/teams/photos/approve', [
                'team_id' => $schoolTeam->id,
                'photo_ids' => [$photo->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved_count', 1);

        // ─── Step 5: Verify MetricsService wrote to metrics table ───────────

        $photo->refresh();

        $this->assertNotNull($photo->processed_at, 'MetricsService should have set processed_at');
        $this->assertGreaterThan(0, (int) $photo->processed_xp, 'processed_xp should be > 0');

        // Metrics table should have rows for this photo's country
        // (timescale 0 = all-time, location_type 1 = country)
        $countryMetrics = DB::table('metrics')
            ->where('timescale', 0) // all-time
            ->where('location_type', 1) // country
            ->where('location_id', $photo->country_id)
            ->first();

        $this->assertNotNull($countryMetrics, 'Metrics row should exist for the photo\'s country');
        $this->assertGreaterThan(0, $countryMetrics->litter, 'Litter count should be > 0 (not zero from null summary)');
        $this->assertEquals(1, $countryMetrics->uploads, 'Should count as 1 upload');

        // Global all-time row (location_type 0 = global, location_id 0)
        $globalMetrics = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', 0)
            ->where('location_id', 0)
            ->first();

        $this->assertNotNull($globalMetrics, 'Global metrics row should exist');
        $this->assertGreaterThan(0, $globalMetrics->litter);
    }
}
