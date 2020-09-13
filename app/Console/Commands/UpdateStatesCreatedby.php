<?php

namespace App\Console\Commands;

use App\Models\Location\State;
use Illuminate\Console\Command;

class UpdateStatesCreatedby extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'states:createdby';

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
        $states = State::where('manual_verify', 1)->get();
        foreach($states as $state) {
            if(is_null($state->created_by)) {
                $state['created_by'] = $state->photos()->first()->user_id;
                $state->save();
            }
        }
    }
}
