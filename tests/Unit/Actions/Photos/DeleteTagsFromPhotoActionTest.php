<?php

namespace Tests\Unit\Actions\Photos;

use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Models\Photo;
use App\Models\Tag;
use Tests\TestCase;

class DeleteTagsFromPhotoActionTest extends TestCase
{
    public function test_it_deletes_the_tags()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $total = $addTagsAction->run($photo, [$tag1->id => 2, $tag2->id => 4,]);

        /** @var AddCustomTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddCustomTagsToPhotoAction::class);
        $addTagsAction->run($photo, ['tag1', 'tag2', 'tag3']);

        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deletedTags = $deleteTagsAction->run($photo);

        $photo->refresh();
        $this->assertCount(0, $photo->fresh()->tags);
        $this->assertEmpty($photo->customTags);
        $this->assertEquals(
            ['all' => 9, 'tags' => 6, 'customTags' => 3],
            $deletedTags
        );
    }
}
