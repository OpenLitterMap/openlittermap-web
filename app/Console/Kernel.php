<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule (Schedule $schedule): void
    {
//        $schedule->command('sitemap:generate')->daily();
        $schedule->command('twitter:daily-report')->dailyAt('00:00');
        $schedule->command('clusters:generate-all')->dailyAt('00:10');
        $schedule->command('clusters:generate-team-clusters')->dailyAt('00:20');

        $schedule->command('twitter:weekly-impact-report-tweet')->weeklyOn(1, '06:30');
        $schedule->command('twitter:monthly-impact-report-tweet')->monthlyOn(1, '06:30');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands (): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
