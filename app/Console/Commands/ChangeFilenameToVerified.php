<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User;
use App\Photo;

class ChangeFilenameToVerified extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:changefilenametoverifiedfordeleted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change the filename to verified.jpg for images accidentally deleted.';

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
        // $photos = Photo::whereDate('created_at', '<', date('2018-1-10'))->get();
        dd('hello!');
        // foreach($photos as $photo) {
        //     $photo->filename = "/assets/verified.jpg";
        //     $photo->save();
        // }
    }
}
