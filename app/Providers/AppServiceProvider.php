<?php

namespace App\Providers;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use App\Models\Users\User;
use App\Observers\PhotoObserver;
use App\Repositories\PhotoMetricsRepo;
use App\Services\Clustering\ClusteringService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(PhotoMetricsRepo::class);
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

        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('superadmin');
        });
    }
}
