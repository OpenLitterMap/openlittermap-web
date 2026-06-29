<?php

namespace App\Listeners\Images;

use App\Events\Images\BadgeCreated;
use App\Helpers\Social;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class TweetBadgeCreated implements ShouldQueue
{
    public function handle(BadgeCreated $event): void
    {
        if (app()->environment('production')) {
            $badge = $event->badge;

            $path = Storage::disk('public')->path($badge->filename);

            Social::withImage("An awesome new #openlittermap badge has been created & unlocked for {$badge->subtype}.", $path);
        }
    }
}
