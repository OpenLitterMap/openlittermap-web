<?php

namespace App\Console\Commands\Locations\CreatedBy;

use App\Models\Location\Country;
use App\Models\Photo;
use Illuminate\Console\Command;

class UpdateCountryCreatedby extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:fix-countries-createdby';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the created by column for each country';

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
     * @return mixed
     */
    public function handle()
    {
        $countries = Country::whereNull('created_by')->get();

        foreach ($countries as $country)
        {
            echo "country $country->id $country->country \n";

            $photo = Photo::where('country_id', $country->id)->orderBy('id')->first();

            if ($photo)
            {
                $country->created_by = $photo->user_id;
                $country->save();

                echo "updated \n";
            }
        }
    }
}
