<?php

namespace Tests\Unit\Listeners\Teams;

use App\Events\ImageUploaded;
use App\Listeners\Teams\IncreaseActiveTeamTotalPhotos;
use App\Models\Teams\Team;
use App\Models\User\User;
use Tests\TestCase;

class IncreaseActiveTeamTotalPhotosTest extends TestCase
{
    /**
     * @param User $user
     * @return ImageUploaded
     */
    protected function getEvent(User $user): ImageUploaded
    {
        return new ImageUploaded(
            'city',
            'state',
            'country',
            'countryCode',
            'imageName',
            'teamName',
            $user->id,
            1,
            1,
            1,
            false
        );
    }

    public function test_it_increases_active_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertEquals(0, $user->fresh()->team->total_images);

        /** @var IncreaseActiveTeamTotalPhotos $listener */
        $listener = app(IncreaseActiveTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $this->assertEquals(1, $user->fresh()->team->total_images);
    }

    public function test_it_increases_users_contribution_to_active_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertEquals(0, $user->fresh()->teams->first()->pivot->total_photos);

        /** @var IncreaseActiveTeamTotalPhotos $listener */
        $listener = app(IncreaseActiveTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $this->assertEquals(1, $user->fresh()->teams->first()->pivot->total_photos);
    }

}
