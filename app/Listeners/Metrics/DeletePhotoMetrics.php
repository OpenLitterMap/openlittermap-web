<?php

namespace App\Listeners\Metrics;

use App\Events\ImageDeleted;
use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeletePhotoMetrics implements ShouldQueue
{
    public function __construct(
        private MetricsService $metricsService
    ) {}

    public function handle(ImageDeleted $event): void
    {
        $photo = Photo::find($event->photo_id);

        if (!$photo) {
            return;
        }

        $this->metricsService->deletePhoto($photo);
    }
}
