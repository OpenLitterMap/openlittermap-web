<?php

namespace Tests\Feature\Admin;

use App\Enums\VerificationStatus;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminResetTagsTest extends TestCase
{
    protected User $admin;
    protected User $regularUser;
    protected Country $country;
    protected State $state;
    protected Category $smokingCategory;
    protected LitterObject $cigaretteButt;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->country = Country::factory()->create(['shortcode' => 'IE', 'country' => 'Ireland']);
        $this->state = State::factory()->create([
            'country_id' => $this->country->id,
            'state' => 'Cork',
        ]);

        $this->smokingCategory = Category::firstOrCreate(['key' => 'smoking']);
        $this->cigaretteButt = LitterObject::firstOrCreate(['key' => 'cigarette_butt']);
        $this->smokingCategory->litterObjects()->syncWithoutDetaching([$this->cigaretteButt->id]);

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    protected function createTaggedPhoto(): Photo
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'total_tags' => 3,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 3]]),
            'xp' => 3,
        ]);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $this->getCloId($this->smokingCategory->id, $this->cigaretteButt->id),
            'category_id' => $this->smokingCategory->id,
            'litter_object_id' => $this->cigaretteButt->id,
            'quantity' => 3,
            'picked_up' => true,
        ]);

        return $photo;
    }

    public function test_admin_can_reset_tags_on_photo(): void
    {
        $photo = $this->createTaggedPhoto();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/reset-tags', ['photoId' => $photo->id]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $photo->refresh();

        // Photo state reset
        $verifiedValue = $photo->verified instanceof VerificationStatus
            ? $photo->verified->value
            : (int) $photo->verified;
        $this->assertEquals(VerificationStatus::UNVERIFIED->value, $verifiedValue);
        $this->assertNull($photo->summary);
        $this->assertEquals(0, $photo->xp);
        $this->assertEquals(0, $photo->total_tags);

        // PhotoTags deleted
        $this->assertEquals(0, PhotoTag::where('photo_id', $photo->id)->count());
    }

    public function test_reset_reverses_metrics_for_processed_photo(): void
    {
        $photo = $this->createTaggedPhoto();

        // Simulate that MetricsService has processed this photo
        $photo->update([
            'processed_at' => now(),
            'processed_fp' => 'abc123',
            'processed_tags' => json_encode([
                'objects' => ['cigarette_butt' => 3],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ]),
            'processed_xp' => 3,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/reset-tags', ['photoId' => $photo->id]);

        $response->assertOk();

        $photo->refresh();

        // MetricsService::deletePhoto() clears processing columns
        $this->assertNull($photo->processed_at);
        $this->assertNull($photo->processed_fp);
        $this->assertNull($photo->processed_tags);
        $this->assertNull($photo->processed_xp);
    }

    public function test_reset_skips_already_admin_approved_photo(): void
    {
        $photo = $this->createTaggedPhoto();
        $photo->update(['verified' => VerificationStatus::ADMIN_APPROVED->value]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/reset-tags', ['photoId' => $photo->id]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $photo->refresh();

        // Photo state unchanged — reset was skipped
        $verifiedValue = $photo->verified instanceof VerificationStatus
            ? $photo->verified->value
            : (int) $photo->verified;
        $this->assertEquals(VerificationStatus::ADMIN_APPROVED->value, $verifiedValue);
        $this->assertNotNull($photo->summary);
        $this->assertEquals(1, PhotoTag::where('photo_id', $photo->id)->count());
    }

    public function test_non_admin_cannot_reset_tags(): void
    {
        $photo = $this->createTaggedPhoto();

        $this->actingAs($this->regularUser)
            ->postJson('/api/admin/reset-tags', ['photoId' => $photo->id])
            ->assertRedirect('/');
    }
}
