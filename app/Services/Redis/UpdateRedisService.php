<?php

namespace App\Services\Redis;

use App\Models\Photo;
use App\Services\Redis\Actions\AwardGlobalXpService;
use App\Services\Redis\Actions\AwardXpLocationService;
use App\Services\Redis\Actions\RecordDailyActivityService;
use App\Services\Redis\Actions\UpdateTimeSeriesService;
use App\Services\Redis\Actions\UpdateTotalsService;

class UpdateRedisService
{
    protected AwardGlobalXpService $awardGlobalXpService;
    protected AwardXpLocationService $awardXpLocationService;
    protected RecordDailyActivityService $recordDailyActivityService;
    protected UpdateTimeSeriesService $updateTimeSeriesService;
    protected UpdateTotalsService $updateTotalsService;

    public function __construct(
        AwardGlobalXpService $awardGlobalXpService,
        AwardXpLocationService $awardXpLocationService,
        RecordDailyActivityService $recordDailyActivityService,
        UpdateTimeSeriesService $updateTimeSeriesService,
        UpdateTotalsService $updateTotalsService
    ) {
        $this->awardGlobalXpService = $awardGlobalXpService;
        $this->awardXpLocationService = $awardXpLocationService;
        $this->recordDailyActivityService = $recordDailyActivityService;
        $this->updateTimeSeriesService = $updateTimeSeriesService;
        $this->updateTotalsService = $updateTotalsService;
    }

    public function updateRedis(Photo $photo): void
    {
        $this->awardGlobalXpService->run($photo);
        $this->awardXpLocationService->run($photo);
        $this->recordDailyActivityService->run($photo);
        $this->updateTimeSeriesService->run($photo);
        $this->updateTotalsService->run($photo);
    }
}
