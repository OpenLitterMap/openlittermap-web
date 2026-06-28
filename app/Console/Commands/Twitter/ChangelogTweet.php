<?php

declare(strict_types=1);

namespace App\Console\Commands\Twitter;

use App\Helpers\Social;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChangelogTweet extends Command
{
    protected $signature = 'twitter:changelog {date? : Date in YYYY-MM-DD format, defaults to yesterday}';

    protected $description = 'Post the curated ## Public changelog block(s) to OLMbot social feeds';

    private const MAX_POST_LENGTH = 300;

    private const MOBILE_CHANGELOG_URL = 'https://raw.githubusercontent.com/OpenLitterMap/react-native/openlittermap/v7/readme/changelog/';

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

        $webPublic = $this->parsePublicBlock(File::lines($path));
        $mobilePublic = $this->mobilePublicBlock($date);

        $posts = array_merge($this->buildPosts($webPublic), $this->buildPosts($mobilePublic));

        if (empty($posts)) {
            $this->info("No public changelog for {$date} — nothing to post.");

            return self::SUCCESS;
        }

        foreach ($posts as $i => $post) {
            $this->line('[' . ($i + 1) . '/' . count($posts) . '] ' . $post);
        }

        $result = Social::thread($posts);

        if ($result['sent'] === 0) {
            $this->info('Changelog not sent (non-production or dry run).');

            return self::SUCCESS;
        }

        if ($result['sent'] < $result['total']) {
            $this->error("Partial post failure: {$result['sent']}/{$result['total']} posts published. First ID: {$result['first_id']}");

            return self::FAILURE;
        }

        $this->info("Changelog posted ({$result['sent']} posts). First post ID: {$result['first_id']}");

        return self::SUCCESS;
    }

    /**
     * Parse the curated `## Public` block from an iterable of changelog lines and
     * return it as a single plain-language post body. Returns '' when the block is
     * absent or empty — the silence case, where that source posts nothing.
     *
     * Works on both the local web file (`File::lines($path)`) and the fetched
     * mobile body (`explode("\n", $body)`). The block runs from the `## Public`
     * heading to the next markdown heading (or EOF). Blank lines are dropped and a
     * leading bullet marker is tolerated and stripped; the remaining lines are
     * joined into one prose string (the house standard is tight prose under 300
     * chars, not a bullet list).
     *
     * @param  iterable<int, string>  $lines
     */
    public function parsePublicBlock(iterable $lines): string
    {
        $inBlock = false;
        $collected = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (! $inBlock) {
                if ($trimmed === '## Public') {
                    $inBlock = true;
                }

                continue;
            }

            if (preg_match('/^#{1,6}\s/', $trimmed)) {
                break;
            }

            if ($trimmed === '') {
                continue;
            }

            $collected[] = preg_replace('/^[-*]\s+/', '', $trimmed);
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $collected)));
    }

    /**
     * Fetch the mobile (react-native) changelog for the date and return its
     * `## Public` block. Mobile posts require the mobile repo to adopt the same
     * `## Public` convention; until it does — or if the fetch fails (non-200,
     * network error, timeout, exception) — this returns '' and the bot posts
     * web-only. A mobile failure never breaks the command.
     */
    public function mobilePublicBlock(string $date): string
    {
        $body = $this->fetchMobileChangelog($date);

        return $body === '' ? '' : $this->parsePublicBlock(explode("\n", $body));
    }

    /**
     * Fetch the raw mobile changelog body from the react-native GitHub repo, or ''
     * on any failure (logged, never thrown).
     */
    public function fetchMobileChangelog(string $date): string
    {
        try {
            $response = Http::timeout(10)->get(self::MOBILE_CHANGELOG_URL . "{$date}.md");

            return $response->successful() ? $response->body() : '';
        } catch (Throwable $e) {
            Log::warning("Failed to fetch mobile changelog for {$date}: {$e->getMessage()}");

            return '';
        }
    }

    /**
     * Build the post(s) for one public block: one post when it fits a single
     * Bluesky post (300 chars), otherwise a thread split on word boundaries with
     * every post within the limit. An empty block contributes no posts.
     *
     * @return string[]
     */
    public function buildPosts(string $text): array
    {
        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= self::MAX_POST_LENGTH) {
            return [$text];
        }

        $posts = [];
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (mb_strlen($candidate) > self::MAX_POST_LENGTH && $current !== '') {
                $posts[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $posts[] = $current;
        }

        return $posts;
    }
}
