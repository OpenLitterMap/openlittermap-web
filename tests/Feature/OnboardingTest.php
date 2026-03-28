<?php

namespace Tests\Feature;

use App\Enums\CategoryKey;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use HasPhotoUploads;

    protected array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPhotoUploads();
        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    public function test_new_user_has_null_onboarding_completed_at(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->onboarding_completed_at);
    }

    public function test_first_tag_sets_onboarding_completed_at(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => ['id' => $category->id],
                    'object' => ['id' => $object->id],
                    'quantity' => 1,
                    'picked_up' => false,
                ],
            ],
        ])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);
    }

    public function test_second_tag_does_not_change_onboarding_completed_at(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create([
            'onboarding_completed_at' => now()->subDay(),
        ]);
        $originalTimestamp = $user->onboarding_completed_at->toIso8601String();

        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => ['id' => $category->id],
                    'object' => ['id' => $object->id],
                    'quantity' => 1,
                    'picked_up' => false,
                ],
            ],
        ])->assertOk();

        $user->refresh();
        $this->assertEquals($originalTimestamp, $user->onboarding_completed_at->toIso8601String());
    }

    public function test_skip_onboarding_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertNull($user->onboarding_completed_at);

        $this->postJson('/api/user/onboarding/skip')
            ->assertOk()
            ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);
    }

    public function test_skip_onboarding_is_idempotent(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now()->subDay(),
        ]);
        $originalTimestamp = $user->onboarding_completed_at->toIso8601String();

        $this->actingAs($user);

        $this->postJson('/api/user/onboarding/skip')->assertOk();

        $user->refresh();
        $this->assertEquals($originalTimestamp, $user->onboarding_completed_at->toIso8601String());
    }

    public function test_skip_onboarding_requires_auth(): void
    {
        $this->postJson('/api/user/onboarding/skip')
            ->assertUnauthorized();
    }

    public function test_profile_refresh_includes_onboarding_completed_at(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->getJson('/api/user/profile/refresh');

        $response->assertOk();
        $this->assertArrayHasKey('onboarding_completed_at', $response->json('user'));
        $this->assertNotNull($response->json('user.onboarding_completed_at'));
    }

    public function test_profile_refresh_returns_null_onboarding_for_new_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/user/profile/refresh');

        $response->assertOk();
        $this->assertArrayHasKey('onboarding_completed_at', $response->json('user'));
        $this->assertNull($response->json('user.onboarding_completed_at'));
    }
}
