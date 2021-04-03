<?php

namespace App\Console\Commands\Photos;

use App\Models\Photo;
use Illuminate\Console\Command;
use Image;

class Resize500x500 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:resize-to-500x500';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Take any level 3 images that are not resized yet, and create 500x500 versions of them for AI';

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
     * @return int
     */
    public function handle()
    {
        $photo = Photo::find(1);

        $img = Image::make($photo->filename);

        $img->resize(500, 500);

        $img->save('public/1.jpg');
    }
}
