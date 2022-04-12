<?php

namespace Tests\Unit\Models;

use App\Models\AI\Annotation;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    public function test_a_photo_has_proper_casts()
    {
        $casts = Photo::factory()->create()->getCasts();

        $this->assertContains('datetime', $casts);
    }

    public function test_a_photo_has_selected_attribute()
    {
        $photo = Photo::factory()->create();

        $this->assertFalse($photo->selected);
    }

    public function test_a_photo_has_picked_up_attribute()
    {
        $photo = Photo::factory()->create();

        $this->assertEquals(!$photo->remaining, $photo->picked_up);
    }

    public function test_a_photo_has_many_boxes()
    {
        $photo = Photo::factory()->create();

        $annotation = Annotation::factory()->create([
            'photo_id' => $photo->id
        ]);

        $this->assertInstanceOf(Collection::class, $photo->boxes);
        $this->assertCount(1, $photo->boxes);
        $this->assertTrue($annotation->is($photo->boxes->first()));
    }

    public function test_a_photo_has_a_translated_string_of_its_tags()
    {
        $category = Category::factory()->create();
        $tag1 = Tag::factory()->hasCategory($category)->create();
        $tag2 = Tag::factory()->hasCategory($category)->create();
        $tag3 = Tag::factory()->create();
        /** @var Photo $photo */
        $photo = Photo::factory()->create();
        $photo->tags()->attach($tag1, ['quantity' => 1]);
        $photo->tags()->attach($tag2, ['quantity' => 2]);
        $photo->tags()->attach($tag3, ['quantity' => 3]);

        $photo->translate();

        $expected = [];
        foreach ($photo->tags as $tag) {
            $expected[] = $tag->category->slug . '.' . $tag->slug . ' ' . $tag->pivot->quantity;
        }
        $this->assertEquals(implode(', ', $expected), $photo->result_string);
    }

    public function test_a_photo_has_a_user()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $photo->user);
        $this->assertTrue($user->is($photo->user));
    }

    public function test_a_photo_has_a_tags_relationship()
    {
        /** @var Category $category */
        $category = Category::factory()->create();
        /** @var Tag $tag */
        $tag = Tag::factory()->create();
        /** @var Photo $photo */
        $photo = Photo::factory()->create();
        $photo->tags()->attach($tag);

        $this->assertInstanceOf(Collection::class, $photo->tags);
        $this->assertTrue($tag->is($photo->tags->first()));
    }
}
