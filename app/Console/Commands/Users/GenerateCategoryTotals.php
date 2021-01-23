<?php

namespace App\Console\Commands\Users;

use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;

class GenerateCategoryTotals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:generate-category-totals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the category total counts for all users';

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
        $users = User::all();

        $categories = [
            'alcohol',
            'coastal',
            'coffee',
            'dumping',
            'food',
            'industrial',
            'other',
            'sanitary',
            'softdrinks',
            'smoking'
        ];

        foreach ($users as $user)
        {
            echo "User.id " . $user->id . "\n";

            foreach ($categories as $category)
            {
                $category_id = $category . '_id';
                $category_total = 0;
                $total_category = 'total_' . $category;

                $photos = Photo::where([
                    'verified' => 2,
                    'user_id' => $user->id
                ])->whereNotNull($category_id)->get();

                foreach ($photos as $photo)
                {
                    if ($photo->$category) $category_total += $photo->$category->total();
                }

                echo $category . " total: " . $category_total . "\n";

                $user->$total_category = $category_total;
            }

            $user->save();

            echo " \n";
        }
    }
}
