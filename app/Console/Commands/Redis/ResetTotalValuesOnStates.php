<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ResetTotalValuesOnStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:reset-total-on-states';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For each state, move all total values to redis';

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
        $states = State::select('id')->where('manual_verify', 1)->get();

        foreach ($states as $state)
        {
            $query = Photo::where([
                ['verified', '>=', 2],
                'state_id' => $state->id
            ]);

            $total_photos = $query->count();
            $total_litter = $query->sum('total_litter');

            Redis::hincrby("state:$state->id", "total_photos", $total_photos);
            Redis::hincrby("state:$state->id", "total_litter", $total_litter);
        }
    }
}
