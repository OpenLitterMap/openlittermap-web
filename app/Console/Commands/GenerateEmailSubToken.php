<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class GenerateEmailSubToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:generate-emailsub-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a 30-chr random_str for a users email subscription.';

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
        $users = User::where('sub_token', null)->get();
        foreach($users as $user) {
            if (is_null($user->sub_token)) {
                $user->sub_token = str_random(30);
                $user->save();
            }
        }
    }
}
