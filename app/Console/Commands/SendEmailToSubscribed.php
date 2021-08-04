<?php

namespace App\Console\Commands;

use App\Subscriber;
use App\Models\User\User;
use App\Jobs\Emails\DispatchEmail;

use Illuminate\Console\Command;

class SendEmailToSubscribed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:send-email-to-subscribed';

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
//         $users = User::where('emailsub', 1)->orderBy('id', 'asc')->get();
        $users = Subscriber::all();

        foreach ($users as $user)
        {
            echo "user.id " . $user->id . " \n \n";
            dispatch (new DispatchEmail($user));
        }
    }
}
