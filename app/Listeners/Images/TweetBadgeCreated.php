<?php

namespace App\Listeners\Images;

use App\Events\Images\BadgeCreated;
use App\Helpers\Twitter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class TweetBadgeCreated implements ShouldQueue
{
    public function handle(BadgeCreated $event): void
    {
        if (app()->environment('production')) {
            $badge = $event->badge;

            $path = Storage::disk('public')->path($badge->filename);

            Twitter::sendTweetWithImage("An awesome new #openlittermap badge has been created & unlocked for {$badge->subtype}.", $path);
        }
    }
}
