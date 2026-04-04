<?php

declare(strict_types=1);

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;

class AnnualImpactReportTweet extends Command
{
    protected $signature = 'twitter:annual-impact-report-tweet';

    protected $description = 'Generates an image of the annual impact report and tweets it via OLM_bot';

    public function handle(): int
    {
        if (! app()->environment('production') && ! app()->runningUnitTests()) {
            $this->info('Skipping — not production environment.');
            return self::SUCCESS;
        }

        $lastYear = now()->subYear()->year;

        $url = "https://openlittermap.com/impact/annual/{$lastYear}";
        $dir = public_path("images/reports/annual/{$lastYear}");

        @mkdir($dir, 0755, true);

        $path = "{$dir}/impact-report.png";

        try {
            Browsershot::url($url)
                ->windowSize(1200, 800)
                ->fullPage()
                ->waitUntilNetworkIdle()
                ->setChromePath(config('services.browsershot.chrome_path'))
                ->save($path);
        } catch (\Throwable $e) {
            $this->error("Browsershot failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info("Image saved to {$path}");

        $msg = "Annual Impact Report for {$lastYear}."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";

        Twitter::sendTweetWithImage($msg, $path);

        $this->info('Tweet sent');

        @unlink($path);

        return self::SUCCESS;
    }
}
