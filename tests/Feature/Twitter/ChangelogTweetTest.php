<?php

namespace Tests\Feature\Twitter;

use App\Console\Commands\Twitter\ChangelogTweet;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChangelogTweetTest extends TestCase
{
    private string $changelogDir;
    private ChangelogTweet $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->changelogDir = base_path('readme/changelog');
        $this->command = new ChangelogTweet();
    }

    /** A mobile changelog body carrying a `## Public` block in the house style. */
    private function mobilePublicBody(string $text): string
    {
        return implode("\n", ['# Mobile Changes', '', '## Public', $text, '', '## Internal', '- did a thing']);
    }

    // ─── parsePublicBlock (web file lines) ───────────────────────────

    public function test_parses_public_block_as_prose(): void
    {
        $date = '2099-01-01';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '## Public',
            'OpenLitterMap update 🔒 We fixed a privacy issue and exports are faster. #openlittermap',
            '',
            '## Session: internal notes',
            '- `v5.0.1` — refactored MetricsService internals',
        ]));

        try {
            $this->assertEquals(
                'OpenLitterMap update 🔒 We fixed a privacy issue and exports are faster. #openlittermap',
                $this->command->parsePublicBlock(File::lines($path))
            );
        } finally {
            File::delete($path);
        }
    }

    public function test_absent_public_block_returns_empty_string(): void
    {
        $date = '2099-01-02';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '## Session: internal only',
            '- `v5.0.2` — tweaked a query',
        ]));

        try {
            $this->assertSame('', $this->command->parsePublicBlock(File::lines($path)));
        } finally {
            File::delete($path);
        }
    }

    public function test_empty_public_block_returns_empty_string(): void
    {
        $date = '2099-01-03';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '## Public',
            '',
            '## Session: internal',
            '- did a thing',
        ]));

        try {
            $this->assertSame('', $this->command->parsePublicBlock(File::lines($path)));
        } finally {
            File::delete($path);
        }
    }

    public function test_strips_leading_bullet_markers_and_joins_lines(): void
    {
        $date = '2099-01-04';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '## Public',
            '- Newsletter sign-up works again.',
            '- The map loads faster.',
        ]));

        try {
            $this->assertEquals(
                'Newsletter sign-up works again. The map loads faster.',
                $this->command->parsePublicBlock(File::lines($path))
            );
        } finally {
            File::delete($path);
        }
    }

    public function test_block_stops_at_next_heading(): void
    {
        $date = '2099-01-05';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '## Public',
            'Public line.',
            '## Session: internal',
            '- Internal bullet that must never be posted',
        ]));

        try {
            $public = $this->command->parsePublicBlock(File::lines($path));

            $this->assertEquals('Public line.', $public);
            $this->assertStringNotContainsString('Internal bullet', $public);
        } finally {
            File::delete($path);
        }
    }

    public function test_parses_public_block_from_array_of_lines(): void
    {
        $body = $this->mobilePublicBody('OpenLitterMap app update 📱 Camera orientation is saved correctly now. #openlittermap');

        $this->assertEquals(
            'OpenLitterMap app update 📱 Camera orientation is saved correctly now. #openlittermap',
            $this->command->parsePublicBlock(explode("\n", $body))
        );
    }

    // ─── buildPosts ──────────────────────────────────────────────────

    public function test_empty_text_builds_no_posts(): void
    {
        $this->assertSame([], $this->command->buildPosts(''));
    }

    public function test_short_text_is_a_single_post(): void
    {
        $text = 'OpenLitterMap update 🦋 We now post to Bluesky too. #openlittermap';

        $posts = $this->command->buildPosts($text);

        $this->assertCount(1, $posts);
        $this->assertEquals($text, $posts[0]);
    }

    public function test_text_exactly_at_limit_is_a_single_post(): void
    {
        $text = str_repeat('a', 300);

        $this->assertCount(1, $this->command->buildPosts($text));
    }

    public function test_long_text_threads_with_every_post_within_limit(): void
    {
        $text = trim(str_repeat('word ', 120)); // 600 chars, word boundaries

        $posts = $this->command->buildPosts($text);

        $this->assertGreaterThan(1, count($posts), 'Over-limit text should thread');

        foreach ($posts as $i => $post) {
            $this->assertLessThanOrEqual(
                300,
                mb_strlen($post),
                'Post ' . ($i + 1) . ' exceeds 300 chars (' . mb_strlen($post) . ')'
            );
        }

        // No content lost in the split.
        $this->assertEquals($text, implode(' ', $posts));
    }

    // ─── handle ──────────────────────────────────────────────────────

    public function test_no_web_file_and_no_mobile_block_skips_silently(): void
    {
        // No local web file for the date, and the mobile fetch 404s → both sources
        // empty → silent. Web and mobile are decoupled, so the mobile fetch still runs.
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 404)]);

        $this->artisan('twitter:changelog 2099-12-31')
            ->expectsOutputToContain('No public changelog')
            ->assertSuccessful();
    }

    public function test_mobile_only_release_posts_when_no_web_file(): void
    {
        // The decoupling gap: a mobile app release on a date with no local web
        // changelog file must still post the mobile `## Public` block.
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response(
                $this->mobilePublicBody('OpenLitterMap app update 📱 New offline mode in the app. #openlittermap'),
                200
            ),
        ]);

        $this->artisan('twitter:changelog 2099-12-30')
            ->expectsOutputToContain('[1/1] OpenLitterMap app update 📱 New offline mode in the app.')
            ->doesntExpectOutputToContain('No public changelog')
            ->assertSuccessful();
    }

    public function test_absent_public_block_on_both_sources_posts_nothing(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 404)]);

        $date = '2099-01-10';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, "# Changes\n\n## Session: internal\n- refactored something\n");

        try {
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('No public changelog')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    public function test_web_public_block_is_posted(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 404)]);

        $date = '2099-01-11';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, implode("\n", [
            '# Changes',
            '',
            '## Public',
            'OpenLitterMap update 🦋 We now post to Bluesky. #openlittermap',
            '',
            '## Session: internal',
            '- changed an internal thing',
        ]));

        try {
            // Non-production test env → Social posts nothing (sent === 0 → SUCCESS),
            // but the command must reach the post path, not the silence path.
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('We now post to Bluesky')
                ->doesntExpectOutputToContain('No public changelog')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    public function test_fetches_mobile_changelog_from_github(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 404)]);

        $date = '2099-01-12';

        $this->command->fetchMobileChangelog($date);

        Http::assertSent(fn ($request) => $request->url()
            === "https://raw.githubusercontent.com/OpenLitterMap/react-native/openlittermap/v7/readme/changelog/{$date}.md");
    }

    public function test_defaults_to_yesterday_when_no_date_provided(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 404)]);

        $yesterday = now()->subDay()->toDateString();
        $path = "{$this->changelogDir}/{$yesterday}.md";
        $existed = File::exists($path);

        if (! $existed) {
            File::put($path, "# Changes\n\n## Session: internal\n- internal only\n");
        }

        try {
            $this->artisan('twitter:changelog')->assertSuccessful();
        } finally {
            if (! $existed) {
                File::delete($path);
            }
        }
    }

    // ─── Mobile (curated `## Public` from the react-native repo) ──────

    public function test_mobile_public_block_is_posted_after_web(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response(
                $this->mobilePublicBody('OpenLitterMap app update 📱 Camera orientation is fixed. #openlittermap'),
                200
            ),
        ]);

        $date = '2099-01-13';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, "# Changes\n\n## Public\nWeb public note here. #openlittermap\n");

        try {
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('[1/2] Web public note here.')
                ->expectsOutputToContain('[2/2] OpenLitterMap app update 📱 Camera orientation is fixed.')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    public function test_mobile_only_public_block_posts_when_web_is_silent(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response(
                $this->mobilePublicBody('OpenLitterMap app update 📱 Upload retry on weak connections. #openlittermap'),
                200
            ),
        ]);

        $date = '2099-01-14';
        $path = "{$this->changelogDir}/{$date}.md";

        // Web file exists but has no `## Public` block — mobile is the only source.
        File::put($path, "# Changes\n\n## Session: internal\n- internal only\n");

        try {
            $this->artisan("twitter:changelog {$date}")
                ->expectsOutputToContain('[1/1] OpenLitterMap app update 📱 Upload retry on weak connections.')
                ->doesntExpectOutputToContain('No public changelog')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }
    }

    public function test_both_sources_combine_into_a_thread_each_within_limit(): void
    {
        $mobileText = 'OpenLitterMap app update 📱 ' . str_repeat('mobile detail ', 30) . '#openlittermap';

        Http::fake([
            'raw.githubusercontent.com/*' => Http::response($this->mobilePublicBody($mobileText), 200),
        ]);

        $date = '2099-01-15';
        $path = "{$this->changelogDir}/{$date}.md";

        File::put($path, "# Changes\n\n## Public\nWeb public note. #openlittermap\n");

        try {
            $webPublic = $this->command->parsePublicBlock(File::lines($path));
            $mobilePublic = $this->command->mobilePublicBlock($date);
            $posts = array_merge($this->command->buildPosts($webPublic), $this->command->buildPosts($mobilePublic));

            $this->assertGreaterThan(1, count($posts), 'Web + long mobile should thread');
            $this->assertStringContainsString('Web public note', $posts[0]);

            foreach ($posts as $i => $post) {
                $this->assertLessThanOrEqual(
                    300,
                    mb_strlen($post),
                    'Post ' . ($i + 1) . ' exceeds 300 chars (' . mb_strlen($post) . ')'
                );
            }
        } finally {
            File::delete($path);
        }
    }

    public function test_mobile_fetch_404_falls_back_to_web_only(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 404)]);

        $this->assertSame('', $this->command->mobilePublicBlock('2099-01-16'));
    }

    public function test_mobile_fetch_500_falls_back_to_web_only(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => Http::response('', 500)]);

        $this->assertSame('', $this->command->mobilePublicBlock('2099-01-17'));
    }

    public function test_mobile_fetch_exception_is_swallowed(): void
    {
        Http::fake(['raw.githubusercontent.com/*' => fn () => throw new ConnectionException('timeout')]);

        // No exception bubbles up; web-only fallback.
        $this->assertSame('', $this->command->mobilePublicBlock('2099-01-18'));
    }

    public function test_mobile_without_public_block_contributes_nothing(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response("# Mobile Changes\n\n- internal only\n", 200),
        ]);

        $this->assertSame('', $this->command->mobilePublicBlock('2099-01-19'));
    }
}
