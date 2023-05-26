<?php

namespace App\Console\Commands\Admin;

use App\Models\User\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MigrateVerificationCountToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin-temp:move-verification-count-from-sql-to-redis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate the user.count_correctly_verified to redis';

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
     * @return void
     */
    public function handle()
    {
        $users = User::where('count_correctly_verified', '>', 0)->get();

        foreach ($users as $user)
        {
            $verificationCount = Redis::hincrby("user_verification_count", $user->id, $user->count_correctly_verified);

            echo "User_id: $user->id, score: $verificationCount \n";
        }

        echo "Completed.";

        return;
    }
}
