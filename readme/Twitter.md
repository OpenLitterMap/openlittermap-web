# OLMbot — Automated Twitter Commands

OLMbot is the automated Twitter/X posting system. All commands live in `app/Console/Commands/Twitter/` and use the `App\Helpers\Twitter` helper for API calls.

## Configuration

Twitter API credentials are in `config/services.php` under the `twitter` key, backed by env vars:

```
TWITTER_API_CONSUMER_KEY=
TWITTER_API_CONSUMER_SECRET=
TWITTER_API_ACCESS_TOKEN=
TWITTER_API_ACCESS_SECRET=
```

The `Twitter` helper has a production guard — all three methods (`sendTweet`, `sendThread`, `sendTweetWithImage`) silently no-op outside `production`. Each command also has its own production guard at the top of `handle()`.

## Schedule (Kernel.php)

| Command | Frequency | Constraints |
|---|---|---|
| `twitter:daily-report` | `dailyAt('00:00')` | None |
| `twitter:changelog` | `dailyAt('07:00')` | None |
| `twitter:weekly-impact-report-tweet` | `weeklyOn(1, '06:30')` (Monday) | None |
| `twitter:monthly-impact-report-tweet` | `monthlyOn(1, '06:30')` | None |

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
**Send method:** `Twitter::sendThread()` (overview tweet + grouped change tweets)
**Image:** No

**Data sources:**
- **Web:** `readme/changelog/{date}.md` (local file). Entries default to web unless prefixed with `[Mobile]`
- **Mobile:** Fetched from `https://raw.githubusercontent.com/OpenLitterMap/react-native/openlittermap/v7/readme/changelog/{date}.md` via HTTP. All entries from this file are treated as mobile. Falls back to web-only if fetch fails (404, network error, timeout)

**Entry prefixes (local file only):**
- `- [Web] description` → web entry (prefix stripped)
- `- [Mobile] description` → mobile entry (prefix stripped)
- `- description` (no prefix) → defaults to web
- Version prefixes (`v5.0.3 —`) and backticks are cleaned from all entries
- Mobile entries from both local `[Mobile]` prefixes and the GitHub fetch are merged

**Thread structure:**
- **Tweet 1 (overview):** Date, entry counts by platform, thread indicator
- **Tweet 2+ (grouped changes):** Web section first (`🌐 Web`), then mobile (`📱 Mobile`), split across tweets if > 280 chars
- Hashtags on final tweet only

**Example Tweet 1 (Overview):**
```
🔧 OpenLitterMap — Changes for 2026-03-22

3 web improvements · 2 mobile improvements

🧵 Thread ↓
```

**Example Tweet 2 (Changes):**
```
🌐 Web
- Fix admin permissions for superadmin role
- Scheduler restored for automated tweets
- Faster cluster rendering at high zoom

📱 Mobile
- Camera orientation saved correctly
- Upload retry on weak connections

#openlittermap #changelog
```

**No data:** If no changelog file or empty file, logs "No changelog found" and skips.

**External dependencies:** GitHub raw content (for mobile changelog fetch, graceful fallback on failure)

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
- Chromium at `/snap/bin/chromium` (Ubuntu snap — won't work on macOS/Docker without Snap)
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
- Chromium at `/snap/bin/chromium`
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
- `tests/Feature/Twitter/ChangelogTweetTest.php` — 26 tests: overview counts, prefix parsing ([Web]/[Mobile]/default), GitHub raw content call verification, web-only/mobile-only, long changelog splits, oversized single line truncation, no-file skip, thread structure, cleanChange, singular/plural, sendThread return shape, mobile fetch from GitHub (success/404/500/merge/URL/thread integration)

No tests exist for `WeeklyImpactReportTweet` or `MonthlyImpactReportTweet` (Browsershot dependency).

## Summary

| Command | Tables | Send Method | Image | No-Data | External Deps |
|---|---|---|---|---|---|
| `daily-report` | `metrics`, `users`, `countries`, `cities`, `littercoin` | `sendThread()` (2 tweets) | No | Skips | None |
| `changelog` | None (reads local + GitHub changelog files) | `sendThread()` (overview + grouped) | No | Skips | GitHub raw content |
| `weekly-impact-report` | None | `sendTweetWithImage()` | Browsershot 1200x800 | Always tweets | Browsershot, Chromium, network |
| `monthly-impact-report` | None | `sendTweetWithImage()` | Browsershot 1200x800 fullPage | Always tweets | Browsershot, Chromium, network |
