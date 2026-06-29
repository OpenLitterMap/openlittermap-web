# Bluesky posting for OLMbot — design

**Date:** 2026-06-28
**Status:** Approved

## Goal

OLMbot currently posts to X/Twitter (now disabled — 0 API credits). Add Bluesky as a posting channel without touching any stat-generation logic. Keep X code in place (gated off) so dual-posting or switching back is a flag.

## Architecture

A thin dispatcher over per-network helpers. **No registry/plugin abstraction** — just direct calls; Mastodon/Threads later is one more helper + two lines in the dispatcher.

```
commands/listeners ──► App\Helpers\Social ──► App\Helpers\Twitter   (gated off)
                                          └─► App\Helpers\Bluesky   (new)
```

- **`App\Helpers\Bluesky`** mirrors the `Twitter` helper's three shapes:
  - `post(string $text): void` — `createSession` → `createRecord`
  - `thread(array $messages): array` — auth once, post a reply chain; each record after the first carries `reply.root` + `reply.parent` strongRefs (`{uri,cid}` from `createRecord`). Returns `{first_id, sent, total}` (same shape as `Twitter::sendThread`).
  - `postWithImage(string $text, string $path): void` — recompress under the blob limit → `uploadBlob` → `createRecord` with an `app.bsky.embed.images` embed.
  - `isEnabled(): bool` = `enabled && production && app_password !== null`. Logs a warning when `enabled && production` but the password is missing (misconfigured prod is visible, not silently dead).
  - Built on Laravel `Http` (so tests use `Http::fake()`). Every network call wrapped in try/catch + `Log` — a failure never breaks the calling command.
- **`App\Helpers\Social`** — `text()`, `thread()`, `withImage()` fan out to enabled networks. `thread()` sums `sent`/`total` over **enabled** networks only (a disabled network contributes nothing, so the commands' `sent < total → FAILURE` check stays correct; with no network enabled it returns `sent=0,total=0` → the existing `sent === 0 → SUCCESS` branch fires).

## Config (`config/services.php`)

```php
'bluesky' => [
    'enabled'      => env('BLUESKY_ENABLED', false),
    'identifier'   => env('BLUESKY_IDENTIFIER'),
    'app_password' => env('BLUESKY_APP_PASSWORD'),
    'service'      => env('BLUESKY_SERVICE', 'https://bsky.social'),
],
```

## Call sites (9 — one word each)

`Twitter::sendTweet` → `Social::text`; `Twitter::sendThread` → `Social::thread`; `Twitter::sendTweetWithImage` → `Social::withImage`. In: DailyReportTweet, ChangelogTweet (threads); Weekly/Monthly/AnnualImpactReportTweet, TweetBadgeCreated (images); TweetNewCity/State/Country (text). Stat logic untouched.

## Refinements (from review)

1. **<1MB blob limit.** `uploadBlob` rejects images over ~1MB. Before upload, recompress with intervention/image (GD): cap width, step JPEG quality (85→40), downscale as last resort, until `<= 950,000 bytes`. If still over, skip the image and post text-only. Without this, the 4 image paths silently fail.
2. **Map-link facet in scope (v1).** Bluesky does not auto-link plain-text URLs. Build `app.bsky.richtext.facet#link` facets with UTF-8 **byte** ranges for any `https?://` URL in the text (covers the new-city map link). Hashtag facets deferred — plain `#tags` post fine, just unlinked.
3. **300-char limit:** callers already truncate to 280 — no change.

## Testing

`Http::fake()` unit tests for `Bluesky` (session→record, thread reply-ref chaining, image uploadBlob+embed, URL facet byte ranges, disabled no-op, error swallow) and `Social` (fan-out, enabled-only summation). Existing 73 Twitter tests and the command tests stay green.

## Out of scope

Hashtag/mention facets, link cards (external embeds), session-token caching, multi-account, any config registry.
