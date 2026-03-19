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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Country $country;
    protected Country $countryB;
    protected State $state;
    protected Category $smokingCategory;
    protected LitterObject $cigaretteButt;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->country = Country::factory()->create(['shortcode' => 'IE', 'country' => 'Ireland']);
        $this->countryB = Country::factory()->create(['shortcode' => 'US', 'country' => 'United States']);
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

    /**
     * Create a tagged public photo ready for admin review.
     */
    protected function createPendingPhoto(array $overrides = []): Photo
    {
        $photo = Photo::factory()->create(array_merge([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::VERIFIED->value,
            'total_tags' => 3,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 3]]),
            'xp' => 3,
        ], $overrides));

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

    // ─── Fetch pending photos ─────────────────────────────

    public function test_admin_can_fetch_pending_photos(): void
    {
        $photo = $this->createPendingPhoto();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.total_pending', 1)
            ->assertJsonCount(1, 'photos.data');

        // Check photo structure includes new_tags with CLO fields
        $photoData = $response->json('photos.data.0');
        $this->assertEquals($photo->id, $photoData['id']);
        $this->assertNotEmpty($photoData['new_tags']);
        $this->assertEquals('cigarette_butt', $photoData['new_tags'][0]['object']['key']);
        $this->assertArrayHasKey('category_litter_object_id', $photoData['new_tags'][0]);
        $this->assertNotNull($photoData['new_tags'][0]['category_litter_object_id']);
        $this->assertArrayHasKey('litter_object_type_id', $photoData['new_tags'][0]);
    }

    public function test_response_includes_user_and_country(): void
    {
        $this->createPendingPhoto();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos');

        $response->assertOk();

        $photoData = $response->json('photos.data.0');
        $this->assertArrayHasKey('user', $photoData);
        $this->assertArrayHasKey('country_relation', $photoData);
    }

    // ─── Exclusions ───────────────────────────────────────

    public function test_excludes_already_approved_photos(): void
    {
        $this->createPendingPhoto([
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos');

        $response->assertOk()
            ->assertJsonCount(0, 'photos.data')
            ->assertJsonPath('stats.total_pending', 0);
    }

    public function test_excludes_private_photos(): void
    {
        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => false,
            'verified' => VerificationStatus::VERIFIED->value,
            'summary' => json_encode(['smoking' => ['cigarette_butt' => 1]]),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos');

        $response->assertOk()
            ->assertJsonCount(0, 'photos.data');
    }

    public function test_excludes_untagged_photos(): void
    {
        Photo::factory()->create([
            'user_id' => $this->regularUser->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'is_public' => true,
            'verified' => VerificationStatus::UNVERIFIED->value,
            'summary' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos');

        $response->assertOk()
            ->assertJsonCount(0, 'photos.data');
    }

    public function test_excludes_soft_deleted_photos(): void
    {
        $photo = $this->createPendingPhoto();
        $photo->delete(); // soft delete

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos');

        $response->assertOk()
            ->assertJsonCount(0, 'photos.data');
    }

    // ─── Filters ──────────────────────────────────────────

    public function test_filter_by_country_id(): void
    {
        $this->createPendingPhoto(['country_id' => $this->country->id]);
        $this->createPendingPhoto(['country_id' => $this->countryB->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos?country_id=' . $this->country->id);

        $response->assertOk()
            ->assertJsonCount(1, 'photos.data');
    }

    public function test_filter_by_photo_id(): void
    {
        $photo = $this->createPendingPhoto();
        $this->createPendingPhoto();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos?photo_id=' . $photo->id);

        $response->assertOk()
            ->assertJsonCount(1, 'photos.data')
            ->assertJsonPath('photos.data.0.id', $photo->id);
    }

    public function test_filter_by_date_range(): void
    {
        $this->createPendingPhoto(['created_at' => '2025-01-15 12:00:00']);
        $this->createPendingPhoto(['created_at' => '2025-06-15 12:00:00']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos?date_from=2025-06-01&date_to=2025-07-01');

        $response->assertOk()
            ->assertJsonCount(1, 'photos.data');
    }

    // ─── Pagination ───────────────────────────────────────

    public function test_pagination_works(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createPendingPhoto();
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos?per_page=2&page=1');

        $response->assertOk()
            ->assertJsonCount(2, 'photos.data')
            ->assertJsonPath('photos.last_page', 3)
            ->assertJsonPath('photos.total', 5);

        // Page 2
        $page2 = $this->actingAs($this->admin)
            ->getJson('/api/admin/photos?per_page=2&page=2');

        $page2->assertOk()
            ->assertJsonCount(2, 'photos.data');
    }

    // ─── Auth ─────────────────────────────────────────────

    public function test_non_admin_gets_redirected(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/admin/photos')
            ->assertRedirect('/');
    }
}
