<?php

namespace App\Console\Commands\Tags;

use App\Enums\VerificationStatus;
use App\Models\Photo;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Console\Command;

class VerifiyRemainingTagsForUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tags:verify-for-user-id {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify remaining tags for a user_id';

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
        $photos = Photo::where([
            'user_id' => $this->argument('user_id'),
            'verification' => 0.1
        ])->get();

        foreach ($photos as $photo)
        {
            $photo->verification = 1;
            $photo->verified = VerificationStatus::ADMIN_APPROVED->value;
            $photo->save();
            event(new TagsVerifiedByAdmin(
                $photo->id,
                $photo->user_id,
                $photo->country_id,
                $photo->state_id,
                $photo->city_id,
                $photo->team_id
            ));
        }
    }
}
