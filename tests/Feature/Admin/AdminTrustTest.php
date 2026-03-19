<?php

namespace Tests\Feature\Admin;

use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminTrustTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $admin;
    protected User $regularUser;
    protected Country $country;
    protected State $state;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->country = Country::factory()->create(['shortcode' => 'IE', 'country' => 'Ireland']);
        $this->state = State::factory()->create([
            'country_id' => $this->country->id,
            'state' => 'Cork',
        ]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create([
            'verification_required' => true,
        ]);
    }

    // ─── Trust toggle ────────────────────────────────────

    public function test_superadmin_can_trust_user(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/trust", [
                'trusted' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('user_id', $this->regularUser->id)
            ->assertJsonPath('trusted', true)
            ->assertJsonPath('verification_required', false);

        $this->assertFalse($this->regularUser->fresh()->verification_required);
    }

    public function test_superadmin_can_untrust_user(): void
    {
        $this->regularUser->update(['verification_required' => false]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/trust", [
                'trusted' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('trusted', false)
            ->assertJsonPath('verification_required', true);

        $this->assertTrue($this->regularUser->fresh()->verification_required);
    }

    public function test_admin_cannot_toggle_trust(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/trust", [
                'trusted' => true,
            ])
            ->assertForbidden();

        // User should remain untrusted
        $this->assertTrue($this->regularUser->fresh()->verification_required);
    }

    public function test_non_admin_cannot_toggle_trust(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson("/api/admin/users/{$this->regularUser->id}/trust", [
                'trusted' => true,
            ])
            ->assertRedirect('/');
    }

    public function test_trust_does_not_retroactively_approve_photos(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $photo = Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/trust", [
                'trusted' => true,
            ])
            ->assertOk();

        // Photo should still be VERIFIED, not ADMIN_APPROVED
        $this->assertEquals(
            VerificationStatus::VERIFIED->value,
            $photo->fresh()->verified->value
        );

        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }

    // ─── Approve all ─────────────────────────────────────

    public function test_superadmin_can_approve_all_pending(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $photos = Photo::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/approve-all");

        $response->assertOk()
            ->assertJsonPath('approved_count', 3);

        foreach ($photos as $photo) {
            $this->assertEquals(
                VerificationStatus::ADMIN_APPROVED->value,
                $photo->fresh()->verified->value
            );
        }

        Event::assertDispatched(TagsVerifiedByAdmin::class, 3);
    }

    public function test_approve_all_skips_private_photos(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        // Private (school) photo — should NOT be approved
        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        // Public photo — should be approved
        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/approve-all");

        $response->assertOk()
            ->assertJsonPath('approved_count', 1);

        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);
    }

    public function test_approve_all_skips_photos_without_summary(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::UNVERIFIED->value,
            'summary' => null,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/approve-all");

        $response->assertOk()
            ->assertJsonPath('approved_count', 0);

        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }

    public function test_approve_all_skips_already_approved(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/approve-all");

        $response->assertOk()
            ->assertJsonPath('approved_count', 0);

        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }

    public function test_admin_cannot_approve_all(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/approve-all")
            ->assertForbidden();
    }

    public function test_approve_all_fires_event_per_photo(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        Photo::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $this->actingAs($this->superadmin)
            ->postJson("/api/admin/users/{$this->regularUser->id}/approve-all")
            ->assertOk()
            ->assertJsonPath('approved_count', 2);

        Event::assertDispatched(TagsVerifiedByAdmin::class, 2);
    }
}
