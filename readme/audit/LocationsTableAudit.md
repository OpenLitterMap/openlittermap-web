# Locations Table Audit

**Tables:** `countries` (110 rows), `states` (567 rows), `cities` (3,724 rows)
**Audited:** 2026-03-14

---

## Summary: All Clean

All columns and indexes across all three location tables are actively used. No drops recommended.

---

## Countries (7 columns, 3 indexes, 1 FK)

| Column | Type | Status | Evidence |
|--------|------|--------|----------|
| `id` | int unsigned | Active | PK |
| `country` | varchar(255) | Active | Location name, queried everywhere |
| `shortcode` | varchar(255) | Active | Country codes (ISO), flag emojis, URL routing, ResolveLocationAction, UploadPhotoController, AdminQueueController, LoadDataHelper, LocationController, SettingsController, DailyReportTweet |
| `created_by` | int unsigned | Active | FK → users. Set by ResolveLocationAction, UpdateCountriesTable listener, cleared by DeleteAccountController |
| `created_at` | timestamp | Active | Standard timestamp |
| `updated_at` | timestamp | Active | Standard timestamp |
| `manual_verify` | tinyint(1) | Active | Filters verified locations: LocationService, GetListOfCountriesController, LoadDataHelper, SettingsController, GenerateTimeSeries, WorldCupController |

| Index | Columns | Status |
|-------|---------|--------|
| `PRIMARY` | id | Required |
| `uq_country_shortcode` | shortcode (unique) | Active — dedup guard |
| `countries_created_by_foreign` | created_by | FK index |

---

## States (7 columns, 4 indexes, 2 FKs)

| Column | Type | Status | Evidence |
|--------|------|--------|----------|
| `id` | int unsigned | Active | PK |
| `state` | varchar(255) | Active | Location name |
| `manual_verify` | int | Active | Same pattern as countries — LocationService, GenerateTimeSeries |
| `country_id` | int unsigned | Active | FK → countries |
| `created_by` | int unsigned | Active | FK → users. Set by ResolveLocationAction, UpdateStatesCreatedby command |
| `created_at` | timestamp | Active | Standard timestamp |
| `updated_at` | timestamp | Active | Standard timestamp |

| Index | Columns | Status |
|-------|---------|--------|
| `PRIMARY` | id | Required |
| `uq_state_country` | (country_id, state) unique | Active — dedup guard |
| `states_country_id_foreign` | country_id | FK index |
| `states_created_by_foreign` | created_by | FK index |

---

## Cities (8 columns, 4 indexes, 2 FKs)

| Column | Type | Status | Evidence |
|--------|------|--------|----------|
| `id` | int unsigned | Active | PK |
| `city` | varchar(255) | Active | Location name |
| `country_id` | int unsigned | Active | FK → countries |
| `state_id` | int unsigned | Active | FK → states |
| `created_at` | timestamp | Active | Standard timestamp |
| `updated_at` | timestamp | Active | Standard timestamp |
| `created_by` | int unsigned | Active | FK → users. Set by ResolveLocationAction, UpdateCitiesCreatedby command |
| `manual_verify` | tinyint(1) | Active | Same pattern as countries/states |

| Index | Columns | Status |
|-------|---------|--------|
| `PRIMARY` | id | Required |
| `uq_city_state` | (state_id, city) unique | Active — dedup guard |
| `cities_country_id_foreign` | country_id | FK index |
| `cities_created_by_foreign` | created_by | FK index |

---

## Notes

- `manual_verify` controls which locations appear on public location pages. Set to `1`/`true` once a location has enough data. Queried heavily across LocationService, controllers, and commands.
- `created_by` tracks which user first triggered location creation. Nulled on account deletion (DeleteAccountController). FK → users on all three tables.
- `shortcode` on countries stores ISO country codes. Used for URL routing (`LoadDataHelper`), flag emojis (`DailyReportTweet`), and geocoding (`ResolveLocationAction`).
- Unique constraints (`uq_country_shortcode`, `uq_state_country`, `uq_city_state`) were added by `LocationCleanupCommand` to prevent duplicate locations.
