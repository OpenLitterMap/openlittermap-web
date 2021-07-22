<?php

namespace Tests\Unit\Actions;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Models\Photo;
use Tests\TestCase;

class AddTagsToPhotoActionTest extends TestCase
{
    public function test_it_excludes_brands_from_total_litter_results()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $total = $addTagsAction->run($photo, [
            'brands' => [
                'adidas' => 5
            ],
            'art' => [
                'item' => 2
            ]
        ]);

        $this->assertEquals(2, $total);
    }
}
