<?php

namespace App\Providers;

use App\Services\Clustering\ClusteringService;
use Illuminate\Support\ServiceProvider;

class ClusteringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClusteringService::class, function ($app) {
            return new ClusteringService();
        });
    }
}
