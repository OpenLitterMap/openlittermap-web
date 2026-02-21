<?php

namespace App\Listeners\Metrics;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessPhotoMetrics implements ShouldQueue
{
    public function __construct(
        private MetricsService $metricsService
    ) {}

    public function handle(TagsVerifiedByAdmin $event): void
    {
        $photo = Photo::find($event->photo_id);

        if (! $photo) {
            return;
        }

        $this->metricsService->processPhoto($photo);
    }
}
