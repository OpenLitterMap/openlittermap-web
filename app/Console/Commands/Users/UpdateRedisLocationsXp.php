<?php

namespace App\Console\Commands\Users;

use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;

class UpdateRedisLocationsXp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-redis-locations-xp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculates users xp based on their photos and tags';

    /**
     * @var UpdateLeaderboardsForLocationAction
     */
    private $updateLeaderboardsAction;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(UpdateLeaderboardsForLocationAction $action)
    {
        parent::__construct();
        $this->updateLeaderboardsAction = $action;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->withProgressBar(User::all(), function (User $user) {
            $user->photos()
                ->with(Photo::categories())
                ->lazyById()
                ->each(function (Photo $photo) {
                    $xp = $this->calculateXp($photo);

                    $this->updateLeaderboardsAction->run($photo, $photo->user_id, $xp);
                });
        });

        return 0;
    }

    private function calculateXp(Photo $photo): int
    {
        $xpFromPhoto = 1;
        $xpFromTags = (int) collect($photo->categories())
            ->filter(function ($category) use ($photo) {
                return $photo->$category;
            })
            ->sum(function ($category) use ($photo) {
                return $photo->$category->total();
            });
        $xpFromCustomTags = $photo->customTags()->count();

        return $xpFromPhoto + $xpFromTags + $xpFromCustomTags;
    }
}
