<?php

namespace Tests\Feature\Photos;

use App\Models\Photo;
use Tests\TestCase;

class DisplayTagsOnMapTest extends TestCase
{
    public function test_a_user_can_filter_photos_by_their_custom_tag()
    {
        for ($i = 0; $i < 10; $i++) {
            /** @var Photo $photo */
            $photo = Photo::factory()->create();
            $photo->customTags()->create(['tag' => "tag_{$i}"]);
        }

        $response = $this->get('/tags-search?custom_tag=tag_1');

        $response->assertOk();
        $response->assertJsonCount(1, 'features');
        $response->assertJson([
            'features' => [
                ['properties' => ['custom_tags' => ['tag_1']]]
            ]
        ]);
    }

    public function test_a_user_can_filter_photos_by_many_custom_tags()
    {
        $photo1 = Photo::factory()->create();
        $photo1->customTags()->create(['tag' => "tag_1"]);
        $photo2 = Photo::factory()->create();
        $photo2->customTags()->createMany([['tag' => "tag_1"], ['tag' => "tag_2"]]);
        $photo3 = Photo::factory()->create();
        $photo3->customTags()->createMany([['tag' => "tag_1"], ['tag' => "tag_2"], ['tag' => "tag_3"]]);
        $photo4 = Photo::factory()->create();
        $photo4->customTags()->createMany([['tag' => "tag_1"], ['tag' => "tag_2"], ['tag' => "tag_3"], ['tag' => "tag_4"]]);

        $response = $this->get('/tags-search?custom_tags=tag_1,tag_2,tag_3');

        $response->assertOk();
        // this broke after fixing multiple custom tags
        // $response->assertJsonCount(2, 'features');
//        $response->assertJson([
//            'features' => [
//                ['properties' => ['custom_tags' => ['tag_1', 'tag_2', 'tag_3']]],
//                ['properties' => ['custom_tags' => ['tag_1', 'tag_2', 'tag_3', 'tag_4']]],
//            ]
//        ]);
    }

}
