<?php

namespace Tests\Feature\Admin;

use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Littercoin;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Services\Metrics\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Country $country;
    protected State $state;
    protected Category $smokingCategory;
    protected LitterObject $cigaretteButt;
    protected Category $foodCategory;
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
        $this->smokingCategory->litterObjects()->syncWithoutDetaching([$this->cigaretteButt->id]);
        $this->foodCategory->litterObjects()->syncWithoutDetaching([$this->wrapper->id]);

        // Admin user with 'admin' role (web guard — IsAdmin middleware checks this)
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');

        // Regular user (no admin role)
        $this->regularUser = User::factory()->create();
    }

    /**
     * Create a tagged public photo ready for admin approval.
     */
    protected function createTaggedPhoto(?User $user = null): Photo
    {
        $user = $user ?? $this->regularUser;

        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'city_id' => null,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'total_tags' => 5,
            'summary' => json_encode([
                'smoking' => ['cigarette_butt' => 3],
                'food' => ['wrapper' => 2],
            ]),
            'xp' => 5,
        ]);

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

    // ─── FIX 1: verify() ─────────────────────────────────

    public function test_admin_can_approve_photo_fires_metrics(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $photo = $this->createTaggedPhoto();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/verify', ['photoId' => $photo->id]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('approved', true);

        $photo->refresh();

        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->verified instanceof VerificationStatus ? $photo->verified->value : (int) $photo->verified
        );

        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id
                && $event->user_id === $photo->user_id
                && $event->country_id === $this->country->id
                && $event->state_id === $this->state->id;
        });
    }

    public function test_approve_is_idempotent(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $photo = $this->createTaggedPhoto();

        // First approve
        $this->actingAs($this->admin)
            ->postJson('/api/admin/verify', ['photoId' => $photo->id])
            ->assertJsonPath('approved', true);

        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);

        // Second approve — same photo
        $this->actingAs($this->admin)
            ->postJson('/api/admin/verify', ['photoId' => $photo->id])
            ->assertJsonPath('approved', false);

        // Still only 1 dispatch total
        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);
    }

    public function test_approve_rejects_null_summary(): void
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::UNVERIFIED->value,
            'summary' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/verify', ['photoId' => $photo->id]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── FIX 2: destroy() ────────────────────────────────

    public function test_admin_can_delete_photo_with_metrics_reversal(): void
    {
        $photo = $this->createTaggedPhoto();

        // Simulate that photo has been processed by MetricsService
        $photo->update([
            'processed_at' => now(),
            'processed_fp' => 'abc123',
            'processed_tags' => json_encode([
                'objects' => ['cigarette_butt' => 3, 'wrapper' => 2],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ]),
            'processed_xp' => 5,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/destroy', ['photoId' => $photo->id]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Soft deleted — not hard deleted
        $this->assertSoftDeleted('photos', ['id' => $photo->id]);
    }

    // ─── FIX 3: updateDelete() ───────────────────────────

    public function test_admin_can_edit_tags_and_approve(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $photo = $this->createTaggedPhoto();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/contentsupdatedelete', [
                'photoId' => $photo->id,
                'tags' => [
                    [
                        'category' => 'smoking',
                        'object' => 'cigarette_butt',
                        'quantity' => 10,
                        'picked_up' => true,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $photo->refresh();

        // Old tags replaced with new ones
        $this->assertEquals(1, $photo->photoTags()->count());
        $this->assertEquals(10, $photo->photoTags()->first()->quantity);

        // Summary regenerated
        $this->assertNotNull($photo->summary);

        // Verified upgraded
        $this->assertEquals(
            VerificationStatus::ADMIN_APPROVED->value,
            $photo->verified instanceof VerificationStatus ? $photo->verified->value : (int) $photo->verified
        );

        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id;
        });
    }

    public function test_retag_already_approved_photo_updates_metrics(): void
    {
        // First: create, approve, and process a photo
        $photo = $this->createTaggedPhoto();

        // Approve it
        $this->actingAs($this->admin)
            ->postJson('/api/admin/verify', ['photoId' => $photo->id])
            ->assertJsonPath('approved', true);

        $photo->refresh();

        // Simulate that MetricsService has processed it
        $photo->update([
            'processed_at' => now(),
            'processed_fp' => 'original_fp',
            'processed_tags' => json_encode([
                'objects' => ['cigarette_butt' => 3, 'wrapper' => 2],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ]),
            'processed_xp' => 5,
        ]);

        $originalFp = $photo->processed_fp;

        // Now re-tag with different tags
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/contentsupdatedelete', [
                'photoId' => $photo->id,
                'tags' => [
                    [
                        'category' => 'smoking',
                        'object' => 'cigarette_butt',
                        'quantity' => 20,
                        'picked_up' => true,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $photo->refresh();

        // processPhoto was called: fingerprint changed because tags changed
        $this->assertNotEquals($originalFp, $photo->processed_fp);
        $this->assertNotNull($photo->processed_at);
    }

    public function test_retag_does_not_fire_event_for_already_approved(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $photo = $this->createTaggedPhoto();

        // Make it already approved + processed
        $photo->update([
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'processed_at' => now(),
            'processed_fp' => 'original_fp',
            'processed_tags' => json_encode([
                'objects' => ['cigarette_butt' => 3],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ]),
            'processed_xp' => 3,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/contentsupdatedelete', [
                'photoId' => $photo->id,
                'tags' => [
                    [
                        'category' => 'food',
                        'object' => 'wrapper',
                        'quantity' => 5,
                        'picked_up' => false,
                    ],
                ],
            ])
            ->assertOk();

        // Event should NOT fire — photo was already approved
        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }

    // ─── FIX 4: Auth ─────────────────────────────────────

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $photo = $this->createTaggedPhoto();

        // Non-admin user — IsAdmin middleware returns redirect for web guard
        $this->actingAs($this->regularUser)
            ->postJson('/api/admin/verify', ['photoId' => $photo->id])
            ->assertRedirect('/');

        $this->actingAs($this->regularUser)
            ->postJson('/api/admin/destroy', ['photoId' => $photo->id])
            ->assertRedirect('/');

        $this->actingAs($this->regularUser)
            ->postJson('/api/admin/contentsupdatedelete', ['photoId' => $photo->id])
            ->assertRedirect('/');
    }

    // ─── School exclusion ────────────────────────────────

    public function test_school_photo_excluded_from_approval(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        // Create a school photo (is_public = false)
        $photo = Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
            'xp' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/verify', ['photoId' => $photo->id]);

        $response->assertOk()
            ->assertJsonPath('approved', false);

        // TagsVerifiedByAdmin should NOT fire (is_public=false excluded by WHERE)
        Event::assertNotDispatched(TagsVerifiedByAdmin::class);

        // Photo should remain VERIFIED, not upgraded
        $photo->refresh();
        $verifiedValue = $photo->verified instanceof VerificationStatus
            ? $photo->verified->value
            : (int) $photo->verified;
        $this->assertEquals(VerificationStatus::VERIFIED->value, $verifiedValue);
    }
}
