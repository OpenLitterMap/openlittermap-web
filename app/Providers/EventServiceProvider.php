<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

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

        'App\Events\NewCountryAdded' => [
            'App\Listeners\AddLocation\UpdateCountriesTable',
            // 'App\Listeners\GenerateLitterCoin',
            // 'App\Listeners\SendNewCountryEmail',
            // 'App\Listeners\UpdateSlackChannel',
        ],
        'App\Events\NewStateAdded' => [
            'App\Listeners\AddLocation\UpdateStatesTable',
            // 'App\Listeners\GenerateLitterCoin',
        ],
        'App\Events\NewCityAdded' => [
            'App\Listeners\AddLocation\UpdateCitiesTable',
            // 'App\Listeners\GenerateLitterCoin',
            // 'App\Listeners\SendNewCityEmail'
        ],
        // 'App\Events\DynamicUpdate' => [
        //     'App\Listeners\UpdateUsersTotals',
        // ],
        // stage-1 verification is not up to date
        'App\Events\PhotoVerifiedByUser' => [
//            'App\Listeners\UpdateUsersTotals',
//            'App\Listeners\UpdateCitiesTotals',
//            'App\Listeners\UpdateStatesTotals',
//            'App\Listeners\UpdateCountriesTotals',
//            'App\Listeners\UpdateLeaderboards',
        ],
        'App\Events\TagsVerifiedByAdmin' => [
            // Todo - generate total tag counts on the event and pass the result into the listeners
            'App\Listeners\AddTags\UpdateUsers',
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
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
