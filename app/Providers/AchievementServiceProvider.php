<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Achievements\{
    AchievementEngine,
    AchievementRepository
};
use App\Services\Achievements\Checkers\{
    UploadsChecker,
    ObjectsChecker,
    CategoriesChecker,
    MaterialsChecker,
    BrandsChecker,
    CustomTagChecker,
    TypesChecker
};

class AchievementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AchievementRepository::class);

        $this->app->singleton(UploadsChecker::class);
        $this->app->singleton(ObjectsChecker::class);
        $this->app->singleton(CategoriesChecker::class);
        $this->app->singleton(MaterialsChecker::class);
        $this->app->singleton(BrandsChecker::class);
        $this->app->singleton(CustomTagChecker::class);
        $this->app->singleton(TypesChecker::class);

        $this->app->tag([
            UploadsChecker::class,
            ObjectsChecker::class,
            CategoriesChecker::class,
            MaterialsChecker::class,
            BrandsChecker::class,
            CustomTagChecker::class,
            TypesChecker::class,
        ], 'achievement.checker');

        // ── engine gets injected with an iterable of tagged checkers ─────
        $this->app->singleton(AchievementEngine::class, function ($app) {
            return new AchievementEngine(
                $app->make(AchievementRepository::class),
                $app->tagged('achievement.checker'),
            );
        });
    }
}
