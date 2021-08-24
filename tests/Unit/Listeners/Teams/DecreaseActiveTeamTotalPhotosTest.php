<?php

namespace Tests\Unit\Listeners\Teams;

use App\Events\ImageDeleted;
use App\Listeners\Teams\DecreaseActiveTeamTotalPhotos;
use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class DecreaseActiveTeamTotalPhotosTest extends TestCase
{
    /**
     * @param User $user
     * @return ImageDeleted
     */
    protected function getEvent(User $user): ImageDeleted
    {
        return new ImageDeleted(
            $user,
            1,
            1,
            1,
        );
    }

    public function test_it_decreases_active_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['total_images' => 1]);

        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertEquals(1, $team->total_images);

        /** @var DecreaseActiveTeamTotalPhotos $listener */
        $listener = app(DecreaseActiveTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $this->assertEquals(0, $team->fresh()->total_images);
    }

    public function test_it_decreases_users_contribution_to_active_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['total_images' => 1]);

        $user->teams()->attach($team, ['total_photos' => 1]);
        $user->active_team = $team->id;
        $user->save();

        $this->assertEquals(1, $user->fresh()->teams->first()->pivot->total_photos);

        /** @var DecreaseActiveTeamTotalPhotos $listener */
        $listener = app(DecreaseActiveTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $this->assertEquals(0, $user->fresh()->teams->first()->pivot->total_photos);
    }

}
