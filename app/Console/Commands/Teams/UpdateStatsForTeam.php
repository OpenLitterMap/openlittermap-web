<?php

namespace App\Console\Commands\Teams;

use Illuminate\Support\Facades\DB;
use App\Models\Photo;
use App\Models\Teams\Team;

use Illuminate\Console\Command;

class UpdateStatsForTeam extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:update-for-id {team_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update stats for a specific team_id';

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
        $team = Team::find($this->argument('team_id'));

        foreach ($team->users as $user)
        {
            $query =  Photo::where(['user_id' => $user->id, 'team_id' => $team->id]);
            $total_photos = $query->count();
            $total_litter = $query->sum('total_litter');

            DB::table('team_user')
                ->where(['user_id' => $user->id, 'team_id' => $team->id])
                ->update(['total_photos' => $total_photos, 'total_litter' => $total_litter]);
        }
    }
}
