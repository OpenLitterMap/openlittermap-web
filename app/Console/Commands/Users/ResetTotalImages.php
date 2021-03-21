<?php

namespace App\Console\Commands\Locations\CreatedBy\Locations\CreatedBy\Locations\CreatedBy\Users;

use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;

class ResetTotalImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:reset-total-images-uploaded';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate how many images a user has uploaded';

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

        foreach ($users as $user)
        {
            echo "User.id " . $user->id . "\n";
            $count = Photo::where('user_id', $user->id)->count();
            echo "count " . $count . "\n";
            $user->total_images = $count;
            $user->save();
        }
    }
}
