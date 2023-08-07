<?php

namespace App\Providers;

use App\Events\ImageUploaded;
use App\Events\ImageDeleted;
use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Events\TagsVerifiedByAdmin;

use App\Listeners\AddTags\IncrementLocation;
use App\Listeners\AddTags\CompileResultsString;
use App\Listeners\Locations\Twitter\TweetNewCity;
use App\Listeners\Locations\Twitter\TweetNewCountry;
use App\Listeners\Locations\Twitter\TweetNewState;
use App\Listeners\Locations\User\UpdateUserIdLastUpdatedLocation;
use App\Listeners\User\UpdateUserCategories;
use App\Listeners\User\UpdateUserTimeSeries;
use App\Listeners\Littercoin\RewardLittercoin;
use App\Listeners\Locations\AddLocationContributor;
use App\Listeners\Locations\DecreaseLocationTotalPhotos;
use App\Listeners\Locations\NotifySlackOfNewCity;
use App\Listeners\Locations\NotifySlackOfNewCountry;
use App\Listeners\Locations\NotifySlackOfNewState;
use App\Listeners\Locations\RemoveLocationContributor;
use App\Listeners\Locations\IncreaseLocationTotalPhotos;
use App\Listeners\Teams\DecreaseTeamTotalPhotos;
use App\Listeners\Teams\IncreaseTeamTotalLitter;
use App\Listeners\Teams\IncreaseTeamTotalPhotos;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class
        ],
        ImageUploaded::class => [
            // Add user_id to country, state, and city on Redis
            AddLocationContributor::class,

            // Increase total_photos for each country, state and city on Redis
            IncreaseLocationTotalPhotos::class,

            // Update total_images for a team, and total_photos for a TeamUser pivot, on SQL
            // this needs to be migrated to Redis
            IncreaseTeamTotalPhotos::class
        ],
        ImageDeleted::class => [
            RemoveLocationContributor::class,
            DecreaseLocationTotalPhotos::class,
            DecreaseTeamTotalPhotos::class
        ],
        // Several Listeners could be merged. Add ProofOfWork
        TagsVerifiedByAdmin::class => [
            // Use the given tags to create a key-value string pair that can display the tags in any language
            CompileResultsString::class,

            // Increase total_litter, total_brands, and total_category for each location, on Redis
            IncrementLocation::class,

            // Increment total_images total_litter on Team and TeamUser pivot, on SQL
            // this needs to be migrated to Redis
            IncreaseTeamTotalLitter::class,

            // Increase the users Littercoin score
            // Reward with Littercoin if criteria met
            RewardLittercoin::class,

            // Update the users total_litter, total_brands, total_photos and total_category on Redis
            UpdateUserCategories::class,

            // Photos per month, or ppm, needs to be migrated to Redis
            UpdateUserTimeSeries::class,

            // Update the last_user_id_uploaded for each Location
            UpdateUserIdLastUpdatedLocation::class,
        ],
        'App\Events\UserSignedUp' => [
            'App\Listeners\SendNewUserEmail'
        ],
        'App\Events\Photo\IncrementPhotoMonth' => [
            'App\Listeners\UpdateTimes\IncrementCountryMonth',
            'App\Listeners\UpdateTimes\IncrementStateMonth',
            'App\Listeners\UpdateTimes\IncrementCityMonth',
        ],
        NewCountryAdded::class => [
            NotifySlackOfNewCountry::class,
            TweetNewCountry::class
        ],
        NewStateAdded::class => [
            NotifySlackOfNewState::class,
            TweetNewState::class
        ],
        NewCityAdded::class => [
            NotifySlackOfNewCity::class,
            TweetNewCity::class
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot()
    {
        parent::boot();
    }
}
