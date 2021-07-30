<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Passport::routes();
        Passport::tokensExpireIn(now()->addDays(365));
        Passport::refreshTokensExpireIn(now()->addDays(365));

        // I think we can delete this which is duplicated by HorizonServiceProvider.php
        Gate::define('viewWebSocketsDashboard', function ($user = null) {
            return in_array($user->email, [
                'seanlynch@umail.ucc.ie',
                'info@openlittermap.com',
                'nair.anoop1995@gmail.com',
                'jahogeni@gmail.com'
            ]);
        });
    }
}
