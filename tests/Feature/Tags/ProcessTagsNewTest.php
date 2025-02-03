<?php

namespace Tests\Feature\Tags;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Database\Seeders\Tests\LoadTagsSeeder;

class ProcessTagsNewTest extends TestCase
{
    public function test_it_returns_a_list_of_tags (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $response = $this->get('/api/tags');

        $response->assertStatus(200);
    }

    public function test_it_returns_a_list_of_tags_for_a_category (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $response = $this->get('/api/tags?category=alcohol');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.0.key', 'alcohol');
        $response->assertJsonFragment(['key' => 'bottle']);
        $response->assertJsonFragment(['key' => 'beer']);
        $response->assertJsonMissing(['key' => 'water']);
        $response->assertJsonMissing(['key' => 'nylon']);
    }

    public function test_it_returns_a_list_of_tags_for_a_category_litter_object (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $response = $this->get('/api/tags?category=alcohol&object=bottle');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.0.key', 'alcohol');
        $response->assertJsonPath('tags.0.litter_objects.0.key', 'bottle');
        $response->assertJsonPath('tags.0.litter_objects.0.tag_types.0.key', 'beer');
        $response->assertJsonPath('tags.0.litter_objects.0.tag_types.0.materials.0', 'glass');
        $response->assertJsonMissingPath('tags.0.litter_objects.0.tag_types.0.materials.1', 'plastic');

        $response->assertJsonFragment(['key' => 'cider']);
        $response->assertJsonFragment(['key' => 'wine']);
        $response->assertJsonFragment(['key' => 'spirits']);
        $response->assertJsonMissing(['key' => 'water']);
        $response->assertJsonMissing(['key' => 'energyDrink']);
        $response->assertJsonMissing(['key' => 'paper']);
        $response->assertJsonMissing(['key' => 'nylon']);
        $response->assertJsonMissing(['key' => 'butts']);
    }

    public function test_it_returns_a_list_of_tags_for_a_litter_object_without_category (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $this->assertDatabaseHas('categories', ['key' => 'alcohol']);
        $this->assertDatabaseHas('categories', ['key' => 'softdrinks']);
        $this->assertDatabaseHas('litter_objects', ['key' => 'bottle']);

        $response = $this->get('/api/tags?&object=bottle');

        $response->assertStatus(200);

        $response->assertJsonPath('tags.0.key', 'alcohol');
        $response->assertJsonPath('tags.1.key', 'softdrinks');

        $response->assertJsonFragment(['key' => 'cider']);
        $response->assertJsonFragment(['key' => 'wine']);
        $response->assertJsonFragment(['key' => 'spirits']);
        $response->assertJsonFragment(['key' => 'water']);
        $response->assertJsonFragment(['key' => 'energyDrink']);
        $response->assertJsonMissing(['key' => 'paper']);
        $response->assertJsonMissing(['key' => 'nylon']);
        $response->assertJsonMissing(['key' => 'butts']);
        $response->assertJsonMissing(['key' => 'smoking']);
    }

    public function test_it_returns_a_list_of_tags_for_a_tag_type (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $response = $this->get('/api/tags?tag_type=beer');

        $response->assertStatus(200);
        $response->assertJsonPath('tags.0.key', 'alcohol');
        $response->assertJsonMissing(['key', 'smoking']);
    }

    public function test_it_returns_a_list_of_materials_for_a_litter_object (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $response = $this->get('/api/tags?materials=aluminium');

        $response->assertStatus(200);
        // add more tests here to ensure only correct tags are loaded. Initial response log looks good.
    }

    public function test_it_searches_across_tags (): void
    {
        $this->seed(LoadTagsSeeder::class);

        $response = $this->get('/api/tags?search=ba');

        $response->assertStatus(200);
        $response->assertJsonFragment(['key' => 'battery']);
        $response->assertJsonFragment(['key' => 'baseballCap']);
        $response->assertJsonFragment(['bamboo']); // materials does not have a key, it's just an array
        $response->assertJsonMissing(['key' => 'bottle']);
        $response->assertJsonMissing(['key' => 'smoking']);
        $response->assertJsonMissing(['key' => 'alcohol']);
        $response->assertJsonMissing(['key' => 'butts']);
    }

    public function test_it_searches_across_a_category ()
    {
        $this->seed(LoadTagsSeeder::class);;

        $response = $this->get('/api/tags?category=softdrinks&object=bottle&search=pl');

        $response->assertStatus(200);
    }
}
