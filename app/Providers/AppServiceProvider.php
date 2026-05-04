<?php

namespace App\Providers;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use App\Models\Users\User;
use App\Observers\PhotoObserver;
use App\Services\Clustering\ClusteringService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ClusteringService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Relation::morphMap([
            'brand'      => BrandList::class,
            'material'   => Materials::class,
            'custom_tag' => CustomTagNew::class,
        ]);

        Photo::observe(PhotoObserver::class);

        Cashier::useCustomerModel(User::class);

        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('superadmin');
        });

        RateLimiter::for('ses-emails', function () {
            return Limit::perSecond(12);
        });

        // CSV exports queue jobs + write to S3 + send email — abuse vector if uncapped.
        // Two limits stack so neither can be sidestepped by varying the other dimension:
        //   1. per-(user|ip+email) per-minute — blocks one user/bot hammering one address.
        //      Authenticated users are limited per-account, NOT per-IP, so switching
        //      networks (mobile ↔ laptop on the same login) doesn't multiply the budget.
        //   2. per-ip per-hour — blocks a single ip spraying many victim emails to multiply
        //      its budget under limit (1).
        RateLimiter::for('csv-export', function ($request) {
            $perAddress = $request->user()?->id
                ?? $request->ip() . '|' . strtolower((string) $request->input('email'));
            return [
                Limit::perMinute(3)->by($perAddress),
                Limit::perHour(20)->by($request->ip()),
            ];
        });
    }
}
