<?php

namespace Tests\Unit\Actions;

use App\Actions\LogAdminVerificationAction;
use App\Models\AdminVerificationLog;
use App\Models\Photo;
use App\Models\Users\User;
use Tests\TestCase;

/**
 * @group deprecated
 * @deprecated Needs rewrite for v5 — admin routes moved to /api/admin/*,
 *             setUp uses dead routes (/submit, /add-tags)
 */
use PHPUnit\Framework\Attributes\Group;

#[Group('deprecated')]
class LogAdminVerificationActionTest extends TestCase
{
    public function test_it_logs_an_admins_action()
    {
        $admin = User::factory()->create();
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
