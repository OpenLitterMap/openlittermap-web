<?php

namespace App\Console\Commands\Photos;

use Illuminate\Support\Facades\Storage;
use App\Models\Photo;
use Carbon\Carbon;
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
        // Get a batch of images with bounding boxes to resize
        Photo::where([
            ['verified', '>=', 2],
            ['filename', '!=', '/assets/verified.jpg'],
            'five_hundred_square_filepath' => null
        ])->chunk(500, function ($photos)
        {
            foreach ($photos as $photo)
            {
                echo "Photo id $photo->id \n";

                // Create an image object
                $img = Image::make($photo->filename);

                $img->resize(500, 500);

                // Create a temp file
                $img->save('public/1.jpg');

                // Create filename from created_at
                $date = Carbon::parse($photo->created_at);
                $year = $date->year;
                $month = $date->month;
                $day = $date->day;

                $x = explode('/', (string) $photo->filename);

                // Get the last element which is the filename with extension
                $filename = $x[count($x) -1];
                $filepath = $year.'/'.$month.'/'.$day.'/'.$filename;

                $s3 = Storage::disk('bbox');
                $s3->put($filepath, $img, 'public');

                $imageName = $s3->url($filepath);

                $photo->five_hundred_square_filepath = $imageName;
                $photo->save();
            }
        });
    }
}
