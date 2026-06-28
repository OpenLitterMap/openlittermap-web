# OLMbot — Automated Twitter Commands

OLMbot is the automated social posting system. All commands live in `app/Console/Commands/Twitter/` and post via the `App\Helpers\Social` dispatcher, which fans out to every enabled network — `App\Helpers\Twitter` (X) and `App\Helpers\Bluesky` — each self-gated by its own `isEnabled()`. Stat-generation logic is independent of the posting layer.

## Configuration

Twitter API credentials are in `config/services.php` under the `twitter` key, backed by env vars:

```
TWITTER_ENABLED=false           # master on/off — default off; requires paid X API credits
TWITTER_API_CONSUMER_KEY=
TWITTER_API_CONSUMER_SECRET=
TWITTER_API_ACCESS_TOKEN=
TWITTER_API_ACCESS_SECRET=
```

Browsershot Chromium path is configurable via `config/services.php`:

```
BROWSERSHOT_CHROME_PATH=       # defaults to /snap/bin/chromium
```

The `Twitter` helper has a master switch — `Twitter::isEnabled()` gates all three methods (`sendTweet`, `sendThread`, `sendTweetWithImage`) and requires `TWITTER_ENABLED=true` **and** the `production` environment **and** a configured consumer key. It **defaults off** (`TWITTER_ENABLED` defaults to `false`), so OLMbot posts nothing until explicitly enabled — a zero-code kill switch for when X API credits are unavailable. An empty/missing key can't misfire a live call. Each command/listener also has its own production guard.

## Social dispatcher & Bluesky

Commands/listeners call `App\Helpers\Social` (`text`, `thread`, `withImage`), which posts to every enabled network. `Social::thread` sums `sent`/`total` over **enabled networks only**, so the commands' `sent < total → FAILURE` check stays correct and a no-network environment returns `sent=0` (→ SUCCESS). Adding a network is a new helper + two lines in `Social` — deliberately no registry/plugin layer.

### Bluesky (`App\Helpers\Bluesky`)

AT Protocol XRPC via Laravel `Http`. Config under the `bluesky` key:

```
BLUESKY_ENABLED=false                    # master on/off — default off
BLUESKY_IDENTIFIER=olmbot.bsky.social
BLUESKY_APP_PASSWORD=                     # app password, never the account password
BLUESKY_SERVICE=https://bsky.social      # optional override
```

`Bluesky::isEnabled()` = `enabled && production && app_password` (logs a warning if enabled in prod with no password). Methods mirror the Twitter helper — `post()`, `thread()`, `postWithImage()`:

- **Auth:** `createSession` (identifier + app password) → `accessJwt` + `did`, once per send operation.
- **Threads:** each post after the first carries `reply.root` + `reply.parent` strongRefs from the previous `createRecord`.
- **Images:** recompressed under Bluesky's ~1MB blob limit (intervention/image, JPEG, quality-stepped + downscale) before `uploadBlob`; falls back to text-only if it can't get under. Embedded as `app.bsky.embed.images`.
- **Links:** bare `https?://` URLs get `app.bsky.richtext.facet#link` facets (UTF-8 byte ranges) so they're clickable — Bluesky does not auto-link plain text. Hashtag facets are deferred (post as plain `#tags`).

## Schedule (Kernel.php)

| Command | Frequency | Constraints |
|---|---|---|
| `twitter:daily-report` | `dailyAt('00:00')` | None |
| `twitter:changelog` | `dailyAt('07:00')` | None |
| `twitter:weekly-impact-report-tweet` | `weeklyOn(1, '06:30')` (Monday) | None |
| `twitter:monthly-impact-report-tweet` | `monthlyOn(1, '06:30')` | None |
| `twitter:annual-impact-report-tweet` | `yearlyOn(1, 1, '06:30')` (Jan 1) | None |

## Event-driven posts (not scheduled)

These fire from domain events on user activity, not the cron schedule — volume scales with uploads/badges, so they are the main X API *write* driver. Wired in `app/Providers/EventServiceProvider.php`; all gated by `Twitter::isEnabled()`.

