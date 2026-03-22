<?php

namespace Tests\Feature\Twitter;

use App\Helpers\Twitter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChangelogTweetTest extends TestCase
{
    private string $summaryDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->summaryDir = base_path('readme/changelog');
    }

    // ─── Thread building ───────────────────────────────────────────

    public function test_single_tweet_when_changes_fit_in_280_chars(): void
    {
        $date = '2099-01-01';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\n- `v1.0.0` — Small fix\n");

        try {
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('[1/1]')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    public function test_multi_tweet_thread_when_changes_exceed_280_chars(): void
    {
        $date = '2099-01-02';
        $path = "{$this->summaryDir}/{$date}.md";

        $lines = "# Changes\n\n";
        for ($i = 1; $i <= 10; $i++) {
            $lines .= "- `v1.0.{$i}` — This is change number {$i} which adds a feature that does something important\n";
        }

        File::put($path, $lines);

        try {
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('🧵')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    public function test_no_tweet_exceeds_280_characters(): void
    {
        $date = '2099-01-03';
        $path = "{$this->summaryDir}/{$date}.md";

        $lines = "# Changes\n\n";
        for ($i = 1; $i <= 15; $i++) {
            $lines .= "- `v2.0.{$i}` — Implemented a fairly verbose description of change number {$i} that pushes length limits\n";
        }

        File::put($path, $lines);

        try {
            $command = new \App\Console\Commands\Twitter\ChangelogTweet();
            $parseMethod = new \ReflectionMethod($command, 'parseChanges');
            $versionMethod = new \ReflectionMethod($command, 'extractLatestVersion');
            $buildMethod = new \ReflectionMethod($command, 'buildThread');

            $changes = $parseMethod->invoke($command, $path);
            $version = $versionMethod->invoke($command, $changes);
            $tweets = $buildMethod->invoke($command, $date, $version, $changes);

            foreach ($tweets as $i => $tweet) {
                $length = mb_strlen($tweet);
                $this->assertLessThanOrEqual(
                    280,
                    $length,
                    "Tweet " . ($i + 1) . " exceeds 280 chars ({$length}): " . mb_substr($tweet, 0, 100) . '...'
                );
            }
        } finally {
            File::delete($path);
        }
    }

    public function test_last_tweet_includes_footer_and_thread_label_within_limit(): void
    {
        $date = '2099-01-04';
        $path = "{$this->summaryDir}/{$date}.md";

        $lines = "# Changes\n\n";
        for ($i = 1; $i <= 8; $i++) {
            $lines .= "- `v3.0.{$i}` — Change {$i}: updating the system with an important modification to behavior\n";
        }

        File::put($path, $lines);

        try {
            $command = new \App\Console\Commands\Twitter\ChangelogTweet();
            $parseMethod = new \ReflectionMethod($command, 'parseChanges');
            $versionMethod = new \ReflectionMethod($command, 'extractLatestVersion');
            $buildMethod = new \ReflectionMethod($command, 'buildThread');

            $changes = $parseMethod->invoke($command, $path);
            $version = $versionMethod->invoke($command, $changes);
            $tweets = $buildMethod->invoke($command, $date, $version, $changes);

            if (count($tweets) > 1) {
                $lastTweet = end($tweets);
                $this->assertStringContainsString('#openlittermap', $lastTweet);
                $this->assertStringContainsString('🧵', $lastTweet);
                $this->assertLessThanOrEqual(280, mb_strlen($lastTweet),
                    "Last tweet with footer+label exceeds 280 chars: " . mb_strlen($lastTweet));
            }
        } finally {
            File::delete($path);
        }
    }

    public function test_thread_numbering_is_sequential(): void
    {
        $date = '2099-01-05';
        $path = "{$this->summaryDir}/{$date}.md";

        $lines = "# Changes\n\n";
        for ($i = 1; $i <= 12; $i++) {
            $lines .= "- `v4.0.{$i}` — A moderately long description of change {$i} to force multiple tweets in the thread\n";
        }

        File::put($path, $lines);

        try {
            $command = new \App\Console\Commands\Twitter\ChangelogTweet();
            $parseMethod = new \ReflectionMethod($command, 'parseChanges');
            $versionMethod = new \ReflectionMethod($command, 'extractLatestVersion');
            $buildMethod = new \ReflectionMethod($command, 'buildThread');

            $changes = $parseMethod->invoke($command, $path);
            $version = $versionMethod->invoke($command, $changes);
            $tweets = $buildMethod->invoke($command, $date, $version, $changes);

            $total = count($tweets);

            if ($total > 1) {
                foreach ($tweets as $i => $tweet) {
                    $num = $i + 1;
                    $this->assertStringContainsString("🧵 {$num}/{$total}", $tweet,
                        "Tweet {$num} missing correct thread label");
                }
            }
        } finally {
            File::delete($path);
        }
    }

    // ─── Parsing ───────────────────────────────────────────────────

    public function test_parses_bullet_points_from_summary_file(): void
    {
        $date = '2099-01-06';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\n## Session 1\n\n- `v1.0.0` — First change\n- `v1.0.1` — Second change\n\n## Session 2\n\n- `v1.0.2` — Third change\n");

        try {
            $command = new \App\Console\Commands\Twitter\ChangelogTweet();
            $method = new \ReflectionMethod($command, 'parseChanges');
            $changes = $method->invoke($command, $path);

            $this->assertCount(3, $changes);
        } finally {
            File::delete($path);
        }
    }

    public function test_extracts_latest_version_from_changes(): void
    {
        $command = new \App\Console\Commands\Twitter\ChangelogTweet();
        $method = new \ReflectionMethod($command, 'extractLatestVersion');

        $changes = [
            '`v5.0.3` — First',
            '`v5.0.4` — Second',
            '`v5.0.5` — Third',
        ];

        $this->assertEquals('v5.0.5', $method->invoke($command, $changes));
    }

    public function test_strips_backticks_and_version_prefix_from_tweets(): void
    {
        $command = new \App\Console\Commands\Twitter\ChangelogTweet();
        $method = new \ReflectionMethod($command, 'cleanChange');

        $this->assertEquals(
            'Added __APP_VERSION__ define in vite.config.js',
            $method->invoke($command, '`v5.0.3` — Added `__APP_VERSION__` define in `vite.config.js`')
        );
    }

    // ─── Command behavior ──────────────────────────────────────────

    public function test_missing_changelog_file_returns_success_with_no_changes(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $this->artisan('twitter:changelog 2099-12-31')
            ->expectsOutputToContain('No changes found')
            ->assertSuccessful();
    }

    public function test_empty_summary_file_returns_success(): void
    {
        $date = '2099-01-07';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\nNo bullet points here.\n");

        try {
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('No changes found')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    public function test_defaults_to_yesterday_when_no_date_provided(): void
    {
        $yesterday = now()->subDay()->toDateString();
        $path = "{$this->summaryDir}/{$yesterday}.md";
        $existed = File::exists($path);

        if (! $existed) {
            File::put($path, "# Changes\n\n- `v1.0.0` — Test change\n");
        }

        try {
            $this->artisan('twitter:changelog')
                ->assertSuccessful();
        } finally {
            if (! $existed) {
                File::delete($path);
            }
        }
    }

    // ─── Mobile changes integration ─────────────────────────────────

    public function test_includes_mobile_commits_in_thread(): void
    {
        $date = '2099-01-08';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\n- `v1.0.0` — Web fix\n");

        Http::fake([
            'api.github.com/*' => Http::response([
                ['commit' => ['message' => "Mobile feature added\n\nSome details"]],
                ['commit' => ['message' => 'Merge pull request #123']],
                ['commit' => ['message' => 'Another mobile fix']],
            ], 200),
        ]);

        try {
            // Call via Artisan facade so Http::fake persists
            \Illuminate\Support\Facades\Artisan::call('twitter:changelog', ['date' => $date]);
            $output = \Illuminate\Support\Facades\Artisan::output();

            $this->assertStringContainsString('[Web]', $output);
            $this->assertStringContainsString('[Mobile]', $output);
            // Merge commits should be filtered out
            $this->assertStringNotContainsString('Merge pull request', $output);
        } finally {
            File::delete($path);
        }
    }

    public function test_mobile_only_changes_when_no_changelog_file(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                ['commit' => ['message' => 'Fix navigation bug']],
            ], 200),
        ]);

        $this->artisan('twitter:changelog 2099-02-01')
            ->expectsOutputToContain('[Mobile]')
            ->assertSuccessful();
    }

    public function test_skips_merge_commits_from_mobile(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                ['commit' => ['message' => 'Merge pull request #50 from feature']],
                ['commit' => ['message' => 'Merge branch main into dev']],
            ], 200),
        ]);

        $this->artisan('twitter:changelog 2099-02-02')
            ->expectsOutputToContain('No changes found')
            ->assertSuccessful();
    }

    // ─── sendThread return shape ───────────────────────────────────

    public function test_send_thread_returns_correct_shape_in_non_production(): void
    {
        $result = Twitter::sendThread(['Tweet 1', 'Tweet 2']);

        $this->assertArrayHasKey('first_id', $result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertNull($result['first_id']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(2, $result['total']);
    }

    public function test_send_thread_with_empty_array_returns_zero_counts(): void
    {
        $result = Twitter::sendThread([]);

        $this->assertNull($result['first_id']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['total']);
    }
}
