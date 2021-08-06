<?php

namespace Tests\Unit\Actions\Photos;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Models\Photo;
use Tests\TestCase;

class AddTagsToPhotoActionTest extends TestCase
{
    public function test_it_returns_correct_number_of_litter_and_brands()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();

        /** @var AddTagsToPhotoAction $addTagsAction */
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
