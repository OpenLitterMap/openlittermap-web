<?php

declare(strict_types=1);

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ChangelogTweet extends Command
{
    protected $signature = 'twitter:changelog {date? : Date in YYYY-MM-DD format, defaults to yesterday}';

    protected $description = 'Tweet a threaded changelog from the daily changelog file';

    private const MAX_TWEET_LENGTH = 280;

    public function handle(): int
    {
        if (! app()->environment('production') && ! app()->runningUnitTests()) {
            $this->info('Skipping — not production environment.');
            return self::SUCCESS;
        }

        $date = $this->argument('date') ?? now()->subDay()->toDateString();
        $path = base_path("readme/changelog/{$date}.md");

        if (! File::exists($path)) {
            $this->info("No changelog found for {$date} — skipping.");
            return self::SUCCESS;
        }

        $parsed = $this->parseEntries($path);

        if (empty($parsed['web']) && empty($parsed['mobile'])) {
            $this->info("No changelog found for {$date} — skipping.");
            return self::SUCCESS;
        }

        $tweets = $this->buildThread($date, $parsed['web'], $parsed['mobile']);

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
     * Parse changelog entries into web and mobile arrays.
     *
     * - Lines starting with `- [Web] ` → web (prefix stripped)
     * - Lines starting with `- [Mobile] ` → mobile (prefix stripped)
     * - Lines starting with `- ` with no prefix → default to web
     *
     * Version prefixes and backticks are cleaned from all entries.
     *
     * @return array{web: string[], mobile: string[]}
     */
    public function parseEntries(string $path): array
    {
        $web = [];
        $mobile = [];

        foreach (File::lines($path) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, '- ')) {
                continue;
            }

            $content = substr($line, 2); // strip "- "

            if (str_starts_with($content, '[Mobile] ')) {
                $mobile[] = $this->cleanChange(substr($content, 9));
            } elseif (str_starts_with($content, '[Web] ')) {
                $web[] = $this->cleanChange(substr($content, 6));
            } else {
                // No prefix → default to web
                $web[] = $this->cleanChange($content);
            }
        }

        return ['web' => $web, 'mobile' => $mobile];
    }

    /**
     * Build the tweet thread: overview tweet + grouped change tweets.
     *
     * @param  string[]  $web
     * @param  string[]  $mobile
     * @return string[]
     */
    public function buildThread(string $date, array $web, array $mobile): array
    {
        $tweets = [];

        // ─── Tweet 1: Overview ───────────────────────────────────────

        $overview = "🔧 OpenLitterMap — Changes for {$date}\n\n";

        $counts = [];
        if (! empty($web)) {
            $counts[] = count($web) . " web improvement" . (count($web) !== 1 ? 's' : '');
        }
        if (! empty($mobile)) {
            $counts[] = count($mobile) . " mobile improvement" . (count($mobile) !== 1 ? 's' : '');
        }

        $overview .= implode(' · ', $counts);
        $overview .= "\n\n🧵 Thread ↓";

        $tweets[] = $overview;

        // ─── Tweet 2+: Grouped changes ───────────────────────────────

        $footer = "\n\n#openlittermap #changelog";
        $changeLines = [];

        if (! empty($web)) {
            $changeLines[] = "🌐 Web";
            foreach ($web as $entry) {
                $changeLines[] = "- {$entry}";
            }
        }

        if (! empty($web) && ! empty($mobile)) {
            $changeLines[] = '';
        }

        if (! empty($mobile)) {
            $changeLines[] = "📱 Mobile";
            foreach ($mobile as $entry) {
                $changeLines[] = "- {$entry}";
            }
        }

        // Pack change lines into tweets respecting 280 char limit
        $current = '';

        foreach ($changeLines as $changeLine) {
            // Truncate individual lines that are too long for a single tweet
            $maxLineLen = self::MAX_TWEET_LENGTH - mb_strlen($footer) - 2;
            if (mb_strlen($changeLine) > $maxLineLen) {
                $changeLine = mb_substr($changeLine, 0, $maxLineLen - 1) . '…';
            }

            $candidate = $current === '' ? $changeLine : $current . "\n" . $changeLine;

            // Reserve space for footer on the last tweet (worst case)
            if (mb_strlen($candidate . $footer) > self::MAX_TWEET_LENGTH && $current !== '') {
                $tweets[] = trim($current);
                $current = $changeLine;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $tweets[] = trim($current) . $footer;
        }

        return $tweets;
    }

    /**
     * Strip version prefix like "`v5.0.3` — " and backticks from a change line.
     */
    public function cleanChange(string $change): string
    {
        $clean = preg_replace('/^\*\*`?v?\d+\.\d+\.\d+`?\*\*\s*[—–-]\s*/u', '', $change);
        $clean = preg_replace('/^`?v?\d+\.\d+\.\d+`?\s*[—–-]\s*/u', '', $clean);

        return str_replace('`', '', $clean);
    }
}
