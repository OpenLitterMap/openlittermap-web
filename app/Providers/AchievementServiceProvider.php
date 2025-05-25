<?php

namespace App\Providers;

use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\AchievementProgressTracker;
use App\Services\Achievements\AchievementRepository;
use App\Services\Achievements\Strategies\DimensionWideAchievementStrategy;
use App\Services\Achievements\Strategies\TagBasedAchievementStrategy;
use App\Services\Achievements\Strategies\UploadsAchievementStrategy;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\ServiceProvider;

class AchievementServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register repository and tracker as singletons
        $this->app->singleton(AchievementRepository::class);
        $this->app->singleton(AchievementProgressTracker::class);

        // Register the main engine as a singleton
        $this->app->singleton(AchievementEngine::class, function ($app) {
            $engine = new AchievementEngine(
                $app->make(AchievementRepository::class),
                $app->make(AchievementProgressTracker::class),
                $app->make(RedisMetricsCollector::class)
            );

            // Register all strategies
            $this->registerStrategies($engine);

            return $engine;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register all achievement strategies
     */
    private function registerStrategies(AchievementEngine $engine): void
    {
        // Upload achievements
        $engine->registerStrategy(new UploadsAchievementStrategy());

        // Dimension-wide achievements
        $engine->registerStrategy(new DimensionWideAchievementStrategy('objects', 'objects', true));
        $engine->registerStrategy(new DimensionWideAchievementStrategy('categories', 'categories', false));
        $engine->registerStrategy(new DimensionWideAchievementStrategy('materials', 'materials', true));
        $engine->registerStrategy(new DimensionWideAchievementStrategy('brands', 'brands', true));

        // Per-tag achievements
        $engine->registerStrategy(new TagBasedAchievementStrategy('object', 'objects'));
        $engine->registerStrategy(new TagBasedAchievementStrategy('category', 'categories'));
        $engine->registerStrategy(new TagBasedAchievementStrategy('material', 'materials'));
        $engine->registerStrategy(new TagBasedAchievementStrategy('brand', 'brands'));
        $engine->registerStrategy(new TagBasedAchievementStrategy('customTag', 'custom_tags'));
    }
}
