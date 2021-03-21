<?php

namespace App\Console\Commands\Locations\CreatedBy\Locations\CreatedBy\Locations\CreatedBy\Users;

use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;

class ResetTotalLitterCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:reset-total-litter-count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the total_litter count for each user, based on each total_category';

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
        $users = User::all();

        $categories = Photo::categories();

        foreach ($users as $user)
        {
            echo "User.id " . $user->id . "\n";

            $total = 0;

            foreach ($categories as $category)
            {
                $category_total = "total_". $category;

                // dont include brands in total_litter
                if ($category !== 'brands')
                {
                    if ($user->$category_total)
                    {
                        $total += $user->$category_total;
                    }
                }
            }

            echo "Total " . $total . "\n";

            $user->total_litter = $total;
            $user->save();
        }
    }
}
