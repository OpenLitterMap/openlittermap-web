<?php

namespace App\Console\Commands;

use App\User;
use App\Mail\Update;
use Illuminate\Console\Command;

class SendEmailToSubscribed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:send-subscribed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a specific email template to subscribed users';

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
            ['verified', 1]
        ])->get();

        foreach($users as $user) {
            \Mail::to($user->email)->send(new Update($user));
        }
    }
}
