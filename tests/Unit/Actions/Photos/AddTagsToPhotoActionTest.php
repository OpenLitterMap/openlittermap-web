<?php

namespace Tests\Unit\Actions\Photos;

use Tests\TestCase;
use App\Models\Photo;
use App\Actions\Photos\AddTagsToPhotoAction;

class AddTagsToPhotoActionTest extends TestCase
{
    public function test_it_returns_correct_number_of_litter_and_brands()
    {
        $photo = Photo::factory()->create();

        $addTagsAction = app(AddTagsToPhotoAction::class);
        $totals = $addTagsAction->run($photo, [
            'brands' => [
                'adidas' => 5
            ],
            'art' => [
                'item' => 2
            ]
        ]);

        $this->assertEquals(
            ['all' => 7, 'litter' => 2, 'brands' => 5],
            $totals
        );
    }
}
