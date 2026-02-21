<?php

declare(strict_types=1);

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;

class MonthlyImpactReportTweet extends Command
{
    protected $signature = 'twitter:monthly-impact-report-tweet';

    protected $description = 'Generates an image of the monthly impact report and tweets it via OLM_bot';

    public function handle(): int
    {
        $lastMonth = now()->subMonth();
        $year  = $lastMonth->year;
        $month = $lastMonth->month;

        $url = "https://openlittermap.com/impact/monthly/{$year}/{$month}";
        $dir = public_path("images/reports/monthly/{$year}/{$month}");

        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/impact-report.png";

        try {
            Browsershot::url($url)
                ->windowSize(1200, 800)
                ->fullPage()
                ->waitUntilNetworkIdle()
                ->setChromePath('/snap/bin/chromium')
                ->save($path);
        } catch (\Throwable $e) {
            $this->error("Browsershot failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info("Image saved to {$path}");

        $time = $lastMonth->format('F Y');
        $msg = "Monthly Impact Report for {$time}. Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";

        Twitter::sendTweetWithImage($msg, $path);

        $this->info('Tweet sent');

        @unlink($path);

        return self::SUCCESS;
    }
}
