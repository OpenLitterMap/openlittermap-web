<?php

namespace App\Traits;

use App\Models\Photo;
use App\Models\User\User;
use App\Models\LitterTags;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Support\Facades\Redis;

trait AddTagsTrait
{
    /**
     * Add or Update tags on an image
     */
    public function addTags ($tags, $photo_id)
    {
        $photo = Photo::find($photo_id);
        $user = User::find($photo->user_id);

        $schema = LitterTags::INSTANCE()->getDecodedJSON();

        $litterTotal = 0;
        foreach ($tags as $category => $items)
        {
            foreach ($items as $column => $quantity)
            {
                // Get the column on photos table to make a relationship with category eg smoking_id
                $id_table = $schema->$category->id_table;

                // Get the full path of the category
                $class = 'App\\Models\\Litter\\Categories\\' . $schema->$category->class;

                // If photos.$id_table does not exist yet,
                // Create a new row on category.id
                // and save category.id on photos.$id_table
                if (is_null($photo->$id_table))
                {
                    $row = $class::create();
                    $photo->$id_table = $row->id;
                    $photo->save();
                }

                // If the relationship already exists, get the row on the Category
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
                    Redis::zadd($country->country . ':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country . ':' . $state->state . ':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country . ':' . $state->state . ':' . $city->city . ':Leaderboard', $user->xp, $user->id);
                }

                $litterTotal += $quantity;
            }
        }

        $user->xp += $litterTotal; // we are duplicating this if we are updating tags....
        $user->save();

        // photo->verified_by ;
        $photo->total_litter = $litterTotal;
        $photo->result_string = null; // Updated on PhotoVerifiedByAdmin only. Must be reset if we are applying new tags.
        $photo->save();
    }
}
