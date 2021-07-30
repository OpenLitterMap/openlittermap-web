<?php

namespace Tests\Unit\Actions;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Models\Photo;
use Tests\TestCase;

class DeleteTagsFromPhotoActionTest extends TestCase
{
    public function test_it_returns_the_correct_number_of_deleted_litter_and_brands()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $addTagsAction->run($photo, [
            'brands' => [
                'adidas' => 5
            ],
            'art' => [
                'item' => 2
            ]
        ]);

        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deletedTags = $deleteTagsAction->run($photo->fresh());

        $this->assertEquals(
            ['all' => 7, 'litter' => 2, 'brands' => 5],
            $deletedTags
        );
    }
}
