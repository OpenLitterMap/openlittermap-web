<?php

namespace Tests\Feature\Twitter;

use App\Console\Commands\Twitter\ChangelogTweet;
use App\Helpers\Twitter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChangelogTweetTest extends TestCase
{
    private string $summaryDir;
    private ChangelogTweet $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->summaryDir = base_path('readme/changelog');
        $this->command = new ChangelogTweet();
    }

    // ─── Overview tweet counts ───────────────────────────────────────

    public function test_overview_counts_web_and_mobile_correctly(): void
    {
        $date = '2099-01-01';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '- [Web] Fix admin permissions',
            '- [Web] Restore scheduler',
            '- [Web] Add clustering config',
            '- [Mobile] Camera orientation fix',
            '- [Mobile] Upload retry',
        ]));

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            $overview = $tweets[0];
            $this->assertStringContainsString('3 web improvements', $overview);
            $this->assertStringContainsString('2 mobile improvements', $overview);
            $this->assertStringContainsString('🧵 Thread ↓', $overview);
        } finally {
            File::delete($path);
        }
    }

    // ─── Prefix parsing ─────────────────────────────────────────────

    public function test_entries_without_prefix_default_to_web(): void
    {
        $date = '2099-01-02';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '- `v5.0.1` — Fix something',
            '- `v5.0.2` — Another fix',
        ]));

        try {
            $parsed = $this->command->parseEntries($path);

            $this->assertCount(2, $parsed['web']);
            $this->assertCount(0, $parsed['mobile']);
            $this->assertEquals('Fix something', $parsed['web'][0]);
        } finally {
            File::delete($path);
        }
    }

    public function test_web_prefix_stripped_correctly(): void
    {
        $date = '2099-01-03';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\n- [Web] Fix admin permissions\n");

        try {
            $parsed = $this->command->parseEntries($path);

            $this->assertCount(1, $parsed['web']);
            $this->assertEquals('Fix admin permissions', $parsed['web'][0]);
        } finally {
            File::delete($path);
        }
    }

    public function test_mobile_prefix_stripped_correctly(): void
    {
        $date = '2099-01-04';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\n- [Mobile] Camera orientation fix\n");

        try {
            $parsed = $this->command->parseEntries($path);

            $this->assertCount(0, $parsed['web']);
            $this->assertCount(1, $parsed['mobile']);
            $this->assertEquals('Camera orientation fix', $parsed['mobile'][0]);
        } finally {
            File::delete($path);
        }
    }

    // ─── No GitHub API calls ─────────────────────────────────────────

    public function test_no_github_http_calls_made(): void
    {
        Http::fake();

        $date = '2099-01-05';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\n- [Web] Fix something\n");

        try {
            $this->artisan("twitter:changelog {$date}")
                ->assertSuccessful();

            Http::assertNothingSent();
        } finally {
            File::delete($path);
        }
    }

    // ─── Web only ────────────────────────────────────────────────────

    public function test_web_only_overview_shows_web_count_no_mobile(): void
    {
        $date = '2099-01-06';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '- [Web] Fix admin permissions',
            '- [Web] Restore scheduler',
        ]));

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            $overview = $tweets[0];
            $this->assertStringContainsString('2 web improvements', $overview);
            $this->assertStringNotContainsString('mobile', $overview);
        } finally {
            File::delete($path);
        }
    }

    // ─── Mobile only ─────────────────────────────────────────────────

    public function test_mobile_only_overview_shows_mobile_count_no_web(): void
    {
        $date = '2099-01-07';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '- [Mobile] Camera fix',
            '- [Mobile] Upload retry',
            '- [Mobile] Haptic feedback',
        ]));

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            $overview = $tweets[0];
            $this->assertStringContainsString('3 mobile improvements', $overview);
            $this->assertStringNotContainsString('web', $overview);
        } finally {
            File::delete($path);
        }
    }

    // ─── Long changelog splits ───────────────────────────────────────

    public function test_long_changelog_splits_across_tweets(): void
    {
        $date = '2099-01-08';
        $path = "{$this->summaryDir}/{$date}.md";

        $lines = "# Changes\n\n";
        for ($i = 1; $i <= 15; $i++) {
            $lines .= "- [Web] Implemented a fairly verbose description of change number {$i} that pushes tweet length limits\n";
        }

        File::put($path, $lines);

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            $this->assertGreaterThan(2, count($tweets), 'Should split into 3+ tweets');

            // Every tweet must be within 280 chars
            foreach ($tweets as $i => $tweet) {
                $this->assertLessThanOrEqual(
                    280,
                    mb_strlen($tweet),
                    "Tweet " . ($i + 1) . " exceeds 280 chars (" . mb_strlen($tweet) . ")"
                );
            }
        } finally {
            File::delete($path);
        }
    }

    public function test_oversized_single_line_truncated_within_280(): void
    {
        $date = '2099-01-20';
        $path = "{$this->summaryDir}/{$date}.md";

        // A single line that is 300+ chars
        $longLine = '- [Web] ' . str_repeat('A very long description that keeps going ', 8);

        File::put($path, "# Changes\n\n{$longLine}\n");

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            foreach ($tweets as $i => $tweet) {
                $this->assertLessThanOrEqual(
                    280,
                    mb_strlen($tweet),
                    "Tweet " . ($i + 1) . " exceeds 280 chars (" . mb_strlen($tweet) . ")"
                );
            }
        } finally {
            File::delete($path);
        }
    }

    // ─── No file: skip silently ──────────────────────────────────────

    public function test_no_file_skips_silently(): void
    {
        $this->artisan('twitter:changelog 2099-12-31')
            ->expectsOutputToContain('No changelog found')
            ->assertSuccessful();
    }

    public function test_empty_file_skips_silently(): void
    {
        $date = '2099-01-09';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, "# Changes\n\nNo bullet points here.\n");

        try {
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('No changelog found')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    // ─── Thread structure ────────────────────────────────────────────

    public function test_first_tweet_is_always_overview(): void
    {
        $date = '2099-01-10';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '- [Web] Fix something',
            '- [Mobile] Camera fix',
        ]));

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            $this->assertStringContainsString('🔧 OpenLitterMap — Changes for', $tweets[0]);
            $this->assertStringContainsString('🧵 Thread ↓', $tweets[0]);

            // Second tweet has actual changes
            $this->assertStringContainsString('🌐 Web', $tweets[1]);
        } finally {
            File::delete($path);
        }
    }

    public function test_last_tweet_has_hashtags(): void
    {
        $date = '2099-01-11';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '- [Web] Fix something',
            '- [Mobile] Camera fix',
        ]));

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            $lastTweet = end($tweets);
            $this->assertStringContainsString('#openlittermap #changelog', $lastTweet);
        } finally {
            File::delete($path);
        }
    }

    public function test_grouped_sections_in_correct_order(): void
    {
        $date = '2099-01-12';
        $path = "{$this->summaryDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '- [Mobile] Camera fix',
            '- [Web] Fix something',
        ]));

        try {
            $parsed = $this->command->parseEntries($path);
            $tweets = $this->command->buildThread($date, $parsed['web'], $parsed['mobile']);

            // Changes tweet should have Web before Mobile
            $changesTweet = $tweets[1];
            $webPos = mb_strpos($changesTweet, '🌐 Web');
            $mobilePos = mb_strpos($changesTweet, '📱 Mobile');

            $this->assertNotFalse($webPos);
            $this->assertNotFalse($mobilePos);
            $this->assertLessThan($mobilePos, $webPos, 'Web section should come before Mobile');
        } finally {
            File::delete($path);
        }
    }

    // ─── cleanChange ─────────────────────────────────────────────────

    public function test_strips_backticks_and_version_prefix(): void
    {
        $this->assertEquals(
            'Added __APP_VERSION__ define in vite.config.js',
            $this->command->cleanChange('`v5.0.3` — Added `__APP_VERSION__` define in `vite.config.js`')
        );
    }

    public function test_strips_bold_version_prefix(): void
    {
        $this->assertEquals(
            'Fix admin permissions',
            $this->command->cleanChange('**v5.0.13** — Fix admin permissions')
        );
    }

    // ─── Defaults to yesterday ───────────────────────────────────────

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

    // ─── sendThread return shape ─────────────────────────────────────

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

    // ─── Singular/plural ─────────────────────────────────────────────

    public function test_singular_improvement_for_single_entry(): void
    {
        $tweets = $this->command->buildThread('2099-01-13', ['Fix something'], []);

        $this->assertStringContainsString('1 web improvement', $tweets[0]);
        $this->assertStringNotContainsString('improvements', $tweets[0]);
    }
}
