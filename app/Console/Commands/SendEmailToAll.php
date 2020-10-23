<?php

namespace App\Console\Commands;

use App\Models\User\User;
use App\Mail\SmallUpdate;
use Illuminate\Console\Command;

class SendEmailToAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:send-email-to-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an email to all users that are subscribed.';

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
            ['id', '>', 60],
            'emailsub' => 1
        ])->get();

        foreach ($users as $user)
        {
            echo "user.id " . $user->id . " \n \n";
            \Mail::to($user->email)->send(new SmallUpdate($user));
        }
    }
}
