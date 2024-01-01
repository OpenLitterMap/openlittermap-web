<?php

namespace Tests\Unit\Listeners\Teams;

use App\Events\ImageDeleted;
use App\Listeners\Teams\DecreaseTeamTotalPhotos;
use App\Models\Teams\Team;
use App\Models\User\User;
use Carbon\Carbon;
use Tests\TestCase;

class DecreaseTeamTotalPhotosTest extends TestCase
{
    protected function getEvent(User $user): ImageDeleted
    {
        return new ImageDeleted(
            $user,
            1,
            1,
            1,
            $user->active_team
        );
    }

    public function test_it_decreases_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['total_images' => 1]);

        $user->teams()->attach($team, ['total_photos' => 1]);
        $user->active_team = $team->id;
        $user->save();

        $this->assertSame(1, $team->total_images);

        $oldUpdatedAt = $team->updated_at;

        Carbon::setTestNow(now()->addMinute());

        /** @var DecreaseTeamTotalPhotos $listener */
        $listener = app(DecreaseTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $team->refresh();
        $this->assertSame(0, $team->total_images);
        $this->assertTrue($team->updated_at->greaterThan($oldUpdatedAt));
    }

    public function test_it_decreases_users_contribution_to_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['total_images' => 1]);

        $user->teams()->attach($team, ['total_photos' => 1]);
        $user->active_team = $team->id;
        $user->save();

        $this->assertSame(1, $user->fresh()->teams->first()->pivot->total_photos);

        $oldUpdatedAt = $user->fresh()->teams->first()->pivot->updated_at;

        Carbon::setTestNow(now()->addMinute());

        /** @var DecreaseTeamTotalPhotos $listener */
        $listener = app(DecreaseTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $user->refresh();
        $this->assertSame(0, $user->teams->first()->pivot->total_photos);
        $this->assertTrue(
            $user->teams->first()->pivot->updated_at->greaterThan($oldUpdatedAt)
        );
    }

}
