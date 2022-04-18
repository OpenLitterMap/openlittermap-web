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
    protected $signature = 'locations:fix-and-merge-duplicates';

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

            $countries = Country::where('shortcode', $duplicatedCountry->shortcode)
                ->select('id', 'country', 'shortcode')
                ->orderBy('id')
                ->get();

            foreach ($countries as $index => $country)
            {
                echo "Country " . $index . " created at " . \Carbon\Carbon::parse($country->created_at) . " \n";

                $realCountryId = 0;

                if ($index === 0)
                {
                    $realCountryId = $country->id;

                    echo "realCountryId $realCountryId \n";
                }
                else
                {
                    echo "Processing country index: " . $index . " \n";

                    $photos = Photo::where('country_id', $country->id)
                        ->select('id', 'country_id', 'state_id', 'city_id', 'user_id')
                        ->orderBy('id')
                        ->get();

                    echo "Found " . sizeof($photos) . " wrong photos \n";

                    foreach ($photos as $photo)
                    {
                        $realStateId = $this->getRealStateId($photo, $realCountryId);
                        $realCityId = $this->getRealCityId($photo, $realStateId);

                        if ($photo->country_id != $realCountryId)
                        {
                            $photo->country_id = $realCountryId;
                            $photo->save();
                        }

                        if ($photo->state_id != $realStateId)
                        {
                            $photo->state_id = $realStateId;
                            $photo->save();
                        }

                        if ($photo->city_id != $realCityId)
                        {
                            $photo->city_id = $realCityId;
                            $photo->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the state associated with a photo
     */
    protected function getRealStateId (Photo $photo, $realCountryId)
    {
        $stateFromPhoto = State::find($photo->state_id);

        $stateExistsInRealCountry = State::where('state', $stateFromPhoto->state)
            ->where('country_id', $realCountryId)
            ->first();

        if ($stateExistsInRealCountry)
        {
            echo "State $stateFromPhoto->state already exists in realCountryId: $realCountryId \n";
            echo "Replacing state_id: $stateFromPhoto->id with $stateExistsInRealCountry->id \n";

            $realStateId = $stateExistsInRealCountry->id;

            if (is_null($stateExistsInRealCountry->created_by)) {
                $stateExistsInRealCountry->created_by = $photo->user_id;
                $stateExistsInRealCountry->save();
            }
        }
        else
        {
            echo "Creating new state: $stateFromPhoto->state for realCountryId: $realCountryId \n";

            $newState = State::create([
                'state' => $stateFromPhoto->state,
                'country_id' => $realCountryId,
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
