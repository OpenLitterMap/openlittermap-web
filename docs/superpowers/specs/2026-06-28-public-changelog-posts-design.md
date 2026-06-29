# Public changelog posts for OLMbot — design

**Date:** 2026-06-28
**Status:** Approved

## Goal

OLMbot's `twitter:changelog` command currently dumps the raw, internal commit-level
changelog bullets to the public feed (an overview-counts post + a thread of every
`- ...` line, plus a mobile changelog fetched from GitHub). That's contributor noise,
not an audience message. Replace it: the command posts a curated `## Public` block —
plain-language release notes written for OLM users, educators, the citizen-science
community, and funders — or posts **nothing** when there's no user-facing news (most
days). The detailed internal changelog file is untouched; it just stops doubling as
the public source. Mobile is kept, but curated the same way (see Mobile, below) — the
raw mobile bullets go too.

## Model — the `## Public` convention

A changelog file (`readme/changelog/YYYY-MM-DD.md`) **may** contain one `## Public`
block. Rules:

- **0–3 plain-language points, written as tight prose**, not a bullet list. The whole
  post must fit **one Bluesky post (300 chars)** — that ceiling is the brittle
  constraint, so write to it. If it genuinely needs more, it threads, but one post
  under 300 is the default unit.
- **Audience:** OLM users, educators/schools, citizen-science community, funders.
  **Not contributors** — they read the PR.
- **No internals:** no file paths, class/function names, route/throttle details. If a
  teacher couldn't follow it, rewrite it.
- **Order:** lead with what matters most to an observer — privacy/safeguarding and
  access changes first, usability/speed after.
- **One block per release, on the release-completion day.** A multi-day feature gets a
  single `## Public` post on the day it lands, not one fragment per day it spanned
  (fragmenting re-buries the headline change). Consolidate on the release day; leave the
  earlier days' blocks absent.
- **Silence is correct.** If nothing is user-facing, the block is absent (or empty)
  and the bot posts nothing. Most days are internal-only — that's expected.

The block runs from the `## Public` heading to the next markdown heading (or EOF).
Authors write prose; a leading `- ` bullet marker is tolerated and stripped.

## Mobile (curated, not raw)

The mobile (react-native) changelog is back — but curated via `## Public`, not the raw
bullets. The bot fetches the mobile repo's changelog
(`…/react-native/openlittermap/v7/readme/changelog/{date}.md`) and parses its `## Public`
block with the **same** parser (refactored to take an iterable of lines, so it runs on
both `File::lines($localPath)` and `explode("\n", $body)`). Web and mobile each build
their own post(s) and combine:
`array_merge(buildPosts(webPublic), buildPosts(mobilePublic))` — a day where both have
content becomes a short thread (web post, then mobile post); most days only one or
neither. Mobile posts require the **mobile repo to adopt the same `## Public`
convention**; until it does — or if the fetch fails (non-200, network error, timeout,
exception) — mobile contributes nothing and the bot posts web-only. A mobile failure is
logged, never thrown.

## Command behaviour — `App\Console\Commands\Twitter\ChangelogTweet`

| | Old | New |
|---|---|---|
| Source | every `- ...` line in the local file **+** raw mobile bullets fetched from GitHub | the `## Public` block of the local file **+** the `## Public` block of the mobile changelog |
| Post 1 | overview counts (`N web · M mobile improvements 🧵 Thread ↓`) | dropped |
| Post 2+ | raw internal bullets grouped `🌐 Web` / `📱 Mobile`, threaded at 280 | one `## Public` post per source (≤300), else a word-boundary thread |
| Nothing user-facing | still posted the overview + whatever bullets existed | posts nothing, logs "No public changelog", exits SUCCESS |
| Mobile | raw bullets | curated `## Public` only; graceful web-only fallback on fetch failure |

`handle()`: env guard → resolve date/path →
`webPublic = File::exists($path) ? parsePublicBlock(File::lines($path)) : ''` (no early
return — web and mobile are decoupled), `mobilePublic = mobilePublicBlock($date)` →
`posts = array_merge(buildPosts(webPublic), buildPosts(mobilePublic))` → empty ⇒ "No
public changelog … nothing to post", SUCCESS → else `Social::thread($posts)` with the
existing `sent === 0` / `sent < total` result handling. The mobile fetch always runs, so
a mobile-only release on a date with no local web file still posts.

Public methods (directly testable): `parsePublicBlock(iterable $lines): string`,
`buildPosts(string $text): array`, `mobilePublicBlock(string $date): string`,
`fetchMobileChangelog(string $date): string` (raw body or '' on any failure).
`cleanChange`, `parseEntries`, `buildThread` and the old overview/raw-bullet path are
removed.

## Cadence

Event-driven, enforced by the command, not the scheduler. `twitter:changelog` still runs
`dailyAt('07:00')`, but it now self-silences on internal-only days, so it effectively
fires only per release/milestone that ships a non-empty `## Public` block. The
always-on `twitter:daily-report` stats post is untouched and out of scope.

## Posting

Through `App\Helpers\Social::thread` → Bluesky (X gated off). Bluesky limit is 300; a
single ≤300-char post goes as a one-element thread. No change to the Social/Bluesky
helpers.

## Docs & examples

- `CLAUDE.md` — document the `## Public` convention in "Daily Changelog" + a BOOP step.
- `readme/Twitter.md` — rewrite the `twitter:changelog` section to the new contract.
  **Not renamed** to a network-neutral filename: the prior Bluesky work already folded
  Bluesky into `Twitter.md` without a rename; following that precedent keeps the change
  minimal and avoids touching the CLAUDE.md index + skill table for no real gain.
- Three first worked `## Public` blocks ship with the convention:
  `2026-06-27.md` (newsletter form fixed), `2026-06-28.md` (OLMbot on Bluesky),
  `2026-05-04.md` (the export release — login-required + student-privacy fix, on its
  completion day). The export work spans `2026-05-02…05-04`; per the one-block-per-release
  rule, only `05-04` carries the block and the earlier days stay absent. Every other file
  leaves the block absent — demonstrating the silence rule.

## Testing

`tests/Feature/Twitter/ChangelogTweetTest.php` (23 tests) to the new contract:
`parsePublicBlock` present/absent/empty/bullet-stripping/stops-at-next-heading/from an
array of lines; `buildPosts` one-post-≤300 / threads->each-≤300 / empty->[]; `handle`
posts present web block, posts nothing + "No public changelog" when both sources absent
(incl. no web file at all), defaults to yesterday, mobile fetch hits the GitHub URL;
mobile combine (mobile post after web), mobile-only when web silent, mobile-only release
with **no web file** -> still posted, both->thread each ≤300, fetch
404/500/exception->web-only, mobile without `## Public`->nothing. Whole
`tests/Feature/Twitter/` dir stays green.

## Out of scope

`twitter:daily-report`; renaming `Twitter.md`; any AI/summarisation; new config; the
Social/Bluesky helpers. (The mobile app authoring its own `## Public` blocks is a
mobile-repo task — this command just consumes them when present.)
