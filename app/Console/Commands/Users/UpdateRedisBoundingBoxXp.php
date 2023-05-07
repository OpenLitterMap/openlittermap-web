<?php

namespace App\Console\Commands\Users;

use App\Actions\Locations\UpdateLeaderboardsXpAction;
use App\Models\User\User;
use Illuminate\Console\Command;

class UpdateRedisBoundingBoxXp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-redis-bounding-box-xp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculates users xp based on the bounding boxes they\'ve added and verified';

    /** @var UpdateLeaderboardsXpAction */
    private $leaderboardsXpAction;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(UpdateLeaderboardsXpAction $xpAction)
    {
        parent::__construct();
        $this->leaderboardsXpAction = $xpAction;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->line("Updating XP from bounding boxes");

        $this->withProgressBar(User::all(), function (User $user) {
            $addedBoxes = $user->boxes()->count();
            $verifiedBoxes = $user->boxesVerified()->count();

            $this->leaderboardsXpAction->run($user->id, $addedBoxes + $verifiedBoxes);
        });

        return 0;
    }
}
