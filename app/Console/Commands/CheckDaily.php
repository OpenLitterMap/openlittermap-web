<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\User\User;
use Illuminate\Console\Command;

class CheckDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:check-daily-for-upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Every day, check if each contributing user has uploaded.';

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
        $users = User::where([
            ['verified', 1],
            ['has_uploaded', 1]
        ])->get();

        // If user has uploaded, reset the counter
        foreach ($users as $user)
        {
            if ($user->has_uploaded_today == 0)
            {
                $user->has_uploaded_counter = 0;
                $user->save();
            }

            if ($user->has_uploaded_today == 1)
            {
                $user->has_uploaded_today = 0;
                $user->save();
            }
        }
    }
}
