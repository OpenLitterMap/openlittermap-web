<?php

namespace App\Providers;

use App\Events\ImageDeleted;
use App\Events\ImageUploaded;
use App\Listeners\Locations\AddLocationContributor;
use App\Listeners\Locations\DecreaseLocationTotalPhotos;
use App\Listeners\Locations\RemoveLocationContributor;
use App\Listeners\Locations\IncreaseLocationTotalPhotos;
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
            IncreaseLocationTotalPhotos::class
        ],
        ImageDeleted::class => [
            RemoveLocationContributor::class,
            DecreaseLocationTotalPhotos::class
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
        'App\Events\TagsVerifiedByAdmin' => [
            'App\Listeners\AddTags\UpdateUser',
            'App\Listeners\AddTags\IncrementCity',
            'App\Listeners\AddTags\IncrementState',
            'App\Listeners\AddTags\IncrementCountry',
            // 'App\Listeners\GenerateLitterCoin',
            // 'App\Listeners\UpdateLeaderboardsAdmin', happens on AddTagsTrait
            'App\Listeners\AddTags\CompileResultsString',
            // todo - only call this listener if the user has active_team
            'App\Listeners\AddTags\IncrementUsersActiveTeam',
            'App\Listeners\User\UpdateUserTimeSeries',
            'App\Listeners\User\UpdateUserCategories'
        ],
        'App\Events\ResetTagsCountAdmin' => [ // not using this yet. Need to add a new Reset + Update tags button
            // 'App\Listeners\DecrementUserTags', Add this in when we update UpdateUserTags
            'App\Listeners\UpdateTags\DecrementCity',
            'App\Listeners\UpdateTags\DecrementState',
            'App\Listeners\UpdateTags\DecrementCountry',
            'App\Listeners\UpdateTags\ResetCompileString',
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
