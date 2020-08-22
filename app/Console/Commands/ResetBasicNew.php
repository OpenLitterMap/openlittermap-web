<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class ResetBasicNew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:reset-basic-new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset image upload on Basic account back to 1 and 3.';

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
        // Set all Paid Startup Users daily allowance to 5 
        $startupUsers = User::where([
            ['stripe_id', '!=', null],
        ])->get();
        foreach($startupUsers as $startUpUser) {
            if($startUpUser->subscriptions->count() > 0) {
                $subscriptions = $startUpUser->subscriptions;
                foreach($subscriptions as $subscription) {
                    if($subscription["name"] == "Startup") {
                        $startUpUser->images_remaining = 999; // 5
                        $startUpUser->verify_remaining = 10;
                        $startUpUser->save();
                    }
                }
            }
        }

        $basicUsers = User::where('stripe_id', null)->orderBy('xp', 'desc')->get();
        foreach($basicUsers as $index => $user) {

            if($index < 10) {
                $user->images_remaining = 999; // 10
                $user->verify_remaining = 100; // 10
                $user->save();
            } else {

                if (($user->level == 0) || ($user->level == 1) || ($user->level == 2) || ($user->level == 3)) {
                    $user->images_remaining = 999; //1
                    $user->verify_remaining = 999; //3
                }
                if (($user->level == 4) || ($user->level == 5) || ($user->level == 6) || ($user->level == 7))  {
                    $user->images_remaining = 999; //2
                    $user->verify_remaining = 999; //4
                }
                if (($user->level == 8) || ($user->level == 9)) {
                    $user->images_remaining = 999; //2 
                    $user->verify_remaining = 999; //6
                }
                if ($user->level >= 10) {
                    $user->images_remaining = 999; //3
                    $user->verify_remaining = 999   ; //10
                }
            }
            $user->save();
        }
        $this->info('Updated basic users allowces updated depending on their level');
    }
}
