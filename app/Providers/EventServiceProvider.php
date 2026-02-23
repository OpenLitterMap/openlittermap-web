<?php

namespace App\Providers;

use App\Events\ImageUploaded;
use App\Events\ImageDeleted;
use App\Events\Images\BadgeCreated;
use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Events\SchoolDataApproved;
use App\Events\TagsVerifiedByAdmin;
use App\Events\UserSignedUp;

use App\Listeners\Images\TweetBadgeCreated;
use App\Listeners\Littercoin\RewardLittercoin;
use App\Listeners\Locations\Twitter\TweetNewCity;
use App\Listeners\Locations\Twitter\TweetNewCountry;
use App\Listeners\Locations\Twitter\TweetNewState;
use App\Listeners\Metrics\ProcessPhotoMetrics;
use App\Listeners\NotifyTeamOfApproval;
use App\Listeners\SendNewUserEmail;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Phase 1: Upload — photo created, no tags yet
        // New locations dispatched from UploadPhotoController if wasRecentlyCreated
        ImageUploaded::class => [
        ],

        // Phase 2: Tag finalization — tags verified by admin or trusted user
        TagsVerifiedByAdmin::class => [
            // Single writer for all metrics (MySQL + Redis)
            ProcessPhotoMetrics::class,

            // Littercoin rewards — separate domain concern
            RewardLittercoin::class,
        ],

        // Phase 3: Delete — metrics reversal is synchronous in controllers
        // (MetricsService::deletePhoto called before soft-delete)
        ImageDeleted::class => [
        ],

        // School team approval — notify team members
        SchoolDataApproved::class => [
            NotifyTeamOfApproval::class,
        ],

        // New location notifications (dispatched from UploadPhotoController)
        NewCountryAdded::class => [
            TweetNewCountry::class,
        ],
        NewStateAdded::class => [
            TweetNewState::class,
        ],
        NewCityAdded::class => [
            TweetNewCity::class,
        ],

        UserSignedUp::class => [
            SendNewUserEmail::class,
        ],

        BadgeCreated::class => [
            TweetBadgeCreated::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
