<?php

namespace App\Providers;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Users\User;
use App\Repositories\PhotoMetricsRepo;
use App\Services\Achievements\AchievementEngine;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Illuminate\Contracts\Cache\Repository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PhotoMetricsRepo::class);

        $this->app->bind(AchievementEngine::class, function($app) {
            return new AchievementEngine(
                $app->make(Repository::class),
                $app->make(ExpressionLanguage::class)
            );
        });
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

        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('superadmin');
        });
    }
}
