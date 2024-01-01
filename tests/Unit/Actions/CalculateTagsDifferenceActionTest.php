<?php

namespace Actions;

use Iterator;
use App\Actions\CalculateTagsDifferenceAction;
use Tests\TestCase;

class CalculateTagsDifferenceActionTest extends TestCase
{
    public function tagsDataProvider(): Iterator
    {
        yield 'add new tag' => [
            'oldTags' => [],
            'newTags' => ['smoking' => ['butts' => 3]],
            'removed' => [],
            'added' => ['smoking' => ['butts' => 3]],
            'removedUserXp' => 0,
            'rewardedAdminXp' => 1
        ];
        yield 'increment user tag' => [
            'oldTags' => ['smoking' => ['butts' => 3]],
            'newTags' => ['smoking' => ['butts' => 10]],
            'removed' => ['smoking' => ['butts' => 3]],
            'added' => ['smoking' => ['butts' => 10]],
            'removedUserXp' => 0,
            'rewardedAdminXp' => 1
        ];
        yield 'decrement user tag' => [
            'oldTags' => ['smoking' => ['butts' => 3]],
            'newTags' => ['smoking' => ['butts' => 1]],
            'removed' => ['smoking' => ['butts' => 3]],
            'added' => ['smoking' => ['butts' => 1]],
            'removedUserXp' => 2,
            'rewardedAdminXp' => 1
        ];
        yield 'delete user tag' => [
            'oldTags' => ['smoking' => ['butts' => 3]],
            'newTags' => ['smoking' => ['lighters' => 5]],
            'removed' => ['smoking' => ['butts' => 3]],
            'added' => ['smoking' => ['lighters' => 5]],
            'removedUserXp' => 3,
            'rewardedAdminXp' => 2
        ];
        yield 'add, delete, incr, decr tags' => [
            'oldTags' => ['smoking' => ['butts' => 3, 'lighters' => 1]],
            'newTags' => ['smoking' => ['butts' => 1], 'alcohol' => ['beerBottle' => 2]],
            'removed' => ['smoking' => ['butts' => 3, 'lighters' => 1]],
            'added' => ['smoking' => ['butts' => 1], 'alcohol' => ['beerBottle' => 2]],
            'removedUserXp' => 3,
            'rewardedAdminXp' => 3
        ];
    }

    public function customTagsDataProvider(): Iterator
    {
        yield 'add new tag' => [
            'oldTags' => [],
            'newTags' => ['smokingggg'],
            'removed' => [],
            'added' => ['smokingggg'],
            'removedUserXp' => 0,
            'rewardedAdminXp' => 1
        ];
        yield 'delete user tag' => [
            'oldTags' => ['smokingggg', 'testtt'],
            'newTags' => ['lighters'],
            'removed' => ['smokingggg', 'testtt'],
            'added' => ['lighters'],
            'removedUserXp' => 2,
            'rewardedAdminXp' => 3
        ];
    }

    /**
     * @dataProvider tagsDataProvider
     */
    public function test_it_calculates_tags_diff($oldTags, $newTags, $removed, $added, $removedUserXp, $rewardedAdminXp)
    {
        /** @var CalculateTagsDifferenceAction $action */
        $action = app(CalculateTagsDifferenceAction::class);
        $result = $action->run($oldTags, $newTags, [], []);

        $this->assertEquals($removed, $result['removed']['tags']);
        $this->assertEquals($added, $result['added']['tags']);
        $this->assertEquals($removedUserXp, $result['removedUserXp']);
        $this->assertEquals($rewardedAdminXp, $result['rewardedAdminXp']);
    }

    /**
     * @dataProvider customTagsDataProvider
     */
    public function test_it_calculates_custom_tags_diff($oldTags, $newTags, $removed, $added, $removedUserXp, $rewardedAdminXp)
    {
        /** @var CalculateTagsDifferenceAction $action */
        $action = app(CalculateTagsDifferenceAction::class);
        $result = $action->run([], [], $oldTags, $newTags);

        $this->assertEquals($removed, $result['removed']['customTags']);
        $this->assertEquals($added, $result['added']['customTags']);
        $this->assertEquals($removedUserXp, $result['removedUserXp']);
        $this->assertEquals($rewardedAdminXp, $result['rewardedAdminXp']);
    }
}
