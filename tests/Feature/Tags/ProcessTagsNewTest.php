<?php

namespace Tests\Feature\Tags;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Database\Seeders\Tests\LoadTagsSeeder;

class ProcessTagsNewTest extends TestCase
{
    /** @test */
    public function test_it_returns_a_list_of_tags (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $response = $this->get('/api/tags');

        Log::info($response->json());

        $response->assertStatus(200);
    }

//    public function test_it_returns_a_list_of_tags_for_a_category (): void
//    {
//        $this->seed(CategoryLitterObjectSeeder::class);
//
//        $response = $this->get('/api/tags/category/alcohol');
//
//        $response->assertStatus(200);
//        $response->assertJsonPath('tags.key', 'alcohol');
//        $response->assertJsonFragment(['key' => 'bottle']);
//        $response->assertJsonFragment(['key' => 'beer_bottle']);
//        $response->assertJsonMissing(['key' => 'water']);
//    }
//
//    public function test_it_returns_an_error_when_a_false_category_is_requested (): void
//    {
//        $response = $this->get('/api/tags/unknown');
//
//        $response->assertStatus(404);
//    }
//
//    public function test_it_returns_a_list_of_tags_for_a_category_litter_object (): void
//    {
//        $this->seed(CategoryLitterObjectSeeder::class);
//
//        $response = $this->get('/api/tags/category/alcohol/object/bottle');
//
//        $response->assertStatus(200);
//        $response->assertJsonPath('tags.key', 'bottle');
//        $response->assertJsonFragment(['key' => 'beer_bottle']);
//        $response->assertJsonFragment(['key' => 'cider_bottle']);
//        $response->assertJsonFragment(['key' => 'wine_bottle']);
//        $response->assertJsonFragment(['key' => 'spirits_bottle']);
//        $response->assertJsonMissing(['key' => 'water']);
//        $response->assertJsonMissing(['key' => 'energyDrink']);
//        $response->assertJsonMissing(['key' => 'paper']);
//    }
//
//    public function test_it_returns_a_list_of_tags_for_a_litter_object (): void
//    {
//        $this->seed(CategoryLitterObjectSeeder::class);
//
//        $response = $this->get('/api/tags/object/bottle');
//
//        $response->assertStatus(200);
//        $response->assertJsonPath('tags.key', 'bottle');
//        $response->assertJsonFragment(['key' => 'alcohol']);
//        $response->assertJsonFragment(['key' => 'softdrinks']);
//        $response->assertJsonFragment(['key' => 'beer_bottle']);
//        $response->assertJsonFragment(['key' => 'energyDrink']);
//        $response->assertJsonFragment(['key' => 'glass']);
//        $response->assertJsonFragment(['key' => 'plastic']);
//        $response->assertJsonMissing(['key' => 'nylon']);
//        $response->assertJsonMissing(['key' => 'butts']);
//    }
//
//    // not entirely sure how to handle this yet
//    public function test_it_returns_a_list_of_tags_for_a_tag_type (): void
//    {
//        $this->seed(CategoryLitterObjectSeeder::class);
//
//        // there are 2 tagTypes with the key "box"
//        // and 1 object with the key "box"
//        $response = $this->get('/api/tags/tag-type/box');
//
////        $text = json_decode($response->getContent(), true);
////        \Log::info($text);
//
//        $response->assertStatus(200);
//    }
//
//    public function test_it_returns_a_list_of_materials_for_a_litter_object (): void
//    {
//        $this->seed(CategoryLitterObjectSeeder::class);
//
//        $response = $this->get('/api/tags/materials/object/bottleTop');
//
//        $response->assertStatus(200);
//        $response->assertJsonPath('tags.key', 'bottleTop');
//        $response->assertJsonFragment(['key' => 'cork']);
//        $response->assertJsonFragment(['key' => 'metal']);
//        $response->assertJsonFragment(['key' => 'plastic']);
//        $response->assertJsonMissing(['key' => 'ceramic']);
//        $response->assertJsonMissing(['key' => 'nylon']);
//    }
//
//    public function test_it_returns_a_list_of_materials_for_a_tag_type (): void
//    {
//        $this->seed(CategoryLitterObjectSeeder::class);
//
//        $response = $this->get('/api/tags/materials/tag-type/beer_bottle');
//
//        $response->assertStatus(200);
//        $response->assertJsonPath('tags.key', 'beer_bottle');
//        $response->assertJsonFragment(['key' => 'glass']);
//        $response->assertJsonMissing(['key' => 'plastic']);
//        $response->assertJsonMissing(['key' => 'ceramic']);
//    }
//
//    public function test_it_searches_across_tags (): void
//    {
//        $this->seed(CategoryLitterObjectSeeder::class);
//
//        $response = $this->get('/api/tags/search?q=ba');
//
//        $response->assertStatus(200);
//        $response->assertJsonPath('litterObjects.0.key', 'battery');
//        $response->assertJsonPath('tagTypes.0.key', 'baseballCap');
//        $response->assertJsonPath('materials.0.key', 'bamboo');
//        $response->assertJsonMissing(['key' => 'bottle']);
//        $response->assertJsonMissing(['key' => 'smoking']);
//        $response->assertJsonMissing(['key' => 'alcohol']);
//        $response->assertJsonMissing(['key' => 'butts']);
//    }
}
