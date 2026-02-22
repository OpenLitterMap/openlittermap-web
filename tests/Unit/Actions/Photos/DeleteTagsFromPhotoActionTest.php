<?php

namespace Tests\Unit\Actions\Photos;

use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Models\Photo;
use Tests\TestCase;

/**
 * @group deprecated
 * @deprecated Needs rewrite for v5 — admin routes moved to /api/admin/*,
 *             setUp uses dead routes (/submit, /add-tags)
 */
use PHPUnit\Framework\Attributes\Group;

#[Group('deprecated')]
class DeleteTagsFromPhotoActionTest extends TestCase
{
    public function test_it_deletes_the_tags()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();
        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $addTagsAction->run($photo, [
            'brands' => ['adidas' => 5],
            'art' => ['item' => 2]
        ]);
        /** @var AddCustomTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddCustomTagsToPhotoAction::class);
        $addTagsAction->run($photo, ['tag1', 'tag2', 'tag3']);

        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deleteTagsAction->run($photo->fresh());

        $photo->refresh();
        $this->assertNull($photo->brands);
        $this->assertNull($photo->art);
        $this->assertEmpty($photo->customTags);
    }

    public function test_it_returns_the_correct_number_of_deleted_litter_brands_and_custom_tags()
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $addTagsAction->run($photo, [
            'brands' => ['adidas' => 5],
            'art' => ['item' => 2]
        ]);
        /** @var AddCustomTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddCustomTagsToPhotoAction::class);
        $addTagsAction->run($photo, ['tag1', 'tag2', 'tag3']);

        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deletedTags = $deleteTagsAction->run($photo->fresh());

        $this->assertEquals(
            ['all' => 10, 'litter' => 2, 'brands' => 5, 'custom' => 3],
            $deletedTags
        );
    }
}
