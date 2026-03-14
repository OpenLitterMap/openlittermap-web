# Miscellaneous Tables Audit

**Audited:** 2026-03-14

---

## Active Systems — Do Not Touch

| Table | Rows | Active Files | Purpose |
|-------|------|-------------|---------|
| `annotations` | — | 5 files (VerifyBoxController, Photo, User models) | AI bounding box annotations |
| `badges` | — | 24 files (AddTagsToPhotoAction, Badge actions, User model) | Awarded for bags_litter at OSM location types |
| `user_badges` | — | Part of badges system | User↔badge pivot |
| `cleanups` | — | 23 files (4 controllers, model, User relation) | Community cleanup events (create, join, leave, GeoJSON) |
| `cleanup_user` | — | Part of cleanups system | User↔cleanup pivot |
| `littercoins` | — | 15 files (events, listeners, controllers, User model) | Littercoin gamification economy |
| `merchants` | — | 6 files (3 controllers, model, DeleteAccountController) | Littercoin redemption partners |
| `merchant_photos` | — | 2 files (MerchantPhoto model, DeleteAccountController) | Merchant photo uploads |
| `admin_verification_logs` | — | 5 files (LogAdminVerificationAction, AdminUsersController) | Audit trail for admin tag edits |
| `taggables` | — | 7 files (ManagesTaggables trait, PerfectBrandMatcher, DynamicBrandConfig) | v5 polymorphic extra-tag system |
| `user_achievements` | — | Part of achievements system | User↔achievement pivot |
| `achievements` | — | Part of achievements system | Achievement definitions |
| `subscribers` | — | Used by email unsubscribe system | Email subscriber tokens |
| `location_merges` | — | LocationCleanupCommand (migration tool) | Location merge audit trail — keep until migration complete |
| `email_subscriptions` | — | Only `EmailSubscription.php` model exists — 0 controllers, 0 routes | Orphaned email newsletter table |

---

## Phase A — Safe to Drop Now

### Tables to Drop

| Table | Rows | Grep Evidence | Notes |
|-------|------|--------------|-------|
| `awards` | — | 0 references as table/model in app/ | Badge system replaced it. No model file exists. |
| `global_levels` | — | Only `GlobalLevel.php` model — referenced in WorldCupController (legacy) | Replaced by `config/levels.php` + `LevelService` |
| `levels` | — | Only `Level.php` model — 0 active usage | Replaced by `config/levels.php` + `LevelService` |
| `suburbs` | — | Only `Suburb.php` model — 0 active usage | Replaced by Country/State/City location system |
| `experience` | — | 0 references in app/ | Never used. XP tracked in `metrics` + `users.xp` |
| `farming` | — | 0 references in app/ | Never used. No model exists. |
| `firewall` | — | 0 references in app/ | Never used. No model exists. |
| `halls` | — | 0 references in app/ | Never used. No model exists. |
| `donates` | — | 0 references in app/, no model file | Orphaned table from old donations system |
| `websockets_statistics_entries` | — | 0 references in app/ | Old Pusher stats table — Reverb replaced it |

### Models to Delete (after dropping tables)

| File | Table | Notes |
|------|-------|-------|
| `app/GlobalLevel.php` | `global_levels` | Bare model, no relations |
| `app/Level.php` | `levels` | Bare model, no relations |
| `app/Suburb.php` | `suburbs` | Bare model, only `$fillable` |

---

## Phase A — Keep but Deprecate (Financial/Legal)

These tables are legacy but may need to be preserved for GDPR/financial compliance. Confirm before dropping.

| Table | Grep Evidence | Notes |
|-------|--------------|-------|
| `payments` | `Payment.php` model + `User::payments()` relation + `DeleteAccountController` reassigns on delete | Old Stripe payments — not deleted on account removal (financial records). Laravel Cashier replaced active payment processing. |
| `stripe` | `Stripe.php` model + `User::stripe()` relation + CSRF exemption | Old standalone Stripe integration. Cashier's `subscriptions` table replaced it. |
| `plans` | No model file. Referenced only in old `Stripe.php` model `$fillable` | Old subscription plan definitions |
| `subscriptions` | Managed by Laravel Cashier package | External package table — do not drop |
| `subscription_items` | Managed by Laravel Cashier package | External package table — do not drop |

---

## Phase B — Post-Migration

| Table | Notes |
|-------|-------|
| `location_merges` | Audit trail for LocationCleanupCommand. Drop after migration complete. |

---

