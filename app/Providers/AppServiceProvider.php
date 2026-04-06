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
    }
}
