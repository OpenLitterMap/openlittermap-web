<?php

namespace App\Console\Commands\Tags;

use App\Models\Photo;
use App\Models\User\User;
use App\Models\LitterTags;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Console\Command;

class AddTagsForAUsersNewPhotos extends Command
{
    /**
     * Todo - Change category and tags to arrays, and loop over them to add many tags
     */
    protected $signature = 'tags:add-tags-for-a-users-photos {user_id} {category} {tag} {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a users unverified photos with new tags';

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
        $user = User::find($this->argument('user_id'));

        $photos = Photo::where(['user_id' => $user->id, 'verified' => 0])->get();

        echo "Photos: " . sizeof($photos) . "\n";

        $schema = LitterTags::INSTANCE()->getDecodedJSON();

        foreach ($photos as $photo)
        {
            $category = $this->argument('category');

            // Column on photos table to make a relationship with current category eg smoking_id
            $id_table = $schema->$category->id_table;

            // Full class path
            $class = 'App\\Models\\Litter\\Categories\\'.$schema->$category->class;

            // Create and Update the relationship between photo + category table
            if (is_null($photo->$id_table))
            {
                $row = $class::create();
                $photo->$id_table = $row->id;
                $photo->save();
            }
            // If it does exist, get it
            else $row = $class::find($photo->$id_table);

            // Get the column name on the category class
            $column = $this->argument('tag');

            // Update column quantity on the category table
            $row->$column = $this->argument('quantity');
            $row->save();

            // Verify the photos photos
            $photo->verified = 2;
            $photo->verification = 1;
            $photo->save();

            event(new TagsVerifiedByAdmin($photo->id));
        }
    }
}
