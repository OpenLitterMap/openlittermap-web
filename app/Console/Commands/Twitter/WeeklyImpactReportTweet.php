<?php

declare(strict_types=1);

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Spatie\Browsershot\Browsershot;

class WeeklyImpactReportTweet extends Command
{
    protected $signature = 'twitter:weekly-impact-report-tweet';

    protected $description = 'Generates an image of the weekly impact report and tweets it via OLM_bot';

    public function handle(): int
    {
        if (! app()->environment('production') && ! app()->runningUnitTests()) {
            $this->info('Skipping — not production environment.');
            return self::SUCCESS;
        }

        $lastWeek = now()->subWeek();
        $isoYear  = (int) $lastWeek->format('o');
        $isoWeek  = (int) $lastWeek->format('W');

        $url = "https://openlittermap.com/impact/weekly/{$isoYear}/{$isoWeek}";
        $dir = public_path("images/reports/weekly/{$isoYear}/{$isoWeek}");

        @mkdir($dir, 0755, true);

        $path = "{$dir}/impact-report.png";

        try {
            Browsershot::url($url)
                ->windowSize(1200, 800)
                ->waitUntilNetworkIdle()
                ->setChromePath(config('services.browsershot.chrome_path'))
                ->save($path);
        } catch (\Throwable $e) {
            $this->error("Browsershot failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info("Image saved to {$path}");

        $msg = "Weekly Impact Report for week {$isoWeek} of {$isoYear}."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";

        Twitter::sendTweetWithImage($msg, $path);

        $this->info('Tweet sent');

        @unlink($path);

        return self::SUCCESS;
    }
}
