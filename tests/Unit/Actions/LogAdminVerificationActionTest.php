<?php

namespace Actions;

use App\Actions\LogAdminVerificationAction;
use App\Models\AdminVerificationLog;
use App\Models\Photo;
use App\Models\User\User;
use Tests\TestCase;

class LogAdminVerificationActionTest extends TestCase
{
    public function test_it_logs_an_admins_action()
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        /** @var Photo $photo */
        $photo = Photo::factory()->create();
        $addedTags = [
            'tags' => ['smoking' => ['butts' => 3]],
            'customTags' => 'nice-tag'
        ];
        $removedTags = [
            'tags' => ['smoking' => ['lighters' => 1]],
            'customTags' => 'tag'
        ];
        $removedUserXp = 100;
        $rewardedAdminXp = 50;

        /** @var LogAdminVerificationAction $action */
        $action = app(LogAdminVerificationAction::class);
        $action->run(
            $admin,
            $photo,
            'verifycorrect',
            $addedTags,
            $removedTags,
            $rewardedAdminXp,
            $removedUserXp
        );

        $log = AdminVerificationLog::first();
        $this->assertInstanceOf(AdminVerificationLog::class, $log);
        $this->assertEquals($addedTags, $log->added_tags);
        $this->assertEquals($removedTags, $log->removed_tags);
        $this->assertEquals($removedUserXp, $log->removed_user_xp);
        $this->assertEquals($rewardedAdminXp, $log->rewarded_admin_xp);
    }
}
