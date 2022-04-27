<?php

namespace App\Providers;

use App\Events\ImageDeleted;
use App\Events\ImageUploaded;
use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Events\TagsVerifiedByAdmin;
use App\Listeners\AddTags\IncrementLocation;
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
            SendEmailVerificationNotification::class,
        ],
        ImageUploaded::class => [
            AddLocationContributor::class,
            IncreaseLocationTotalPhotos::class,
            IncreaseTeamTotalPhotos::class
        ],
        ImageDeleted::class => [
            RemoveLocationContributor::class,
            DecreaseLocationTotalPhotos::class,
            DecreaseTeamTotalPhotos::class
        ],
        // Several Listeners could be merged. Add ProofOfWork
        TagsVerifiedByAdmin::class => [
            'App\Listeners\AddTags\UpdateUser',
            IncrementLocation::class,
            'App\Listeners\AddTags\CompileResultsString',
            IncreaseTeamTotalLitter::class,
            'App\Listeners\User\UpdateUserTimeSeries',
            'App\Listeners\User\UpdateUserCategories'
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
            NotifySlackOfNewCountry::class
        ],
        NewStateAdded::class => [
            NotifySlackOfNewState::class
        ],
        NewCityAdded::class => [
            NotifySlackOfNewCity::class
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
