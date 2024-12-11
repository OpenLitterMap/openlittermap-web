<?php

namespace Tests\Feature\Tags;

use Database\Seeders\CategoryLitterObjectSeeder;
use Tests\TestCase;

class AddNewTagsToPhotosTest extends TestCase
{
    /**
     *
     */
    public function test_it_adds_tags_to_a_photo (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $response = $this->post('/api/tags/upload-add-tags', [

        ]);
    }
}
