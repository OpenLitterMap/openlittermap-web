<?php

namespace App\Console\Commands;

use App\Models\Location\City;
use Illuminate\Console\Command;

class UpdateCitiesCreatedby extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cities:createdby';

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
        $cities = City::where([
            ['manual_verify', 1],
            ['total_contributors', '>', 1],
            ['total_images', '>', 0]
        ])->get();
        foreach($cities as $city) {
            if(is_null($city->created_by)) {
                $city['created_by'] = $city->photos()->first()->user_id;
                $city->save();
            }
        }
    }
}
