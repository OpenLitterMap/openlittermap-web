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

        $text = json_decode($response->getContent(), true);

        Log::info($text);

        $response->assertStatus(200);
    }
}
