<?php

namespace Tests\Feature\User;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Tests\TestCase;

class PhotoVisibilityTest extends TestCase
{
    public function test_user_can_toggle_own_photo_to_private(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => false,
            ]);

        $response->assertOk();
        $this->assertFalse((bool) $photo->fresh()->is_public);
    }

    public function test_user_can_toggle_own_photo_to_public(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => true,
            ]);

        $response->assertOk();
        $this->assertTrue((bool) $photo->fresh()->is_public);
    }

    public function test_user_cannot_toggle_another_users_photo(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $owner->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($other)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => false,
            ]);

        $response->assertForbidden();
        $this->assertTrue((bool) $photo->fresh()->is_public);
    }

    public function test_school_team_photo_toggle_rejected(): void
    {
        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );
        $team = Team::factory()->create([
            'type_id' => $schoolType->id,
            'type_name' => 'school',
        ]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => true,
            ]);

        $response->assertForbidden();
        $this->assertFalse((bool) $photo->fresh()->is_public);
    }

    public function test_unauthenticated_toggle_rejected(): void
    {
        $photo = Photo::factory()->create(['is_public' => true]);

        $response = $this->patchJson("/api/v3/photos/{$photo->id}/visibility", [
            'is_public' => false,
        ]);

        $response->assertUnauthorized();
    }

    public function test_toggle_requires_is_public_field(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", []);

        $response->assertUnprocessable();
    }

    /**
     * Spec test case #8: Private photo gets tagged — TagsVerifiedByAdmin fires,
     * metrics processed, user appears on leaderboard, photo stays off map.
     */
    public function test_private_photo_tagging_still_fires_metrics_event(): void
    {
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\TagsVerifiedByAdmin::class,
        ]);

        $user = User::factory()->create();
        $country = \App\Models\Location\Country::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'is_public' => false,
            'verified' => 0,
        ]);

        $this->seed(\Database\Seeders\Tags\GenerateTagsSeeder::class);

        $category = \App\Models\Litter\Tags\Category::where('key', 'smoking')->first();
        $object = \App\Models\Litter\Tags\LitterObject::where('key', 'butts')->first();
        $clo = \App\Models\Litter\Tags\CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        app(\App\Actions\Tags\AddTagsToPhotoAction::class)->run($user->id, $photo->id, [
            ['category_litter_object_id' => $clo->id, 'quantity' => 1],
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\TagsVerifiedByAdmin::class,
            function ($event) use ($photo) {
                return $event->photo_id === $photo->id;
            }
        );

        $this->assertFalse((bool) $photo->fresh()->is_public);
    }
}
