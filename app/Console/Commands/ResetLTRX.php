<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class ResetLTRX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ltrx:reset-to-zero';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all LTRX to 0';

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
        $users = User::all();
        foreach($users as $user) {
            $user->littercoin_allowance = 0;
            $user->save();
        }
    }
}
