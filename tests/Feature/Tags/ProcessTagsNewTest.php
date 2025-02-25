<?php

namespace Tests\Feature\Tags;

use Tests\TestCase;
use Database\Seeders\Tags\GenerateTagsSeeder;

class ProcessTagsNewTest extends TestCase
{
    public function test_it_returns_a_list_of_tags (): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $response = $this->get('/api/tags');

        $response->assertStatus(200);
    }

    public function test_it_returns_the_correct_list_of_tags_for_a_single_category (): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $response = $this->get('/api/tags?category=alcohol');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.alcohol.key', 'alcohol');
        $response->assertJsonPath('tags.alcohol.litter_objects.0.key', 'beer_bottle');
        $response->assertJsonMissing(['key' => 'water_bottle']);
        $response->assertJsonMissing(['key' => 'nylon']);
    }

    public function test_it_returns_the_correct_list_of_tags_for_a_category_and_litter_object (): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $response = $this->get('/api/tags?category=alcohol&object=bottle');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.alcohol.key', 'alcohol');
        $response->assertJsonPath('tags.alcohol.litter_objects.0.key', 'beer_bottle');
        $response->assertJsonPath('tags.alcohol.litter_objects.0.materials.0.key', 'glass');
        $response->assertJsonMissingPath('tags.0.litter_objects.0.materials.1.key', 'plastic');

        $response->assertJsonFragment(['key' => 'cider_bottle']);
        $response->assertJsonFragment(['key' => 'wine_bottle']);
        $response->assertJsonFragment(['key' => 'spirits_bottle']);
        $response->assertJsonFragment(['key' => 'bottleTop']);
        $response->assertJsonMissing(['key' => 'water_bottle']);
        $response->assertJsonMissing(['key' => 'energyDrink']);
        $response->assertJsonMissing(['key' => 'paper']);
        $response->assertJsonMissing(['key' => 'nylon']);
        $response->assertJsonMissing(['key' => 'butts']);
    }

    public function test_it_returns_a_list_of_tags_for_a_litter_object_without_category (): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $response = $this->get('/api/tags?&object=bottle');

        $response->assertStatus(200);

        $response->assertJsonFragment(['key' => 'alcohol']);
        $response->assertJsonFragment(['key' => 'softdrinks']);
        $response->assertJsonFragment(['key' => 'cider_bottle']);
        $response->assertJsonFragment(['key' => 'wine_bottle']);
        $response->assertJsonFragment(['key' => 'spirits_bottle']);
        $response->assertJsonFragment(['key' => 'water_bottle']);
        $response->assertJsonFragment(['key' => 'energy_bottle']);
        $response->assertJsonMissing(['key' => 'paper']);
        $response->assertJsonMissing(['key' => 'nylon']);
        $response->assertJsonMissing(['key' => 'butts']);
        $response->assertJsonMissing(['key' => 'smoking']);
    }

    public function test_it_returns_a_list_of_tags_for_a_tag_type (): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $response = $this->get('/api/tags?search=beer');

        $response->assertStatus(200);
        $response->assertJsonFragment(['key' => 'alcohol']);
        $response->assertJsonMissing(['key', 'smoking']);
    }

    public function test_it_returns_a_list_of_materials_for_a_litter_object (): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $response = $this->get('/api/tags?materials=aluminium');

        $response->assertStatus(200);
        // add more tests here to ensure only correct tags are loaded. Initial response log looks good.
    }

    public function test_it_searches_across_tags (): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $response = $this->get('/api/tags?search=ba');

        $response->assertStatus(200);
        $response->assertJsonFragment(['key' => 'battery']);
        $response->assertJsonFragment(['bamboo']); // materials does not have a key, it's just an array
        $response->assertJsonMissing(['key' => 'beer_bottle']);
        $response->assertJsonMissing(['key' => 'smoking']);
        $response->assertJsonMissing(['key' => 'alcohol']);
        $response->assertJsonMissing(['key' => 'butts']);
    }

    public function test_it_searches_across_a_category ()
    {
        $this->seed(GenerateTagsSeeder::class);;

        $response = $this->get('/api/tags?category=softdrinks&object=bottle&search=pl');

        $response->assertStatus(200);
    }
}
