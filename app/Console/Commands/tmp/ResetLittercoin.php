<?php

namespace App\Console\Commands\tmp;

use App\Models\Littercoin;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;

class ResetLittercoin extends Command
{
    protected $signature = 'littercoin:reset';

    protected $description = 'Reset the amount of littercoin owed to all users.';

    public function handle()
    {
        Littercoin::truncate();

        $users = User::where('has_uploaded', true)->get();

        foreach ($users as $user)
        {
            $photos = Photo::select('id', 'verified', 'user_id', 'created_at')
                ->where('user_id', $user->id)
                ->where('verified', '>=', 2)
                ->get();

            $photoCount = 0;

            foreach ($photos as $index => $photo)
            {
                $photoCount++;

                if ($photoCount % 100 === 0)
                {
                    echo $index . " \n";

                    Littercoin::firstOrCreate([
                        'user_id' => $user->id,
                        'photo_id' => $photo->id,
                        'created_at' => $photo->created_at
                    ]);
                }
            }
        }
    }
}
