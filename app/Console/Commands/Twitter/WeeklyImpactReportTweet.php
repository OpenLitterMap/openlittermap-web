<?php

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;

class WeeklyImpactReportTweet extends Command
{
    protected $signature = 'twitter:weekly-impact-report-tweet';

    protected $description = 'Generates an image of the weekly impact report and tweets it via OLM_bot';

    public function handle()
    {
        $url = "https://openlittermap.com/impact";
        $year = now()->year;
        $month = now()->month;
        $week = now()->weekOfYear;
        $dir = public_path("images/reports/weekly/$year/$month/$week");

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

        $msg = "Weekly Impact Report for week $week of $year. Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";

        // Tweet the image
        Twitter::sendTweetWithImage($msg, $path);

        $this->info("Tweet sent");

        // Delete the image
        unlink($path);
    }
}
