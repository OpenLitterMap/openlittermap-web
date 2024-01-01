<?php

namespace Tests\Unit\Listeners\Teams;

use App\Events\ImageUploaded;
use App\Listeners\Teams\IncreaseTeamTotalPhotos;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\User\User;
use Carbon\Carbon;
use Tests\TestCase;

class IncreaseTeamTotalPhotosTest extends TestCase
{
    protected function getEvent(User $user): ImageUploaded
    {
        /** @var Photo $photo */
        $photo = Photo::factory()->create();
        /** @var Country $country */
        $country = Country::factory()->create();
        /** @var State $state */
        $state = State::factory()->create();
        /** @var City $city */
        $city = City::factory()->create();
        return new ImageUploaded($user, $photo, $country, $state, $city);
    }

    public function test_it_increases_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();
        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertSame(0, $user->fresh()->team->total_images);
        $oldUpdatedAt = $user->fresh()->team->updated_at;
        Carbon::setTestNow(now()->addMinute());

        /** @var IncreaseTeamTotalPhotos $listener */
        $listener = app(IncreaseTeamTotalPhotos::class);
        $listener->handle($this->getEvent($user));

        $user->refresh();
        $this->assertSame(1, $user->team->total_images);
        $this->assertTrue($user->team->updated_at->greaterThan($oldUpdatedAt));
    }

    public function test_it_increases_users_contribution_to_team_total_photos()
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Team $team */
        $team = Team::factory()->create();
        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->assertSame(0, $user->fresh()->teams->first()->pivot->total_photos);
        $oldUpdatedAt = $user->fresh()->teams->first()->pivot->updated_at;

        Carbon::setTestNow(now()->addMinute());

        /** @var IncreaseTeamTotalPhotos $listener */
        $listener = app(IncreaseTeamTotalPhotos::class);
        $listener->handle($this->getEvent($user));

        $user->refresh();
        $this->assertSame(1, $user->teams->first()->pivot->total_photos);
        $this->assertTrue(
            $user->teams->first()->pivot->updated_at->greaterThan($oldUpdatedAt)
        );
    }

}
