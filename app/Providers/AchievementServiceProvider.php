<?php

namespace App\Providers;

use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\AchievementRepository;
use App\Services\Achievements\Checkers\BrandsChecker;
use App\Services\Achievements\Checkers\CategoriesChecker;
use App\Services\Achievements\Checkers\CustomTagChecker;
use App\Services\Achievements\Checkers\MaterialsChecker;
use App\Services\Achievements\Checkers\ObjectsChecker;
use App\Services\Achievements\Checkers\UploadsChecker;
use Illuminate\Support\ServiceProvider;

class AchievementServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register repository as singleton
        $this->app->singleton(AchievementRepository::class);

        // Register the main engine as a singleton
        $this->app->singleton(AchievementEngine::class, function ($app) {
            $engine = new AchievementEngine(
                $app->make(AchievementRepository::class)
            );

            // Register all checkers
            $this->registerCheckers($engine);

            return $engine;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // You could add event listeners here later
        // For example, to process achievements after photo upload
    }

    /**
     * Register all achievement checkers
     */
    private function registerCheckers(AchievementEngine $engine): void
    {
        // Basic achievements
        $engine->registerChecker(new UploadsChecker());

        // Object achievements (both dimension-wide and per-tag)
        $engine->registerChecker(new ObjectsChecker());

        // Category achievements (both dimension-wide and per-tag)
        $engine->registerChecker(new CategoriesChecker());

        // Material achievements (both dimension-wide and per-tag)
        $engine->registerChecker(new MaterialsChecker());

        // Brand achievements (both dimension-wide and per-tag)
        $engine->registerChecker(new BrandsChecker());

        $engine->registerChecker(new CustomTagChecker());

        // Future checkers can be added here:
        // $engine->registerChecker(new StreakChecker());
        // $engine->registerChecker(new LocationChecker());
    }
}
