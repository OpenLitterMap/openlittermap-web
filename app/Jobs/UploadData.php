<?php

namespace App\Jobs;

use App\User;
use App\Photo;
use App\Country;
use App\State;
use App\City;
use App\Litterrata;
use App\Events\ImageUploaded;
use App\Events\PhotoVerifiedByAdmin;
use Illuminate\Support\Facades\Redis;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UploadData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $request;
    public $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request, $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = User::find($this->userId);
        $photo = Photo::find($this->request['photo_id']);
        $jsonDecoded = Litterrata::INSTANCE()->getDecodedJSON();

        $litterTotal = 0;
        foreach ($this->request['litter'] as $category => $values) {
        $total = 0;
        foreach ($values as $item => $quantity) { // Butts => 3
            // reference column on the photos table to update eg. smoking_id
            $id     = $jsonDecoded->$category->id;
            $clazz  = $jsonDecoded->$category->class;
            $col    = $jsonDecoded->$category->types->$item->col;
            $dynamicClassName = 'App\\Categories\\'.$clazz;

            if (is_null($photo->$id)) {
                $row = $dynamicClassName::create();
                $photo->$id = $row->id;
                $photo->save();
            } else {
                $row = $dynamicClassName::find($photo->$id);
            }
            // Update the quantity on the dynamic table and save
            $row->$col = $quantity;
            $row->save();
            // TODO - Only reward XP on verification.
            $user->xp += $quantity;
            $user->save();

            // todo - Update Leaderboards if user has changed privacy settings
            if (($user->show_name == 1) || ($user->show_username == 1)) {
                $country = Country::find($photo->country_id);
                $state = State::find($photo->state_id);
                $city = City::find($photo->city_id);
                Redis::zadd($country->country.':Leaderboard', $user->xp, $user->id);
                Redis::zadd($country->country.':'.$state->state.':Leaderboard', $user->xp, $user->id);
                Redis::zadd($country->country.':'.$state->state.':'.$city->city.':Leaderboard', $user->xp, $user->id);
            }
            $litterTotal += $quantity;
        }

        // $photo->remaining = true;
        $photo->total_litter = $litterTotal;

        // Check if the User is a trusted user => photos do not require verification.
        if($user->verification_required == 0) {
            $photo->verification = 1;
            $photo->verified = 2;
            event(new PhotoVerifiedByAdmin($photo->id));
        } else {
            // Bring the photo to an initial state of verification
            /* 0 for testing, 0.1 for production */
            $photo->verification = 0.1;
        }

        $photo->save();
        }
    }
}
