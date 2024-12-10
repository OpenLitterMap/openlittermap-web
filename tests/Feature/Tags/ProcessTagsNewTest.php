<?php

namespace Tests\Feature\Tags;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Database\Seeders\CategoryLitterObjectSeeder;

class ProcessTagsNewTest extends TestCase
{
    /** @test */
    public function test_it_returns_a_list_of_tags (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $response = $this->get('/api/tags');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tags' => [
                '*' => [
                    'key',
                    'litter_objects' => [
                        '*' => [
                            'key',
                            'tag_types' => [
                                '*' => [
                                    'key',
                                    'materials' => [
                                        '*' => [
                                            'key'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function test_it_returns_a_list_of_tags_for_a_category (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $response = $this->get('/api/tags/alcohol');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.key', 'alcohol');
        $response->assertJsonFragment(['key' => 'bottle']);
        $response->assertJsonFragment(['key' => 'beer_bottle']);
        $response->assertJsonMissing(['key' => 'water']);
    }

    public function test_it_returns_an_error_when_a_false_category_is_requested (): void
    {
        $response = $this->get('/api/tags/unknown');

        $response->assertStatus(404);
    }

    public function test_it_returns_a_list_of_tags_for_a_litter_object (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $response = $this->get('/api/tags/alcohol/bottle');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.key', 'bottle');
        $response->assertJsonFragment(['key' => 'beer_bottle']);
        $response->assertJsonFragment(['key' => 'cider_bottle']);
        $response->assertJsonFragment(['key' => 'wine_bottle']);
        $response->assertJsonFragment(['key' => 'spirits_bottle']);
        $response->assertJsonMissing(['key' => 'water']);
        $response->assertJsonMissing(['key' => 'energyDrink']);
        $response->assertJsonMissing(['key' => 'paper']);
    }

    public function test_it_returns_a_list_of_materials_for_a_litter_object (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $response = $this->get('/api/tags/materials/object/bottleTop');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.key', 'bottleTop');
        $response->assertJsonFragment(['key' => 'cork']);
        $response->assertJsonFragment(['key' => 'metal']);
        $response->assertJsonFragment(['key' => 'plastic']);
        $response->assertJsonMissing(['key' => 'ceramic']);
        $response->assertJsonMissing(['key' => 'nylon']);
    }

    public function test_it_returns_a_list_of_materials_for_a_tag_type (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $response = $this->get('/api/tags/materials/tag-type/beer_bottle');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.key', 'beer_bottle');
        $response->assertJsonFragment(['key' => 'glass']);
        $response->assertJsonMissing(['key' => 'plastic']);
        $response->assertJsonMissing(['key' => 'ceramic']);
    }
}
