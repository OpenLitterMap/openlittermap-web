<?php

namespace App\Console\Commands\Locations;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMergeLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:fix-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge locations together and delete the old ones';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $duplicatedCountries = DB::table('countries')
            ->select('shortcode', DB::raw('COUNT(*) as `count`'))
            ->groupBy('shortcode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        echo "Found " . sizeof($duplicatedCountries) . " duplicates \n";

        foreach ($duplicatedCountries as $duplicatedCountry)
        {
            echo "Shortcode: " . $duplicatedCountry->shortcode . " found " . $duplicatedCountry->count . " times \n";

            // Get All Countries, First + Duplicates.
            $countries = Country::where('shortcode', $duplicatedCountry->shortcode)
                ->select('id', 'country', 'shortcode')
                ->orderBy('id')
                ->get();

            $firstCountryId = $countries[0]->id;

            foreach ($countries as $index => $country)
            {
                echo "Processing country #: " . $index . " \n";

                if ($index === 0)
                {
                    echo "Skipping first country id = $firstCountryId \n";
                }
                else
                {
                    // Get the states for the duplicated countries
                    $states = State::where('country_id', $country->id)->get();

                    echo sizeof($states) . " states found \n";

                    foreach ($states as $state)
                    {
                        // Check for duplicate states to see which one is earliest
                        $firstState = State::where('state', $state->state)
                            ->whereIn('country_id', $countries->pluck('id')->toArray())
                            ->orderBy('id')
                            ->first();

                        $firstStateId = $firstState->id;
                        $firstState->country_id = $firstCountryId;
                        $firstState->save();

                        // Get cities for each state
                        $cities = City::where('state_id', $state->id)->get();

                        foreach ($cities as $city)
                        {
                            // Check if there is a duplicate of the city
                            $firstCity = City::where('city', $city->city)
                                ->whereIn('state_id', $states->pluck('id')->toArray())
                                ->orderBy('id')
                                ->first();

                            $firstCityId = $firstCity->id;
                            $firstCity->state_id = $firstStateId;
                            $firstCity->country_id = $firstCountryId;
                            $firstCity->save();

                            $photos = Photo::where('city_id', $city->id)->get();

                            foreach ($photos as $photo)
                            {
                                $photo->country_id = $firstCountryId;
                                $photo->state_id = $firstStateId;
                                $photo->city_id = $firstCityId;
                                $photo->save();
                            }

                            // delete duplicate for city
                        }

                        // delete duplicates for States.
                    }
                }
            }
        }
    }

    /**
     * Get the state associated with a photo
     */
    protected function getRealStateId (Photo $photo, $firstCountryId)
    {
        $stateFromPhoto = State::find($photo->state_id);

        $stateExistsInRealCountry = State::where('state', $stateFromPhoto->state)
            ->where('country_id', $firstCountryId)
            ->first();

        if ($stateExistsInRealCountry)
        {
            echo "State $stateFromPhoto->state already exists in firstCountryId: $firstCountryId \n";
            echo "Replacing state_id: $stateFromPhoto->id with $stateExistsInRealCountry->id \n";

            $realStateId = $stateExistsInRealCountry->id;

            if (is_null($stateExistsInRealCountry->created_by)) {
                $stateExistsInRealCountry->created_by = $photo->user_id;
                $stateExistsInRealCountry->save();
            }
        }
        else
        {
            echo "Creating new state: $stateFromPhoto->state for firstCountryId: $firstCountryId \n";

            $newState = State::create([
                'state' => $stateFromPhoto->state,
                'country_id' => $firstCountryId,
                'created_by' => $photo->user_id
            ]);

            echo "New state_id is: $newState->id \n";

            $realStateId = $newState->id;
        }

        return $realStateId;
    }

    /**
     * Get the city associated with the photo
     *
     * @param Photo $photo
     * @param $realStateId
     * @return int
     */
    protected function getRealCityId (Photo $photo, $realStateId)
    {
        $cityFromPhoto = City::find($photo->city_id);

        if ($cityFromPhoto->state_id != $realStateId)
        {
            $cityFromPhoto->state_id = $realStateId;
            $cityFromPhoto->save();
        }

        $cityExistsWithinState = City::where('city', $cityFromPhoto->city)
            ->where('state_id', $realStateId)
            ->first();

        if ($cityExistsWithinState) {
            echo "City $cityFromPhoto->city already exists in realStateId: $realStateId \n";
            echo "Replacing state_id: $cityFromPhoto->id with $cityExistsWithinState->id \n";

            $realCityId = $cityExistsWithinState->id;

            if (is_null($cityExistsWithinState->created_by)) {
                $cityExistsWithinState->created_by = $photo->user_id;
                $cityExistsWithinState->save();
            }
        } else {
            echo "Creating new city: $cityFromPhoto->city for realStateId: $realStateId \n";

            $newCity = City::create([
                'city' => $cityFromPhoto->city,
                'state_id' => $realStateId,
                'created_by' => $photo->user_id
            ]);

            echo "New city_id is: $newCity->id \n";

            $realCityId = $newCity->id;
        }

        return $realCityId;
    }
}
