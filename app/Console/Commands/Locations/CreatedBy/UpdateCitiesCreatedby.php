<?php

namespace App\Console\Commands\Locations\CreatedBy;

use App\Models\Location\City;
use App\Models\Photo;
use Illuminate\Console\Command;

class UpdateCitiesCreatedby extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:fix-cities-createdby';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the createdby for each manually verified city';

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
        $cities = City::whereNull('created_by')->get();

        foreach ($cities as $city)
        {
            echo "city $city->id $city->city \n";

            $photo = Photo::where('city_id', $city->id)->orderBy('id')->first();

            if ($photo)
            {
                $city->created_by = $photo->user_id;
                $city->save();

                echo "updated \n";
            }
        }
    }
}