| Listener | Event | Fires | Posts |
|---|---|---|---|
| `TweetNewCity` | `NewCityAdded` | per new city uploaded | `sendTweet()` (text) |
| `TweetNewState` | `NewStateAdded` | per new state uploaded | `sendTweet()` (text) |
| `TweetNewCountry` | `NewCountryAdded` | per new country uploaded | `sendTweet()` (text) |
| `TweetBadgeCreated` | `BadgeCreated` (queued) | per badge unlocked | `sendTweetWithImage()` |

## Commands

### twitter:daily-report

**Class:** `App\Console\Commands\Twitter\DailyReportTweet`
**Send method:** `Twitter::sendThread()` (always 2-tweet thread)
**Image:** No

**Data queried:**
- `metrics` table — global daily row (`timescale=1`, `location_type=Global`, `location_id=0`, `user_id=0`) for yesterday. Selects `uploads`, `tags`, `litter`, `xp`
- `metrics` table — global all-time row (`timescale=0`, `bucket_date=1970-01-01`) for cumulative totals. Single PK lookup
- `metrics` table — per-country daily rows (`location_type=Country`) joined with `countries` for top 3 by tags (with names) + active country count
- `metrics` table — daily rows going back up to 365 days for upload streak calculation
- `cities` table — joined with `countries`, `whereDate('created_at', yesterday)` for new cities. LIMIT 3
- `users` table — new users yesterday + total count
- `littercoin` table — minted yesterday

**Gamification features:**
- **Upload streak:** Counts consecutive days with uploads (backwards from yesterday)
- **Season label:** Progress toward next major milestone (100K, 250K, 500K, 750K, 1M)
- **Next milestone:** Step size: 5K under 100K, 10K under 1M, 50K above
- **Country lead streak:** How many consecutive days the #1 country has held the lead (single batch query for last 31 days)
- **Mission line:** Motivational CTA with 3 frames (streak+close, this-week pace, default)
- **New cities:** Cities first seen yesterday with country flags

