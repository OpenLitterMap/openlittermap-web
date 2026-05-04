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
        // All export endpoints are auth-only, so we can key strictly on user_id —
        // switching networks on the same login can't multiply the budget. Falls back
        // to IP only as a paranoid backstop in case a route is ever opened to guests
        // again without updating this limiter.
        RateLimiter::for('csv-export', function ($request) {
            return Limit::perMinute(3)->by($request->user()?->id ?? $request->ip());
        });
    }
}
