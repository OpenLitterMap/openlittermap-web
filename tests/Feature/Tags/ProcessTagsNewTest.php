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
            'categories' => [
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
        $response->assertJsonPath('category.key', 'alcohol');
        $response->assertJsonFragment(['key' => 'bottle']);
        $response->assertJsonFragment(['key' => 'beer']);
        $response->assertJsonMissing(['key' => 'water']);
    }
}
