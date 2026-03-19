<?php

namespace App\Listeners\Metrics;

use App\Events\TagsVerifiedByAdmin;
use App\Jobs\EvaluateUserAchievements;
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
            \Illuminate\Support\Facades\Log::warning('ProcessPhotoMetrics: photo not found', [
                'photo_id' => $event->photo_id,
                'user_id' => $event->user_id,
            ]);

            return;
        }

        $this->metricsService->processPhoto($photo);

        EvaluateUserAchievements::dispatch($photo->user_id);
    }
}