**Conditional lines (skip, don't show zeros):**
- Streak line: only if streak >= 2
- Littercoin line: only if count > 0
- New cities line: only if cities exist
- Lead defense: falls back to plain format if no previous day data

**Example Tweet 1 (Scoreboard):**
```
📊 OpenLitterMap — 2026-03-21
📅 Day 147 of consecutive uploads!
🗺️ Season: Road to 1M · 52.1% complete

👥 3 new users (8,967 total)
📸 47 uploads (521,593 total)
🏷️ 312 tags (1,600,678 total)
🪙 2 Littercoin mined

🎯 Only 3,407 to 525,000 photos!
```

**Example Tweet 2 (Podium + Mission):**
```
🏆 Country Podium
🥇 🇮🇪 Ireland leads the way — Day 4!
🥈 🇳🇱 Netherlands
🥉 🇺🇸 United States of America
🌍 5 countries active

🗺️ New: Porto 🇵🇹, Ghent 🇧🇪

🎯 41 uploads today keeps the streak alive and pushes us under 3,000 to 525k!

#openlittermap #citizenscience #OLMbot
```

**No data:** If yesterday's `uploads === 0`, skips silently (no tweet).

**Graceful degradation:** Each gamification method is wrapped in try/catch — if any fails, that line is skipped and the thread still posts.

**Tweet length enforcement:** Both tweets are truncated to 280 chars via `truncateTweet()`. Partial thread failures (`sent < total`) return FAILURE.

**Milestone formatting:** `formatMilestone()` — under 1M: `525k`. At/above 1M: `1M`, `1.05M`, `2M`.

**External dependencies:** None beyond Twitter API.

---

### twitter:changelog

**Class:** `App\Console\Commands\Twitter\ChangelogTweet`
**Signature:** `twitter:changelog {date?}` — defaults to yesterday
**Send method:** `Social::thread()` (one post when ≤300 chars, otherwise a thread)
**Image:** No

The command posts the curated **`## Public`** block from `readme/changelog/{date}.md` — plain-language release notes written for OLM users, educators, the citizen-science community, and funders. It does **not** post the raw internal session bullets (those stay as the team's internal record). The `## Public` convention is documented in `CLAUDE.md` → "Daily Changelog".

**Two sources, combined:**
- **Web:** the `## Public` block in the local `readme/changelog/{date}.md`.
- **Mobile:** the `## Public` block in the react-native repo's changelog, fetched from `https://raw.githubusercontent.com/OpenLitterMap/react-native/openlittermap/v7/readme/changelog/{date}.md`. Mobile posts require the **mobile repo to adopt the same `## Public` convention**; until it does (or if the fetch fails), mobile is silently skipped. Mobile blocks self-label in the same house style (e.g. "OpenLitterMap app update 📱…").
- Each source builds its own post(s): `array_merge(buildPosts(webPublic), buildPosts(mobilePublic))`. A day where both have content becomes a short thread — web post, then mobile post; most days only one or neither.

**Behaviour:**
- No local changelog file for the date → logs "No changelog found", exits SUCCESS (no mobile fetch).
- Neither source has a `## Public` block (or both empty) → logs "No public changelog", **posts nothing**, exits SUCCESS. The common, correct outcome — most days are internal-only.
- One or both blocks present → each is posted as **one Bluesky post** when ≤300 chars, else threaded on word boundaries (every post ≤300).
- **Mobile fetch is best-effort:** any failure (non-200, network error, timeout, exception) is logged and the bot continues web-only — a mobile failure never breaks the command.

**Cadence — event-driven, not daily.** The command is still scheduled `dailyAt('07:00')`, but it self-silences on internal-only days, so it effectively fires only per release/milestone that ships a non-empty `## Public` block.

**`## Public` block format** (see `CLAUDE.md` → "Daily Changelog" for authoring rules):
- Runs from the `## Public` heading to the next markdown heading (or EOF). Parsed identically for web (`File::lines`) and mobile (`explode` of the fetched body).
- 0–3 plain-language points written as tight prose; a leading `- ` bullet marker is tolerated and stripped, then lines are joined into one prose string.
- No internals (file paths, class names, routes). Lead with privacy/safeguarding/access, then usability/speed.
- **One `## Public` per release, on the release-completion day** — a multi-day feature consolidates to a single block, not one per day it spanned.

**Example post (single Bluesky post, ≤300 chars):**
```
OpenLitterMap update 🔒 Data exports now require a free account, and we fixed a privacy issue that could have exposed school students' names. Exports are also faster, with simpler format options. #openlittermap
```

**No data:** No file → "No changelog found"; no `## Public` block on either source → "No public changelog". Both skip silently.

**External dependencies:** GitHub raw content (mobile `## Public` block; graceful web-only fallback on failure).

---

### twitter:weekly-impact-report-tweet

**Class:** `App\Console\Commands\Twitter\WeeklyImpactReportTweet`
**Send method:** `Twitter::sendTweetWithImage()`
**Image:** Yes — Browsershot screenshot

**Data queried:** None. Screenshots a live URL.

**Process:**
1. Calculates last week's ISO year/week
2. Browsershot screenshots `https://openlittermap.com/impact/weekly/{isoYear}/{isoWeek}` at 1200x800
3. Saves to `public/images/reports/weekly/{year}/{week}/impact-report.png`
4. Tweets with image
5. Deletes PNG after sending

**Example tweet:**
```
Weekly Impact Report for week 12 of 2026. Join us at openlittermap.com #litter #citizenscience #impact #openlittermap
```

**No data:** No data check. Always screenshots and tweets whatever the page shows. If Browsershot fails (e.g. Chromium missing), returns FAILURE.

**External dependencies:**
- Browsershot (`spatie/browsershot`)
- Chromium at path from `config('services.browsershot.chrome_path')` (defaults to `/snap/bin/chromium`)
- Network access to `https://openlittermap.com` (screenshots the live production site)

---

### twitter:monthly-impact-report-tweet

**Class:** `App\Console\Commands\Twitter\MonthlyImpactReportTweet`
**Send method:** `Twitter::sendTweetWithImage()`
**Image:** Yes — Browsershot screenshot

**Data queried:** None. Screenshots a live URL.

**Process:**
1. Calculates last month's year/month
2. Browsershot screenshots `https://openlittermap.com/impact/monthly/{year}/{month}` at 1200x800 with `fullPage()` and `waitUntilNetworkIdle()`
3. Saves to `public/images/reports/monthly/{year}/{month}/impact-report.png`
4. Tweets with image
5. Deletes PNG after sending

**Example tweet:**
```
Monthly Impact Report for February 2026. Join us at openlittermap.com #litter #citizenscience #impact #openlittermap
```

**No data:** Same as weekly — no data check, always screenshots and tweets.

**External dependencies:**
- Browsershot (`spatie/browsershot`)
- Chromium at path from `config('services.browsershot.chrome_path')`
- Network access to `https://openlittermap.com`

---

### twitter:annual-impact-report-tweet

**Class:** `App\Console\Commands\Twitter\AnnualImpactReportTweet`
**Send method:** `Twitter::sendTweetWithImage()`
**Image:** Yes — Browsershot screenshot

**Data queried:** None. Screenshots a live URL.

**Process:**
1. Calculates last year
2. Browsershot screenshots `https://openlittermap.com/impact/annual/{year}` at 1200x800 with `fullPage()` and `waitUntilNetworkIdle()`
3. Saves to `public/images/reports/annual/{year}/impact-report.png`
4. Tweets with image
5. Deletes PNG after sending

**Example tweet:**
```
Annual Impact Report for 2025. Join us at openlittermap.com #litter #citizenscience #impact #openlittermap
```

**No data:** Same as weekly/monthly — no data check, always screenshots and tweets.

**External dependencies:**
- Browsershot (`spatie/browsershot`)
- Chromium at path from `config('services.browsershot.chrome_path')`
- Network access to `https://openlittermap.com`

## Twitter Helper

`App\Helpers\Twitter` — three static methods:

| Method | API Version | Use Case |
|---|---|---|
| `sendTweet($message)` | v2 | Single text tweet |
| `sendThread($messages)` | v2 | Reply chain, returns `{first_id, sent, total}` |
| `sendTweetWithImage($message, $imagePath)` | v1.1 (media upload) + v2 (tweet post) | Tweet with attached image |

All three methods guard on `app()->environment('production')` and `$consumer_key !== null`.

## Tests

- `tests/Feature/Twitter/DailyReportTweetTest.php` — 28 tests: streak (0/1/5/gap), milestone boundaries (100K/1M), season labels (all 6 tiers), lead line (same/new/no-data), mission frames (3), conditional skipping (littercoin/streak/cities), thread output, no-data skip, formatMilestone (k/M), tweet length enforcement
- `tests/Feature/Twitter/ChangelogTweetTest.php` — 22 tests: `parsePublicBlock` (present prose / absent / empty / bullet-marker stripping / stops at next heading / from an array of lines), `buildPosts` (empty → none / short → one post / exactly-at-limit / over-limit threads with every post ≤300 and no content lost), `handle` (no file → "No changelog found", absent block both sources → "No public changelog" + posts nothing, web block → posted, mobile fetch hits the GitHub URL, defaults to yesterday), and mobile (mobile `## Public` posted after web, mobile-only when web silent, both combine into a ≤300 thread, fetch 404/500/exception → web-only, mobile without a `## Public` block contributes nothing)

No tests exist for `WeeklyImpactReportTweet`, `MonthlyImpactReportTweet`, or `AnnualImpactReportTweet` (Browsershot dependency). The `GenerateImpactReportController` is tested in `tests/Feature/Reports/GenerateImpactReportTest.php` (8 tests: weekly/monthly/annual rendering, future date, invalid period, v5 brands query, zero data).

## Summary

| Command | Tables | Send Method | Image | No-Data | External Deps |
|---|---|---|---|---|---|
| `daily-report` | `metrics`, `users`, `countries`, `cities`, `littercoin` | `sendThread()` (2 tweets) | No | Skips | None |
| `changelog` | None (web `## Public` block + mobile `## Public` from GitHub) | `Social::thread()` (one post per source, else thread) | No | Skips (no file / no `## Public` block) | GitHub raw content (mobile, graceful web-only fallback) |
| `weekly-impact-report` | None | `sendTweetWithImage()` | Browsershot 1200x800 | Always tweets | Browsershot, Chromium, network |
| `monthly-impact-report` | None | `sendTweetWithImage()` | Browsershot 1200x800 fullPage | Always tweets | Browsershot, Chromium, network |
| `annual-impact-report` | None | `sendTweetWithImage()` | Browsershot 1200x800 fullPage | Always tweets | Browsershot, Chromium, network |
