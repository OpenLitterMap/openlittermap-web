<?php

namespace Tests\Feature\Teams;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Tag;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class TrustedTeamsTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();
    }

    public function test_photos_uploaded_by_users_of_trusted_teams_are_verified_automatically()
    {
        $tag = Tag::factory()->create();
        Event::fake();

        // User is not verified
        /** @var User $user */
        $user = User::factory()->create(['verification_required' => true]);

        // However, user is part of a trusted team
        /** @var Team $team */
        $team = Team::factory()->create(['is_trusted' => true]);
        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        // User uploads a photo and tags it
        $this->actingAs($user);

        $this->post('/submit', ['file' => $this->getImageAndAttributes()['file'],]);

        $photo = $user->fresh()->photos->last();

        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'picked_up' => true,
            'tags' => [$tag->category->name => [$tag->name => 3]]
        ]);

        // The photo is automatically verified
        $photo->refresh();
        $this->assertEquals(2, $photo->verified);
        $this->assertEquals(1, $photo->verification);

        // Event is fired
        Event::assertDispatched(TagsVerifiedByAdmin::class);
    }

    public function test_photos_uploaded_by_api_users_of_trusted_teams_are_verified_automatically()
    {
        $tag = Tag::factory()->create();
        Event::fake();

        // User is not verified
        /** @var User $user */
        $user = User::factory()->create(['verification_required' => true]);

        // However, user is part of a trusted team
        /** @var Team $team */
        $team = Team::factory()->create(['is_trusted' => true]);
        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        // User uploads a photo and tags it
        $this->actingAs($user, 'api');
        $imageAttributes = $this->getImageAndAttributes();
        $this->post('/api/photos/submit', $this->getApiImageAttributes($imageAttributes));
        $photo = $user->fresh()->photos->last();
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => [$tag->category->name => [$tag->name => 3]]
        ]);

        // The photo is automatically verified
        $photo->refresh();
        $this->assertEquals(2, $photo->verified);
        $this->assertEquals(1, $photo->verification);

        // Event is fired
        Event::assertDispatched(TagsVerifiedByAdmin::class);
    }
}
