<?php

namespace App\Jobs;

use App\Services\Achievements\AchievementEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateUserAchievements implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId
    ) {}

    public function handle(AchievementEngine $engine): void
    {
        $engine->evaluate($this->userId);
    }
}
