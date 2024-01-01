<?php

namespace Tests\Feature\Photos;

use App\Models\CustomTag;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Smoking;
use App\Models\Photo;
use App\Models\User\User;
use Tests\TestCase;

class AddManyTagsToManyPhotosTest extends TestCase
{

    public function test_a_user_can_bulk_tag_photos()
    {
        /** @var User $user */
        $user = User::factory()->create(['xp' => 2]);
        $photos = Photo::factory(2)->create([
            'user_id' => $user->id,
            'verified' => 0,
            'verification' => 0
        ]);
        $this->assertSame(2, $user->fresh()->xp);

        $response = $this->actingAs($user)->postJson('/user/profile/photos/tags/bulkTag', [
            'photos' => [
                $photos[0]->id => [
                    'tags' => ['smoking' => ['butts' => 3]],
                    'custom_tags' => ['tag1', 'tag2', 'tag3'],
                    'picked_up' => true
                ],
                $photos[1]->id => [
                    'tags' => ['alcohol' => ['pint' => 1]],
                    'custom_tags' => ['tag4', 'tag5'],
                    'picked_up' => false
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $photos->each->refresh();
        $this->assertTrue($photos[0]->picked_up);
        $this->assertInstanceOf(Smoking::class, $photos[0]->smoking);
        $this->assertSame(3, $photos[0]->smoking->butts);
        $this->assertSame(['tag1', 'tag2', 'tag3'], $photos[0]->customTags->pluck('tag')->toArray());

        $this->assertFalse($photos[1]->picked_up);
        $this->assertInstanceOf(Alcohol::class, $photos[1]->alcohol);
        $this->assertSame(1, $photos[1]->alcohol->pint);
        $this->assertSame(['tag4', 'tag5'], $photos[1]->customTags->pluck('tag')->toArray());

        $this->assertSame(11, $user->fresh()->xp); // 2 + (4 tags + 5 custom tags)
    }

    public function test_it_returns_the_current_users_previously_added_custom_tags()
    {
        $user = User::factory()->create();
        Photo::factory()->has(CustomTag::factory(3)->sequence(
            ['tag' => 'custom-1'], ['tag' => 'custom-2'], ['tag' => 'custom-3']
        ))->create(['user_id' => $user->id]);

        $customTags = $this->actingAs($user)
            ->getJson('/user/profile/photos/previous-custom-tags')
            ->assertOk()
            ->json();

        $this->assertCount(3, $customTags);
        $this->assertEqualsCanonicalizing(
            ['custom-1', 'custom-2', 'custom-3'],
            $customTags
        );
    }
}
