<?php

namespace App\Console\Commands\Users;

use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Actions\Locations\UpdateLeaderboardsXpAction;
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
    protected $description = 'Recalculates users xp based on their photos, tags, and bounding boxes';

    /** @var UpdateLeaderboardsForLocationAction */
    private $leaderboardsLocationAction;

    /** @var UpdateLeaderboardsXpAction */
    private $leaderboardsXpAction;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        UpdateLeaderboardsForLocationAction $locationAction,
        UpdateLeaderboardsXpAction $xpAction
    )
    {
        parent::__construct();
        $this->leaderboardsLocationAction = $locationAction;
        $this->leaderboardsXpAction = $xpAction;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->line('Updating XP from photos and tags');

        $this->withProgressBar(User::all(), function (User $user) {
            $user->photos()
                ->with(Photo::categories())
                ->lazyById()
                ->each(function (Photo $photo) {
                    $xp = $this->calculatePhotoAndTagsXp($photo);
                    $this->leaderboardsLocationAction->run($photo, $photo->user_id, $xp);
                });
        });

        $this->line("\nUpdating XP from bounding boxes");

        $this->withProgressBar(User::all(), function (User $user) {
            $addedBoxes = $user->boxes()->count();
            $verifiedBoxes = $user->boxesVerified()->count();

            $this->leaderboardsXpAction->run($user->id, $addedBoxes + $verifiedBoxes);
        });

        return 0;
    }

    /**
     * @param Photo $photo
     * @return int
     */
    private function calculatePhotoAndTagsXp(Photo $photo): int
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
