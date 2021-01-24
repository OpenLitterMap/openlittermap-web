<?php

namespace App\Console\Commands\Users;

use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;

class GenerateTotalsForACategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:generate-totals-for-a-category {category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pass a cateogory, and update total_category for all users for that category';

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

        $categories = Photo::categories();

        $category = $this->argument('category');

        echo "category " . $category . "\n";

        if (!in_array($category, $categories))
        {
            echo "not found \n";
            return;
        }

        echo "found \n";

        foreach ($users as $user)
        {
            echo "User.id " . $user->id . "\n";

            $category_total = 0;
            $category_id =  $category . '_id';
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
            $user->save();

            echo "\n";
        }
    }
}
