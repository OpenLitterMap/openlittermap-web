<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class UpdateAdminsUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:verify-admins-uploads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set all of Seans uploads to verification 2.';

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
        $user = User::find(1);
        $photos = $user->photos()->where('verification', '0.1')->get();
        foreach($photos as $photo) {
            $photo->verification = 1;
            $photo->verified = 2;
            $photo->save();
        }
    }
}
