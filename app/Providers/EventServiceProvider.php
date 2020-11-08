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
            'App\Listeners\UpdateCountriesTable',
            // 'App\Listeners\GenerateLitterCoin',
            // 'App\Listeners\SendNewCountryEmail',
            // 'App\Listeners\UpdateSlackChannel',
        ],
        'App\Events\NewStateAdded' => [
            'App\Listeners\UpdateStatesTable',
            // 'App\Listeners\GenerateLitterCoin',
        ],
        'App\Events\NewCityAdded' => [
            'App\Listeners\UpdateCitiesTable',
            // 'App\Listeners\GenerateLitterCoin',
            // 'App\Listeners\SendNewCityEmail'
        ],
        // 'App\Events\DynamicUpdate' => [
        //     'App\Listeners\UpdateUsersTotals',
        // ],

//       stage-1 verification is not up to date
        'App\Events\PhotoVerifiedByUser' => [
            'App\Listeners\UpdateUsersTotals',
            'App\Listeners\UpdateCitiesTotals',
            'App\Listeners\UpdateStatesTotals',
            'App\Listeners\UpdateCountriesTotals',
            'App\Listeners\UpdateLeaderboards',
        ],
        'App\Events\PhotoVerifiedByAdmin' => [
            'App\Listeners\UpdateUsersAdmin',
            'App\Listeners\IncrementCityAdmin',
            'App\Listeners\IncrementStateAdmin',
            'App\Listeners\IncrementCountryAdmin',

//            'App\Listeners\UpdateCitiesAdmin', // Needs refactor
//            'App\Listeners\UpdateStatesAdmin', // Needs refactor
//            'App\Listeners\UpdateCountriesAdmin', // Needs refactor
            // 'App\Listeners\UpdateLocationsAdmin', // todo

            // 'App\Listeners\GenerateLitterCoin',
            // 'App\Listeners\UpdateLeaderboardsAdmin', happens on AddTagsTrait
            'App\Listeners\CompileResultsString'
        ],
        'App\Events\UserSignedUp' => [
            'App\Listeners\SendNewUserEmail'
        ],
        'App\Events\Photo\IncrementPhotoMonth' => [
            'App\Listeners\Photo\IncrementCountryMonth',
            'App\Listeners\Photo\IncrementStateMonth',
            'App\Listeners\Photo\IncrementCityMonth',
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
