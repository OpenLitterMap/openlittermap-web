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
use Illuminate\Support\Facades\Log;

class DailyReportTweet extends Command
{
    protected $signature = 'twitter:daily-report';

    protected $description = 'Tweet yesterday\'s OLM gamified daily scoreboard via OLM_bot';

    private const MAX_TWEET_LENGTH = 280;

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

        $allTime = $this->getAllTimeMetrics();

        $newUsers    = User::whereDate('created_at', $yesterday)->count();
        $totalUsers  = User::count();
        $littercoin  = Littercoin::whereDate('created_at', $yesterday)->count();

        $topCountries    = $this->getTopCountriesWithNames($yesterday, 3);
        $activeCountries = $this->getActiveCountryCount($yesterday);

        $dailyUploads = (int) ($daily->uploads ?? 0);
        $dailyTags    = (int) ($daily->tags ?? 0);

        $totalPhotos = (int) ($allTime->uploads ?? 0);
        $totalTags   = (int) ($allTime->tags ?? 0);

        // ─── Tweet 1: Scoreboard ─────────────────────────────────────

        $tweet1Lines = [];
        $tweet1Lines[] = "📊 OpenLitterMap — {$yesterday->toDateString()}";

        try {
            $streak = $this->calculateStreak($yesterday);
            if ($streak >= 2) {
                $tweet1Lines[] = "📅 Day {$streak} of consecutive uploads!";
            }
        } catch (\Throwable $e) {
            Log::warning('DailyReport: streak calculation failed', ['error' => $e->getMessage()]);
        }

        try {
            $tweet1Lines[] = "🗺️ " . $this->seasonLabel($totalPhotos);
        } catch (\Throwable $e) {
            Log::warning('DailyReport: season label failed', ['error' => $e->getMessage()]);
        }

        $tweet1Lines[] = '';
        $tweet1Lines[] = "👥 " . number_format($newUsers) . " new users (" . number_format($totalUsers) . " total)";
        $tweet1Lines[] = "📸 " . number_format($dailyUploads) . " uploads (" . number_format($totalPhotos) . " total)";
        $tweet1Lines[] = "🏷️ " . number_format($dailyTags) . " tags (" . number_format($totalTags) . " total)";

        if ($littercoin > 0) {
            $tweet1Lines[] = "🪙 " . number_format($littercoin) . " Littercoin mined";
        }

        $tweet1Lines[] = '';

        $milestone = $this->nextMilestone($totalPhotos);
        $remaining = $milestone - $totalPhotos;
        $tweet1Lines[] = "🎯 Only " . number_format($remaining) . " to " . number_format($milestone) . " photos!";

        $tweet1 = $this->truncateTweet(implode("\n", $tweet1Lines));

        // ─── Tweet 2: Podium + Mission ───────────────────────────────

        $tweet2Lines = [];
        $tweet2Lines[] = "🏆 Country Podium";

        if (! empty($topCountries)) {
            $medals = ['🥇', '🥈', '🥉'];

            foreach ($topCountries as $i => $entry) {
                $flag = $this->countryFlag($entry['shortcode']);
                $name = $entry['country'];

                if ($i === 0) {
                    try {
                        $tweet2Lines[] = $this->leadLine($name, $flag, $yesterday);
                    } catch (\Throwable $e) {
                        $tweet2Lines[] = "{$medals[0]} {$flag} {$name}";
                        Log::warning('DailyReport: lead line failed', ['error' => $e->getMessage()]);
                    }
                } else {
                    $tweet2Lines[] = "{$medals[$i]} {$flag} {$name}";
                }
            }
        }

        $tweet2Lines[] = "🌍 " . number_format($activeCountries) . " countries active";

        try {
            $newCities = $this->getNewCities($yesterday, 3);
            if (! empty($newCities)) {
                $cityParts = array_map(function ($city) {
                    $flag = $this->countryFlag($city['shortcode']);
                    return "{$city['city']} {$flag}";
                }, $newCities);
                $tweet2Lines[] = '';
                $tweet2Lines[] = "🗺️ New: " . implode(', ', $cityParts);
            }
        } catch (\Throwable $e) {
            Log::warning('DailyReport: new cities failed', ['error' => $e->getMessage()]);
        }

        $tweet2Lines[] = '';

        try {
            $streak = $streak ?? 0;
            $tweet2Lines[] = "🎯 " . $this->missionLine($dailyUploads, $totalPhotos, $streak);
        } catch (\Throwable $e) {
            Log::warning('DailyReport: mission line failed', ['error' => $e->getMessage()]);
        }

