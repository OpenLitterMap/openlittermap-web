<?php

namespace Tests\Feature\Mobile;

use App\Actions\Tags\ConvertV4TagsAction;
use App\Enums\CategoryKey;
use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConvertV4TagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
    }

    public function test_v4_payload_converts_to_photo_tags(): void
    {
        $user = User::factory()->create(['verification_required' => true]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        app(ConvertV4TagsAction::class)->run(
            $user->id,
            $photo->id,
            ['smoking' => ['butts' => 5]],
            true
        );

        $photo->refresh();

        $smokingCategory = Category::where('key', CategoryKey::Smoking->value)->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();

        $photoTags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(1, $photoTags);
        $this->assertEquals($smokingCategory->id, $photoTags[0]->category_id);
        $this->assertEquals($buttsObject->id, $photoTags[0]->litter_object_id);
        $this->assertEquals(5, $photoTags[0]->quantity);
        $this->assertTrue((bool) $photoTags[0]->picked_up);
    }

    public function test_summary_and_xp_generated_after_conversion(): void
    {
        $user = User::factory()->create(['verification_required' => true]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        app(ConvertV4TagsAction::class)->run(
            $user->id,
            $photo->id,
            ['smoking' => ['butts' => 3]],
            true
        );

        $photo->refresh();

        $this->assertNotNull($photo->summary);
        $this->assertIsArray($photo->summary);
        $this->assertArrayHasKey('tags', $photo->summary);
        $this->assertArrayHasKey('totals', $photo->summary);
        $this->assertGreaterThan(0, $photo->xp);
    }

    public function test_unknown_category_skipped_without_error(): void
    {
        $user = User::factory()->create(['verification_required' => true]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        // fakeCategory should be filtered out, smoking should proceed
        app(ConvertV4TagsAction::class)->run(
            $user->id,
            $photo->id,
            [
                'fakeCategory' => ['butts' => 5],
                'smoking' => ['butts' => 3],
            ],
            true
        );

        $photo->refresh();
        $photoTags = PhotoTag::where('photo_id', $photo->id)->get();

        // Only the smoking tag should exist
        $this->assertCount(1, $photoTags);
        $this->assertEquals(3, $photoTags[0]->quantity);
    }

    public function test_empty_tags_is_noop(): void
    {
        $user = User::factory()->create(['verification_required' => true]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        app(ConvertV4TagsAction::class)->run(
            $user->id,
            $photo->id,
            [],
            true
        );

        $photo->refresh();
        $this->assertCount(0, PhotoTag::where('photo_id', $photo->id)->get());
    }

    public function test_duplicate_conversion_is_idempotent(): void
    {
        $user = User::factory()->create(['verification_required' => true]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        $payload = ['smoking' => ['butts' => 5]];

        // First call
        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, $payload, true);
        $countAfterFirst = PhotoTag::where('photo_id', $photo->id)->count();

        // Second call — should be a no-op
        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, $payload, true);
        $countAfterSecond = PhotoTag::where('photo_id', $photo->id)->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
        $this->assertGreaterThan(0, $countAfterFirst);
    }

    public function test_trusted_user_fires_tags_verified_event(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        app(ConvertV4TagsAction::class)->run(
            $user->id,
            $photo->id,
            ['smoking' => ['butts' => 2]],
            true
        );

        Event::assertDispatched(TagsVerifiedByAdmin::class, 1);
    }

    public function test_untrusted_user_does_not_fire_event(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $user = User::factory()->create(['verification_required' => true]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::UNVERIFIED->value,
        ]);

        app(ConvertV4TagsAction::class)->run(
            $user->id,
            $photo->id,
            ['smoking' => ['butts' => 2]],
            true
        );

        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }
}
