<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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
     */
    public function boot(): void
    {
        // I think we can delete this which is duplicated by HorizonServiceProvider.php
        Gate::define('viewWebSocketsDashboard', function ($user = null) {
            return in_array($user->email, [
                'seanlynch@umail.ucc.ie',
                'info@openlittermap.com',
            ]);
        });
    }
}