        $tweet2Lines[] = '';
        $tweet2Lines[] = '#openlittermap #citizenscience #OLMbot';

        $tweet2 = $this->truncateTweet(implode("\n", $tweet2Lines));

        // ─── Send ────────────────────────────────────────────────────

        $result = Twitter::sendThread([$tweet1, $tweet2]);

        $this->line("Tweet 1:\n{$tweet1}");
        $this->line("Tweet 2:\n{$tweet2}");

        if ($result['sent'] === 0) {
            $this->info('Thread not sent (non-production or dry run).');
            return self::SUCCESS;
        }

        if ($result['sent'] < $result['total']) {
            $this->error("Partial thread failure: {$result['sent']}/{$result['total']} tweets posted. First ID: {$result['first_id']}");
            return self::FAILURE;
        }

        $this->info("Thread posted ({$result['sent']} tweets). First ID: {$result['first_id']}");
        return self::SUCCESS;
    }

    // ─── Data queries ────────────────────────────────────────────────

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
            ->first(['uploads', 'tags', 'litter', 'xp']);
    }

    /**
     * All-time metrics row (timescale=0, bucket_date=1970-01-01). Single PK lookup.
     */
    private function getAllTimeMetrics(): ?object
    {
        return DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->where('bucket_date', '1970-01-01')
            ->first(['uploads', 'tags', 'litter', 'xp']);
    }

    /**
     * Top N countries by tags for the day, with country name + shortcode.
     *
     * @return array<int, array{country: string, shortcode: string}>
     */
    private function getTopCountriesWithNames(Carbon $date, int $limit): array
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
            ->get(['c.country', 'c.shortcode'])
            ->map(fn ($row) => ['country' => $row->country, 'shortcode' => $row->shortcode])
            ->toArray();
    }

    /**
     * Count of countries with uploads on the given date.
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
     * Cities created on the given date, with country shortcode.
     *
     * @return array<int, array{city: string, shortcode: string}>
     */
    private function getNewCities(Carbon $date, int $limit): array
    {
        return DB::table('cities')
            ->join('countries', 'countries.id', '=', 'cities.country_id')
            ->whereDate('cities.created_at', $date->toDateString())
            ->orderByDesc('cities.id')
            ->limit($limit)
            ->get(['cities.city', 'countries.shortcode'])
            ->map(fn ($row) => ['city' => $row->city, 'shortcode' => $row->shortcode])
            ->toArray();
    }

    // ─── Gamification methods ────────────────────────────────────────

    /**
     * Count consecutive days with uploads, backwards from $date.
     */
    public function calculateStreak(Carbon $date): int
    {
        $rows = DB::table('metrics')
            ->where('timescale', 1)
            ->where('location_type', LocationType::Global)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->where('uploads', '>', 0)
            ->where('bucket_date', '<=', $date->toDateString())
            ->orderByDesc('bucket_date')
            ->limit(365)
            ->pluck('bucket_date');

        $streak = 0;
        $expected = $date->copy();

        foreach ($rows as $bucketDate) {
            if ($bucketDate === $expected->toDateString()) {
                $streak++;
                $expected->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Next milestone upload count.
     * Step: 5,000 under 100K, 10,000 under 1M, 50,000 above.
     */
    public function nextMilestone(int $total): int
    {
        if ($total < 100_000) {
            $step = 5_000;
        } elseif ($total < 1_000_000) {
            $step = 10_000;
        } else {
            $step = 50_000;
        }

        return (int) (ceil(($total + 1) / $step) * $step);
    }

    /**
     * Season label based on total photo milestones.
     */
    public function seasonLabel(int $totalPhotos): string
    {
        $targets = [
            100_000  => '100K',
            250_000  => '250K',
            500_000  => '500K',
            750_000  => '750K',
            1_000_000 => '1M',
        ];

        foreach ($targets as $target => $label) {
            if ($totalPhotos < $target) {
                $pct = round(($totalPhotos / $target) * 100, 1);
                return "Season: Road to {$label} · {$pct}% complete";
            }
        }

        return "Season: Beyond 1M · " . number_format($totalPhotos) . " and counting";
    }

    /**
     * Lead line for the #1 country on the podium.
     * Uses a single query to fetch recent top countries for streak calculation.
     */
    public function leadLine(string $country, string $flag, Carbon $yesterday): string
    {
        $recentTopCountries = $this->getRecentTopCountries($yesterday, 31);

        if (empty($recentTopCountries)) {
            return "🥇 {$flag} {$country}";
        }

        // Calculate lead streak from the batch
        $leadStreak = 0;
        foreach ($recentTopCountries as $topCountry) {
            if ($topCountry === $country) {
                $leadStreak++;
            } else {
                break;
            }
        }

        if ($leadStreak >= 2) {
            return "🥇 {$flag} {$country} leads the way — Day {$leadStreak}!";
        }

        // Check if previous day had a different leader
        $previousTop = $recentTopCountries[1] ?? null;

        if ($previousTop !== null && $previousTop !== $country) {
            return "🥇 {$flag} {$country} takes the lead!";
        }

        return "🥇 {$flag} {$country}";
    }

    /**
     * Mission line — motivational call to action.
     */
    public function missionLine(int $yesterdayUploads, int $totalPhotos, int $streak): string
    {
        $milestone = $this->nextMilestone($totalPhotos);
        $remaining = $milestone - $totalPhotos;
        $milestoneLabel = $this->formatMilestone($milestone);

        // Frame 1: Streak active AND milestone reachable within a week
        if ($streak >= 2 && $yesterdayUploads > 0 && $remaining <= $yesterdayUploads * 7) {
            return number_format($yesterdayUploads) . " uploads today keeps the streak alive and pushes us under "
                . number_format($remaining) . " to {$milestoneLabel}!";
        }

        // Frame 2: Milestone lands this week at current pace
        if ($yesterdayUploads > 0) {
            $daysToMilestone = (int) ceil($remaining / $yesterdayUploads);
            if ($daysToMilestone <= 7) {
                return "At this pace, {$milestoneLabel} lands this week!";
            }
        }

        // Frame 3: Default
        return "Only " . number_format($remaining) . " to " . number_format($milestone) . " photos!";
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Get the #1 country by tags for each of the last N days, in one query.
     * Returns array indexed from 0 (most recent = $date) to N-1.
     *
     * @return string[]  Country names, one per day
     */
    private function getRecentTopCountries(Carbon $date, int $days): array
    {
        $startDate = $date->copy()->subDays($days - 1)->toDateString();

        $rows = DB::table('metrics as m')
            ->join('countries as c', 'c.id', '=', 'm.location_id')
            ->where('m.timescale', 1)
            ->where('m.location_type', LocationType::Country)
            ->where('m.user_id', 0)
            ->where('m.tags', '>', 0)
            ->whereBetween('m.bucket_date', [$startDate, $date->toDateString()])
            ->orderByDesc('m.bucket_date')
            ->orderByDesc('m.tags')
            ->get(['m.bucket_date', 'c.country']);

        // Take only the #1 country per date
        $topByDate = [];
        foreach ($rows as $row) {
            if (! isset($topByDate[$row->bucket_date])) {
                $topByDate[$row->bucket_date] = $row->country;
            }
        }

        // Return in date-descending order (most recent first)
        krsort($topByDate);

        return array_values($topByDate);
    }

    /**
     * Format a milestone number for display.
     * Under 1M: "525k". At/above 1M: "1M", "1.05M", "2M".
     */
    public function formatMilestone(int $milestone): string
    {
        if ($milestone >= 1_000_000) {
            $millions = $milestone / 1_000_000;
            return ($millions == (int) $millions)
                ? (int) $millions . 'M'
                : rtrim(number_format($millions, 2), '0') . 'M';
        }

        if ($milestone >= 1_000) {
            $thousands = $milestone / 1_000;
            return ($thousands == (int) $thousands)
                ? (int) $thousands . 'k'
                : rtrim(number_format($thousands, 1), '0') . 'k';
        }

        return number_format($milestone);
    }

    /**
     * Truncate a tweet to 280 chars if it exceeds the limit.
     */
    private function truncateTweet(string $tweet): string
    {
        if (mb_strlen($tweet) <= self::MAX_TWEET_LENGTH) {
            return $tweet;
        }

        return mb_substr($tweet, 0, self::MAX_TWEET_LENGTH - 1) . '…';
    }

    /**
     * Convert 2-letter country code to flag emoji.
     */
    private function countryFlag(string $code): string
    {
        if (! $code || strlen($code) !== 2) {
            return '';
        }

        return collect(str_split(strtoupper($code)))
            ->map(fn (string $char) => mb_chr(127397 + ord($char)))
            ->implode('');
    }
}
