<?php

namespace App\Console\Commands\Users;

use App\Models\Photo;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateTimeSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:generate-time-series';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the time-series metadata for a users profile';

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
        $months = [0, '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];

        $users = User::all();

        foreach ($users as $user)
        {
            echo "User.id " . $user->id . " \n";
            $user->photos_per_month = null;

            $photosPerMonth = [];

            $photos = Photo::select('id', 'user_id', 'datetime')
                ->where([
                    'verified' => 2,
                    'user_id' => $user->id
                ])
                ->orderBy('datetime', 'asc')
                ->get();

            $photos = $photos->groupBy(function($val) {
                return Carbon::parse($val->datetime)->format('m-y');
            });

            foreach ($photos as $index => $monthlyPhotos)
            {
                $month = $months[(int)$substr = substr((string) $index,0,2)];
                $year = substr((string) $index,2,5);
                $photosPerMonth[$month.$year] = $monthlyPhotos->count(); // Mar-17
                // $total_photos += $monthlyPhotos->count();
            }

            $user->photos_per_month = json_encode($photosPerMonth);
            $user->save();
        }
    }
}
