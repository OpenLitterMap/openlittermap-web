<?php

namespace App\Console\Commands;

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
    protected $signature = 'country:createdby';

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
        $countries = Country::where('manual_verify', 1)->get();
        foreach($countries as $country) {
            if(is_null($country->created_by)) {
                $country['created_by'] = $country->photos()->first()->user_id;
                $country->save();
            }
        }
    }
}
