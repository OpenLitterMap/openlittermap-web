<?php

declare(strict_types=1);

namespace App\Console\Commands\Twitter;

use Carbon\Carbon;
use App\Enums\LocationType;
use App\Helpers\Twitter;
use App\Models\Users\User;
use App\Models\Littercoin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DailyReportTweet extends Command
{
    protected $signature = 'twitter:daily-report';

    protected $description = 'Tweet yesterday\'s OLM daily summary via OLM_bot';

    public function handle(): int
    {
        if (! app()->environment('production') && ! app()->runningUnitTests()) {
            $this->info('Skipping — not production environment.');
            return self::SUCCESS;
        }

        $yesterday = Carbon::yesterday();

        $daily = $this->getDailyMetrics($yesterday);

        if ((int) ($daily->uploads ?? 0) === 0) {
            $this->info('No uploads yesterday — skipping tweet.');
            return self::SUCCESS;
        }

        $newUsers  = User::whereDate('created_at', $yesterday)->count();
        $totalUsers = User::count();
        $littercoin = Littercoin::whereDate('created_at', $yesterday)->count();

        $topCountries = $this->getTopCountries($yesterday, 3);
        $flags = $this->buildFlagString($topCountries);

        $uploads = number_format((int) ($daily->uploads ?? 0));
        $tags    = number_format((int) ($daily->tags ?? 0));

        // Active countries = countries with uploads yesterday
        $activeCountries = $this->getActiveCountryCount($yesterday);

        $message = "Yesterday we signed up {$newUsers} users"
            . " and uploaded {$uploads} photos from {$activeCountries} countries!"
            . " We added {$tags} tags."
            . " We now have " . number_format($totalUsers) . " users!";

        if ($littercoin > 0) {
            $message .= " {$littercoin} littercoin were mined.";
        }

        if ($flags) {
            $message .= " {$flags}";
        }

        $message .= ' #openlittermap #OLMbot 🌍';

        // Twitter has a 280 character limit
        if (mb_strlen($message) > 280) {
            $message = mb_substr($message, 0, 277) . '...';
        }

        Twitter::sendTweet($message);

        $this->info('Tweet sent: ' . $message);

        return self::SUCCESS;
    }

    /**
     * Daily metrics row from the metrics table (timescale=1).
     */
    private function getDailyMetrics(Carbon $date): ?object
    {
        return DB::table('metrics')
            ->where('timescale', 1)
            ->where('location_type', LocationType::Global)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->where('bucket_date', $date->toDateString())
            ->first(['uploads', 'tags', 'brands', 'litter', 'xp']);
    }

    /**
     * Top N countries by tags for the day (single query).
     */
    private function getTopCountries(Carbon $date, int $limit): array
    {
        return DB::table('metrics as m')
            ->join('countries as c', 'c.id', '=', 'm.location_id')
            ->where('m.timescale', 1)
            ->where('m.location_type', LocationType::Country)
            ->where('m.user_id', 0)
            ->where('m.bucket_date', $date->toDateString())
            ->where('m.tags', '>', 0)
            ->orderByDesc('m.tags')
            ->limit($limit)
            ->pluck('c.shortcode')
            ->toArray();
    }

    /**
     * Count of countries with uploads yesterday.
     */
    private function getActiveCountryCount(Carbon $date): int
    {
        return (int) DB::table('metrics')
            ->where('timescale', 1)
            ->where('location_type', LocationType::Country)
            ->where('user_id', 0)
            ->where('bucket_date', $date->toDateString())
            ->where('uploads', '>', 0)
            ->count();
    }

    /**
     * Build "1st 🇮🇪 2nd 🇳🇱 3rd 🇺🇸" string from shortcodes.
     */
    private function buildFlagString(array $shortcodes): string
    {
        $ordinals = ['1st', '2nd', '3rd'];
        $parts = [];

        foreach ($shortcodes as $i => $code) {
            if (! $code || strlen($code) !== 2) continue;

            $flag = $this->countryFlag($code);
            $parts[] = "{$ordinals[$i]} {$flag}";
        }

        return implode(' ', $parts);
    }

    /**
     * Convert 2-letter country code to flag emoji.
     */
    private function countryFlag(string $code): string
    {
        return collect(str_split(strtoupper($code)))
            ->map(fn (string $char) => mb_chr(127397 + ord($char)))
            ->implode('');
    }
}
