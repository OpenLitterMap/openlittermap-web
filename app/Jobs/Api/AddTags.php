<?php

namespace App\Jobs\Api;

use App\Models\User\User;
use App\Models\Photo;

use App\Models\LitterTags;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;

use App\Events\TagsVerifiedByAdmin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Redis;

class AddTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $request, $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct ($request, $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle ()
    {
        $user = User::find($this->userId);

        $photo = Photo::find($this->request['photo_id']);

        $schema = LitterTags::INSTANCE()->getDecodedJSON();

        $litterTotal = 0;
        foreach ($this->request['litter'] as $category => $items)
        {
            foreach ($items as $column => $quantity)
            {
                // Column on photos table to make a relationship with current category eg smoking_id
                $id_table = $schema->$category->id_table;

                // Full class path
                $class = 'App\\Models\\Litter\\Categories\\'.$schema->$category->class;

                // Create reference to category.$id_table on photos if it does not exist
                if (is_null($photo->$id_table))
                {
                    $row = $class::create();
                    $photo->$id_table = $row->id;
                    $photo->save();
                }

                // If it does exist, get it
                else $row = $class::find($photo->$id_table);

                // Update quantity on the category table
                $row->$column = $quantity;
                $row->save();

                // Update Leaderboards if user has public privacy settings
                // todo - save data per leaderboard
                if (($user->show_name) || ($user->show_username))
                {
                    $country = Country::find($photo->country_id);
                    $state = State::find($photo->state_id);
                    $city = City::find($photo->city_id);
                    Redis::zadd($country->country.':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country.':'.$state->state.':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country.':'.$state->state.':'.$city->city.':Leaderboard', $user->xp, $user->id);
                }

                $litterTotal += $quantity;
            }
        }

        $user->xp += $litterTotal;
        $user->save();

        $photo->total_litter = $litterTotal;

        if ($user->verification_required)
        {
            /* Bring the photo to an initial state of verification */
            /* 0 for testing, 0.1 for production */
            $photo->verification = 0.1;
        }

        else // the user is trusted. Dispatch event to update OLM.
        {
            $photo->verification = 1;
            $photo->verified = 2;
            event(new TagsVerifiedByAdmin($photo->id));
        }

        $photo->save();
    }
}
