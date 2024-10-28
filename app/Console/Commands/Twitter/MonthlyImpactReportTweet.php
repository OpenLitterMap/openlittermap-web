<?php

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Spatie\Browsershot\Browsershot;

class MonthlyImpactReportTweet extends Command
{
    protected $signature = 'twitter:monthly-impact-report-tweet';

    protected $description = 'Generates an image of the monthly impact report and tweets it via OLM_bot';

    public function handle()
    {
        $month = now()->subMonth()->month;

        $year = ($month === 1)
            ? now()->subYear()->year
            : now()->year;

        $url = "https://openlittermap.com/impact/monthly/$year/$month";

        $dir = public_path("images/reports/monthly/$year/$month");

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "$dir/impact-report.png";

        Browsershot::url($url)
            ->windowSize(1200, 800)
            ->setOption('logLevel', 'debug')
            ->setChromePath('/snap/bin/chromium')
            ->save($path);

        $this->info("Image saved to $path");

        $time = Carbon::parse("$year-$month-01")->format('F Y');

        $msg = "Monthly Impact Report for $time. Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";

        // Tweet the image
        Twitter::sendTweetWithImage($msg, $path);

        $this->info("Tweet sent");

        // Delete the image
        unlink($path);
    }
}
