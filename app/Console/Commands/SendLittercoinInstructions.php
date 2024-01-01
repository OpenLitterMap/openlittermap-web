<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
use App\Models\User\User;
use App\Mail\LittercoinInstructions;
use Illuminate\Console\Command;

class SendLittercoinInstructions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ltrx:sendinstructions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Littercoin Instructions to a User who is owed Littercoin.';

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
            ['littercoin_owed', '>', 1],
            ['littercoin_instructions_received', null]
        ])->get();
        foreach ($users as $user) {
            Mail::to($user->email)->send(new LittercoinInstructions($user));
            $user->littercoin_instructions_received = 1;
            $user->save();
        }

    }
}
