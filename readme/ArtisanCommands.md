# OLM Custom Artisan Commands

All custom commands registered via `app/Console/Commands/`. Auto-discovered by Laravel.

---

## Scheduler (Kernel.php)

| Command | Frequency |
|---------|-----------|
| `twitter:daily-report` | Daily at 00:00 |
| `clustering:process-dirty` | Every 5 minutes |
| `clustering:update --all --all-teams` | Daily at 00:10 |
| `twitter:weekly-impact-report-tweet` | Weekly (Monday 06:30) |
| `twitter:monthly-impact-report-tweet` | Monthly (1st at 06:30) |

---

## Clustering

| Command | Purpose |
|---------|---------|
| `clustering:update --populate` | Backfill NULL tile_keys on photos (50k chunks) |
| `clustering:update --all` | Full recluster all global + tile zoom levels |
| `clustering:update --team=N` | Cluster a specific team |
| `clustering:update --all-teams` | Cluster all teams with photos |
| `clustering:update --stats` | Show statistics + integrity check |
| `clustering:process-dirty` | Process dirty tiles + teams (incremental, scheduled) |
| `clustering:check-migration` | Verify clustering migration state |

See `readme/Clustering.md` for full details.

---

## v5 Migration (`app/Console/Commands/tmp/v5/`)

| Command | Purpose |
|---------|---------|
| `olm:v5` | Main migration script — converts photos to v5 tag structure |
| `olm:v5:reset --force` | Reset all v5 migration changes (destructive) |
| `olm:verify-tags-fixed {--user=}` | Verify tag migration accuracy for users |
| `olm:locations:analysis` | Analyze location data integrity (duplicates, orphans) |
| `olm:locations:cleanup` | Merge duplicate locations, remove orphans |
| `olm:extract-brands` | Extract brand-object relationships from all photos |
| `olm:auto-create-brand-relationships` | Auto-create brand relationships (dry-run by default) |
| `olm:validate-brands` | Validate brand-object relationships using AI |

These live in `tmp/` and are intended for the v5 migration period only.

---

## Photos

| Command | Purpose |
|---------|---------|
| `olm:generate-data {photos=1500}` | Generate test data in Cork, Ireland |
| `photos:resize-to-500x500` | Create 500x500 versions of level 3 images for AI |
| `olm:photos:regenerate-time-series` | Regenerate photos_per_month for all locations |

---

## Tags

| Command | Purpose |
|---------|---------|
| `seed:tags` | Run GenerateTagsSeeder (required for test DB setup) |
| `tags:verify-for-user-id {user_id}` | Verify remaining tags for a user |

---

## Locations

| Command | Purpose |
|---------|---------|
| `locations:fix-and-merge-duplicates` | Delete unused locations |
| `locations:fix-duplicates` | Merge locations and delete old ones |
| `locations:fix-countries-createdby` | Fix created_by for countries |
| `locations:fix-states-createdby` | Fix created_by for states |
| `locations:fix-cities-createdby` | Fix created_by for cities |

---

## Redis

| Command | Purpose |
|---------|---------|
| `redis:refresh-total-contributors` | Calculate total contributors per city → Redis |
| `redis:reset-all-totals-for {type}` | Get category totals → Redis |
| `redis:GenerateTotalPhotosPerMonthForCountry` | Update monthly totals for each location |

These are legacy commands that write to old Redis key patterns. May be superseded by `MetricsService` + `RedisMetricsCollector`.

---

## Teams

| Command | Purpose |
|---------|---------|
| `teams:update-for-id {team_id}` | Update stats for a specific team |

---

## Twitter / Social

| Command | Purpose |
|---------|---------|
| `twitter:daily-report` | Tweet daily OLM summary (scheduled) |
| `twitter:weekly-impact-report-tweet` | Generate + tweet weekly impact report (scheduled) |
| `twitter:monthly-impact-report-tweet` | Generate + tweet monthly impact report (scheduled) |

---

## Global / Utility

| Command | Purpose |
|---------|---------|
| `global:compile-verified-translated-tags` | Generate result_string for global map |
| `global:reset-results-string` | Reset all result_strings to null |
| `olm:generate-daily-leaderboards` | Generate daily leaderboard snapshots |
| `olm:check-daily-for-upload` | Check if contributing users uploaded today |
| `olm:send-email-to-subscribed` | Send email to all subscribed users |
| `olm:unify-translation-files {path}` | Copy missing translation keys from English |
| `littercoin:reset` | Reset littercoin owed to all users |
| `school:assign-manager {email}` | Assign school_manager role to a user |
