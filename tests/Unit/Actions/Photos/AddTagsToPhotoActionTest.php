<?php

namespace Tests\Unit\Actions\Photos;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Models\Photo;
use App\Models\Tag;
use Tests\TestCase;

class AddTagsToPhotoActionTest extends TestCase
{
    public function test_it_returns_correct_number_of_tags_added()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $total = $addTagsAction->run($photo, [
            $tag1->id => 2,
            $tag2->id => 4,
        ]);

        $this->assertEquals(6, $total);
        $this->assertEquals(
            [$tag1->id, $tag2->id],
            $photo->fresh()->tags->pluck('id')->toArray()
        );
    }
}
