<?php

namespace Tests\Unit\Listeners\Teams;

use App\Events\ImageUploaded;
use App\Listeners\Teams\IncreasePhotoTeamTotalPhotos;
use App\Models\Teams\Team;
use App\Models\User\User;
use Carbon\Carbon;
use Tests\TestCase;

class IncreasePhotoTeamTotalPhotosTest extends TestCase
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
            false,
            $user->active_team
        );
    }

    public function test_it_increases_photo_team_total_photos()
    {
        Carbon::setTestNow();

        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertEquals(0, $user->fresh()->team->total_images);

        $updatedAt = $user->fresh()->team->updated_at;

        Carbon::setTestNow(now()->addMinute());

        /** @var IncreasePhotoTeamTotalPhotos $listener */
        $listener = app(IncreasePhotoTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $user->refresh();
        $this->assertEquals(1, $user->team->total_images);
        $this->assertTrue($updatedAt->addMinute()->is($user->team->updated_at));
    }

    public function test_it_increases_users_contribution_to_photo_team_total_photos()
    {
        Carbon::setTestNow();

        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertEquals(0, $user->fresh()->teams->first()->pivot->total_photos);

        $updatedAt = $user->fresh()->teams->first()->pivot->updated_at;

        Carbon::setTestNow(now()->addMinute());

        /** @var IncreasePhotoTeamTotalPhotos $listener */
        $listener = app(IncreasePhotoTeamTotalPhotos::class);

        $listener->handle($this->getEvent($user));

        $user->refresh();
        $this->assertEquals(1, $user->teams->first()->pivot->total_photos);
        $this->assertEquals(
            $updatedAt->addMinute()->toDateTimeString(),
            $user->teams->first()->pivot->updated_at->toDateTimeString()
        );
    }

}
