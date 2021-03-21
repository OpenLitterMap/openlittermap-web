<?php

namespace App\Console\Commands\Locations\CreatedBy;

use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;

class UpdateStatesCreatedby extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:fix-states-createdby';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the created by section for each State.';

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
        $states = State::whereNull('created_by')->get();

        foreach ($states as $state)
        {
            echo "state $state->id $state->state \n";

            $photo = Photo::where('state_id', $state->id)->orderBy('id')->first();

            if ($photo)
            {
                $state->created_by = $photo->user_id;
                $state->save();

                echo "updated \n";
            }
        }
    }
}
