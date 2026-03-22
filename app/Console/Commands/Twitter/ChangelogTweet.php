<?php

declare(strict_types=1);

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChangelogTweet extends Command
{
    protected $signature = 'twitter:changelog {date? : Date in YYYY-MM-DD format, defaults to yesterday}';

    protected $description = 'Tweet a threaded changelog from the daily changelog file and React Native repo commits';

    private const MAX_TWEET_LENGTH = 280;

    public function handle(): int
    {
        if (! app()->environment('production') && ! app()->runningUnitTests()) {
            $this->info('Skipping — not production environment.');
            return self::SUCCESS;
        }

        $date = $this->argument('date') ?? now()->subDay()->toDateString();
        $path = base_path("readme/changelog/{$date}.md");

        // Web changelog
        $webChanges = [];
        if (File::exists($path)) {
            $webChanges = $this->parseChanges($path);
        }

        // React Native repo commits
        $mobileChanges = $this->fetchMobileChanges($date);

        $changes = [];
        if (! empty($webChanges)) {
            $changes = array_merge($changes, array_map(fn ($c) => "[Web] {$c}", $webChanges));
        }
        if (! empty($mobileChanges)) {
            $changes = array_merge($changes, array_map(fn ($c) => "[Mobile] {$c}", $mobileChanges));
        }

        if (empty($changes)) {
            $this->info("No changes found for {$date} — skipping.");
            return self::SUCCESS;
        }

        $version = $this->extractLatestVersion($changes);
        $tweets = $this->buildThread($date, $version, $changes);

        foreach ($tweets as $i => $tweet) {
            $this->line("[" . ($i + 1) . "/" . count($tweets) . "] " . $tweet);
        }

        $result = Twitter::sendThread($tweets);

        if ($result['sent'] === 0) {
            $this->info('Thread not sent (non-production or dry run).');
            return self::SUCCESS;
        }

        if ($result['sent'] < $result['total']) {
            $this->error("Partial thread failure: {$result['sent']}/{$result['total']} tweets posted. First ID: {$result['first_id']}");
            return self::FAILURE;
        }

        $this->info("Thread posted ({$result['sent']} tweets). First tweet ID: {$result['first_id']}");
        return self::SUCCESS;
    }

    /**
     * Parse bullet-point changes from the markdown summary file.
     */
    private function parseChanges(string $path): array
    {
        $lines = File::lines($path);
        $changes = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Match lines starting with "- " (bullet points)
            if (str_starts_with($line, '- ')) {
                $changes[] = ltrim($line, '- ');
            }
        }

        return $changes;
    }

    /**
     * Extract the latest version tag from the changes (e.g. "v5.0.3 — description").
     */
    private function extractLatestVersion(array $changes): string
    {
        $version = 'latest';

        foreach (array_reverse($changes) as $change) {
            if (preg_match('/^`?(v?\d+\.\d+\.\d+)`?/', $change, $m)) {
                $version = $m[1];
                break;
            }
        }

        return $version;
    }

    /**
     * Build numbered tweet thread from changes.
     */
    private function buildThread(string $date, string $version, array $changes): array
    {
        $header = "🔧 OpenLitterMap {$version} — Changes for {$date}\n\n";
        $footer = "\n\n#openlittermap #changelog";

        // Try to fit everything in a single tweet first
        $singleTweet = $header;
        foreach ($changes as $change) {
            // Strip version prefix for cleaner tweets
            $clean = $this->cleanChange($change);
            $singleTweet .= "• {$clean}\n";
        }
        $singleTweet .= $footer;

        if (mb_strlen($singleTweet) <= self::MAX_TWEET_LENGTH) {
            return [$singleTweet];
        }

        // Multi-tweet thread
        $tweets = [];
        $totalEstimate = $this->estimateTweetCount($changes, $header, $footer);

        // First tweet: header + as many changes as fit
        $current = $header;
        $changeIndex = 0;

        while ($changeIndex < count($changes)) {
            $clean = $this->cleanChange($changes[$changeIndex]);
            $bullet = "• {$clean}\n";
            $threadLabel = "\n\n🧵 1/{$totalEstimate}";

            if (mb_strlen($current . $bullet . $threadLabel) > self::MAX_TWEET_LENGTH) {
                break;
            }

            $current .= $bullet;
            $changeIndex++;
        }

        $tweets[] = $current . "\n\n🧵 1/{$totalEstimate}";

        // Remaining tweets
        $tweetNum = 2;
        $current = '';

        while ($changeIndex < count($changes)) {
            $clean = $this->cleanChange($changes[$changeIndex]);
            $bullet = "• {$clean}\n";

            // Always reserve space for both footer and thread label (worst case)
            $maxSuffix = $footer . "\n\n🧵 {$tweetNum}/{$totalEstimate}";

            if (mb_strlen($current . $bullet . $maxSuffix) > self::MAX_TWEET_LENGTH && $current !== '') {
                $tweets[] = trim($current) . "\n\n🧵 {$tweetNum}/{$totalEstimate}";
                $tweetNum++;
                $current = '';
            }

            $current .= $bullet;
            $changeIndex++;
        }

        if ($current !== '') {
            $tweets[] = trim($current) . $footer . "\n\n🧵 {$tweetNum}/{$totalEstimate}";
        }

        // Re-number with correct total now that we know the real count
        $total = count($tweets);
        foreach ($tweets as $i => &$tweet) {
            $num = $i + 1;
            $tweet = preg_replace('/🧵 \d+\/\d+/', "🧵 {$num}/{$total}", $tweet);
        }

        return $tweets;
    }

    /**
     * Fetch commit messages from the React Native repo for the given date.
     *
     * @return string[]
     */
    private function fetchMobileChanges(string $date): array
    {
        try {
            $since = "{$date}T00:00:00Z";
            $until = "{$date}T23:59:59Z";

            $response = Http::get('https://api.github.com/repos/OpenLitterMap/react-native/commits', [
                'sha' => 'openlittermap/v7',
                'since' => $since,
                'until' => $until,
                'per_page' => 50,
            ]);

            if (! $response->successful()) {
                Log::warning('Failed to fetch React Native commits', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            $commits = $response->json();
            $changes = [];

            foreach ($commits as $commit) {
                $message = $commit['commit']['message'] ?? '';
                // Use first line of commit message only
                $firstLine = trim(strtok($message, "\n"));
                if ($firstLine !== '' && ! str_starts_with($firstLine, 'Merge ')) {
                    $changes[] = $firstLine;
                }
            }

            return $changes;
        } catch (\Exception $e) {
            Log::warning('Error fetching React Native commits', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Strip version prefix like "`v5.0.3` — " from a change line.
     */
    private function cleanChange(string $change): string
    {
        $clean = preg_replace('/^`?v?\d+\.\d+\.\d+`?\s*[—–-]\s*/u', '', $change);

        // Strip markdown backticks — tweets don't render them
        return str_replace('`', '', $clean);
    }

    /**
     * Rough estimate of how many tweets we'll need.
     */
    private function estimateTweetCount(array $changes, string $header, string $footer): int
    {
        $avgChangeLen = 0;
        foreach ($changes as $change) {
            $avgChangeLen += mb_strlen($this->cleanChange($change)) + 4; // "• " + "\n"
        }

        $available = self::MAX_TWEET_LENGTH - mb_strlen($header) - 20; // thread label
        $perTweet = self::MAX_TWEET_LENGTH - 20;

        $firstFit = max(1, (int) floor($available / max(1, $avgChangeLen / count($changes))));
        $remaining = max(0, count($changes) - $firstFit);
        $restFit = max(1, (int) floor($perTweet / max(1, $avgChangeLen / count($changes))));

        return 1 + (int) ceil($remaining / max(1, $restFit));
    }
}
