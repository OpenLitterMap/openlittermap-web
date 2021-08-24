<?php

namespace App\Providers;

use App\Events\ImageDeleted;
use App\Events\ImageUploaded;
use App\Events\TagsDeletedByAdmin;
use App\Events\TagsVerifiedByAdmin;
use App\Listeners\AddTags\IncrementLocation;
use App\Listeners\Locations\AddLocationContributor;
use App\Listeners\Locations\DecreaseLocationTotalPhotos;
use App\Listeners\Locations\RemoveLocationContributor;
use App\Listeners\Locations\IncreaseLocationTotalPhotos;
use App\Listeners\Teams\DecreaseActiveTeamTotalPhotos;
use App\Listeners\Teams\IncreaseActiveTeamTotalPhotos;
use App\Listeners\UpdateTags\DecrementLocation;
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
            IncreaseActiveTeamTotalPhotos::class
        ],
        ImageDeleted::class => [
            RemoveLocationContributor::class,
            DecreaseLocationTotalPhotos::class,
            DecreaseActiveTeamTotalPhotos::class
        ],
        // stage-1 verification is not currently in use
        'App\Events\PhotoVerifiedByUser' => [
//            'App\Listeners\UpdateUsersTotals',
//            'App\Listeners\UpdateCitiesTotals',
//            'App\Listeners\UpdateStatesTotals',
//            'App\Listeners\UpdateCountriesTotals',
//            'App\Listeners\UpdateLeaderboards',
        ],
        // Several Listeners could be merged. Add ProofOfWork
        TagsVerifiedByAdmin::class => [
            'App\Listeners\AddTags\UpdateUser',
            IncrementLocation::class,
            // 'App\Listeners\GenerateLitterCoin',
            // 'App\Listeners\UpdateLeaderboardsAdmin', happens on AddTagsTrait
            'App\Listeners\AddTags\CompileResultsString',
            // todo - only call this listener if the user has active_team
            'App\Listeners\AddTags\IncrementUsersActiveTeam',
            'App\Listeners\User\UpdateUserTimeSeries',
            'App\Listeners\User\UpdateUserCategories'
        ],
        TagsDeletedByAdmin::class => [
            DecrementLocation::class
        ],
        'App\Events\UserSignedUp' => [
            'App\Listeners\SendNewUserEmail'
        ],
        'App\Events\Photo\IncrementPhotoMonth' => [
            'App\Listeners\UpdateTimes\IncrementCountryMonth',
            'App\Listeners\UpdateTimes\IncrementStateMonth',
            'App\Listeners\UpdateTimes\IncrementCityMonth',
        ]
    ];

    /**
     * Register any events for your application.
     */
    public function boot()
    {
        parent::boot();
    }
}
