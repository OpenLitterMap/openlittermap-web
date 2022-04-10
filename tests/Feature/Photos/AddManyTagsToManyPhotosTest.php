<?php

namespace Tests\Feature\Photos;

use App\Models\Photo;
use App\Models\Tag;
use App\Models\User\User;
use Tests\TestCase;

class AddManyTagsToManyPhotosTest extends TestCase
{
    public function test_a_user_can_add_tags_to_a_photo()
    {
        $tag = Tag::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['xp' => 2]);
        $photos = Photo::factory(2)->create([
            'user_id' => $user->id,
            'verified' => 0,
            'verification' => 0
        ]);
        $this->assertEquals(2, $user->fresh()->xp);

        $response = $this->actingAs($user)->postJson('/user/profile/photos/tags/create', [
            'selectAll' => false,
            'filters' => [],
            'inclIds' => $photos->pluck('id')->toArray(),
            'tags' => [$tag->category->name => [$tag->name => 3]],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        foreach ($photos as $photo) {
            $this->assertCount(1, $photo->fresh()->tags);
            $this->assertEquals(3, $photo->fresh()->tags->first()->pivot->quantity);
        }
        $this->assertEquals(8, $user->fresh()->xp); // 2 + 6
    }

    public function test_a_user_can_add_custom_tags_to_a_photo()
    {
        /** @var User $user */
        $user = User::factory()->create(['xp' => 2]);
        $photos = Photo::factory(2)->create([
            'user_id' => $user->id,
            'verified' => 0,
            'verification' => 0
        ]);
        $this->assertEquals(2, $user->fresh()->xp);

        $response = $this->actingAs($user)->postJson('/user/profile/photos/tags/create', [
            'selectAll' => false,
            'filters' => [],
            'inclIds' => $photos->pluck('id')->toArray(),
            'custom_tags' => ['tag1', 'tag2', 'tag3']
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        foreach ($photos as $photo) {
            $this->assertEquals(['tag1', 'tag2', 'tag3'], $photo->fresh()->customTags->pluck('tag')->toArray());
        }
        $this->assertEquals(8, $user->fresh()->xp); // 2 + 8
    }

    public function test_a_user_can_add_tags_and_custom_tags_to_a_photo()
    {
        $tag = Tag::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['xp' => 2]);
        $photos = Photo::factory(2)->create([
            'user_id' => $user->id,
            'verified' => 0,
            'verification' => 0
        ]);
        $this->assertEquals(2, $user->fresh()->xp);

        $response = $this->actingAs($user)->postJson('/user/profile/photos/tags/create', [
            'selectAll' => false,
            'filters' => [],
            'inclIds' => $photos->pluck('id')->toArray(),
            'tags' => [$tag->category->name => [$tag->name => 3]],
            'custom_tags' => ['tag1', 'tag2', 'tag3']
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        foreach ($photos as $photo) {
            $this->assertCount(1, $photo->fresh()->tags);
            $this->assertEquals(3, $photo->fresh()->tags->first()->pivot->quantity);
            $this->assertEquals(['tag1', 'tag2', 'tag3'], $photo->fresh()->customTags->pluck('tag')->toArray());
        }
        $this->assertEquals(14, $user->fresh()->xp); // 2 + (6 + 6)
    }
}
