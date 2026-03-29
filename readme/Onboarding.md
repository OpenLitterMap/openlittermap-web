# Onboarding System

Guided 3-step onboarding flow for new users: **Welcome → Upload → Tag → Celebration**. New users are funneled through this flow after signup or login. Existing users are backfilled as "completed" and unaffected.

## User Flow

```
Register / Login
    ↓
onboarding_completed_at == null?
    ├─ NO  → normal app (/upload or intended route)
    └─ YES → /onboarding (Welcome)
                 ↓  "Get started"
             /onboarding/upload (single-file upload)
                 ↓  auto-redirect after upload
             /onboarding/tag (tag with quick-select chips)
                 ↓  on first tag submit
             /onboarding/complete?photo={id} (Celebration)
                 ↓
             CTAs: View on Map | Upload More | Profile
```

Users can **skip** from the Welcome page (`POST /api/user/onboarding/skip`), which sets `onboarding_completed_at` and redirects to `/upload`.

## Completion Triggers

`onboarding_completed_at` gets set (only if currently null) in two places:

1. **First tag submission** — `PhotoTagsController::store()` checks and sets `now()`
2. **Skip endpoint** — `POST /api/user/onboarding/skip` (inline route in `api.php`, inside `auth:sanctum` group)

Both are idempotent — they won't overwrite an existing timestamp.

## Database

### Migration: `2026_03_28_182859_add_onboarding_completed_at_to_users`

- Adds `users.onboarding_completed_at` — nullable timestamp, after `can_bbox`
- **Backfill:** All existing users get `onboarding_completed_at = created_at` so they're never trapped in onboarding

### Model: `User.php`

- `$fillable`: `'onboarding_completed_at'`
- `$casts`: `'onboarding_completed_at' => 'datetime'`

## Backend

### API Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/user/onboarding/skip` | sanctum | Skip onboarding (sets `onboarding_completed_at`) |

The skip endpoint is an inline closure in `routes/api.php` inside the `auth:sanctum` middleware group.

### Controllers

**`PhotoTagsController::store()`** — After creating tags, checks if `$user->onboarding_completed_at === null` and sets it to `now()`. This is the "natural" completion path.

**`LoginController`** — Uses `ResolvesUserProfile::buildFullProfileData()` instead of returning raw `$user`. This ensures the login response includes `onboarding_completed_at` so the frontend can route immediately.

**`ProfileController` + `ResolvesUserProfile` trait** — Both include `onboarding_completed_at` (ISO 8601 string or null) in the user settings response, so `REFRESH_USER` picks it up.

## Frontend

### Routes

| Path | Component | Props | Middleware | Purpose |
|------|-----------|-------|------------|---------|
| `/onboarding` | OnboardingWelcome | — | auth, onboardingNotCompleted | Welcome + GPS instructions |
| `/onboarding/upload` | Upload | `onboarding: true` | auth, onboardingNotCompleted | Multi-file upload |
| `/onboarding/tag` | AddTags | `onboarding: true` | auth, onboardingNotCompleted | Tag with chips + reassurance |
| `/onboarding/complete` | Celebration | — | auth | Geolink + XP + CTAs |

### Route Middleware

**`onboarding.js`** — Applied to main app routes (upload, tag, uploads, teams, profile). Redirects to `/onboarding` if `auth && !onboardingCompleted`. **Not applied to admin routes** — admins should never be locked out of admin tools.

**`onboardingNotCompleted.js`** — Applied to onboarding routes (except `/onboarding/complete`). Redirects completed users to `/upload` so they can't re-enter the flow.

**Guarded routes:** `/tag`, `/upload`, `/uploads`, `/teams`, `/teams/create`, `/profile`

**Not guarded:** `/admin/redis`, `/admin/queue`, `/admin/users` (auth only)

### Pinia Store

**`stores/user/index.js`** — Getter: `onboardingCompleted: (state) => !!state.user?.onboarding_completed_at`

**`stores/user/requests.js`** — Login success handler checks `onboardingCompleted`. New users → `/onboarding`, returning users → intended route or `/upload`.

**`views/Account/CreateAccount.vue`** — Same redirect logic after signup.

### Components

#### `views/Onboarding/OnboardingWelcome.vue`
- Heading: "Map litter in 3 steps"
- 3-step summary bullets (photo, tag, map)
- `StepIndicator` at step 1
- `GpsInstructions` component (tabbed iPhone/Android GPS setup)
- "Get started" → `/onboarding/upload`
- "Skip for now" → `POST /api/user/onboarding/skip`, optimistic local update, then `/upload`

#### `views/Onboarding/Celebration.vue`
- `StepIndicator` at step 4 (all complete)
- Success icon + "You did it!" heading
- XP badge (from `userStore.user.xp`)
- **Photo geolink** — reads `route.query.photo`, `route.query.lat`, and `route.query.lon`. Shows URL in `<code>` block with copy-to-clipboard button
- Sharing message: "Copy this link and share it with anyone. OpenLitterMap is a real-time, open-source global reporting tool."
- Tip: "Take a photo of bags of litter picked up and share the link with your local council!"
- "See your upload on the global map" → `/global?lat={lat}&lon={lon}&zoom=17.89&load=true&open=true&photo={id}` (or generic `/global` if no coords)
- "Upload more photos" → `/upload`
- "Go to your profile" → `/profile`
- Calls `REFRESH_USER()` on mount to get fresh XP

#### `components/onboarding/StepIndicator.vue`
- 3-step progress bar: "Upload a photo" → "Add tags" → "See your data"
- Props: `currentStep` (1–4, where 4 means all complete)
- Numbered circles with checkmarks for completed steps, emerald ring for active
- Responsive — hides labels on mobile, shows on `sm:`

#### `components/onboarding/GpsInstructions.vue`
- Tabbed card with iPhone and Android GPS setup instructions, fully i18n'd
- Props: `compact` (Boolean, default false) — hides heading when true (used in Upload error state)
- **iPhone:** Settings → Privacy & Security → Location Services → Camera → While Using the App. Plus recommended: Settings → Camera → Formats → Most Compatible (JPG instead of HEIC)
- **Android:** 1) Turn on system Location (quick settings or Settings → Location), 2) Camera app → Settings → Location tags/GPS tags/Save location → ON
- **Android upload tip (web only):** Use file picker's Browse view (⋮ → Browse) instead of Photos/Albums — the default Android picker strips GPS metadata
- Reassurance: "Once enabled, every photo you take will include GPS automatically. You only need to do this once."
- All strings use `$t()` with `onboarding_gps_*` keys in `resources/js/langs/en.json`
- Used on: OnboardingWelcome (full), Upload.vue GPS error state (compact)

#### `components/onboarding/OnboardingChips.vue`
- 6 quick-select buttons for common litter items: Cigarette butt, Bottle, Can, Wrapper, Cup, Bag
- Resolves CLO IDs from the tags store at runtime
- Emits `add-tag` with the resolved tag object
- Shown only during onboarding tagging step

### Modified Pages (onboarding mode)

**`Upload.vue`** when `props.onboarding === true`:
- Shows `StepIndicator` at step 2
- Multiple uploads allowed (user only needs to tag the first one to complete onboarding)
- Auto-redirects to `/onboarding/tag` after successful upload
- "Tag your photos" link points to `/onboarding/tag`
- Preloads tags store (fire-and-forget) so tag page loads faster
- Preloads photos cache before navigating to tag page

**`Upload.vue`** GPS error handling (all modes):
- Detects `no_gps` and `invalid_coordinates` error codes from 422 response
- Shows `GpsInstructions compact` below the upload area with "This photo doesn't have location data" message
- Resets on "Try again"

**`AddTags.vue`** when `props.onboarding === true`:
- Shows `StepIndicator` at step 3
- Shows `OnboardingChips` (6 quick-select tag buttons)
- Shows reassurance text: "One tag is enough to get started. You can always edit later."
- On submit: optimistically sets `onboarding_completed_at` on local user object, captures `photo`, `lat`, `lon` from photo, redirects to `/onboarding/complete?photo={id}&lat={lat}&lon={lon}`

**`Nav.vue`** — Hides nav links during onboarding:
- Upload, Add Tags hidden unless `onboardingCompleted`
- Profile, Teams, Settings hidden unless `onboardingCompleted`
- Admin links always visible (not gated by onboarding)
- Public links (Map, About, Leaderboard, Locations) + Logout always visible

## Photo Geolink

The geolink uses the photo's GPS coordinates and ID to centre the map and load the specific photo:

```
/global?lat={lat}&lon={lon}&zoom=17.89&load=true&open=true&photo={id}
```

- `lat`/`lon` — the photo's GPS coordinates (passed from AddTags via query params)
- `zoom=17.89` — centres tightly on the location
- `load=true` — tells the map to load data for this area
- `open=true` — opens the stats drawer automatically
- `photo={id}` — loads and positions the specific photo on the map

Example: `https://openlittermap.com/global?lat=51.886865&lon=-8.487191&zoom=17.89&load=true&open=true&photo=12345`

The celebration page constructs this URL for both the "See your upload on the global map" CTA and the copy-to-clipboard button.

**Note:** Unverified photos (new non-trusted users) will show "Awaiting verification" in the map popup instead of the photo image. The photo appears on the map once admin-verified (`verified >= 2`).

## Design

All onboarding pages use the established dark glass theme:
- Background: `bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900`
- Cards: `bg-white/5 border border-white/10 backdrop-blur-xl`
- Accent: emerald (`bg-emerald-500`, `text-emerald-400`)
- Text: `text-white` / `text-white/60` / `text-white/40` / `text-white/30`

## Tests

**`tests/Feature/OnboardingTest.php`** — 10 tests, 27 assertions:

| Test | What it verifies |
|------|-----------------|
| `test_new_user_has_null_onboarding_completed_at` | Factory creates user with null |
| `test_first_tag_sets_onboarding_completed_at` | POST /api/v3/tags sets the timestamp |
| `test_second_tag_does_not_change_onboarding_completed_at` | Idempotent — won't overwrite |
| `test_skip_onboarding_endpoint` | POST /api/user/onboarding/skip works |
| `test_skip_onboarding_is_idempotent` | Skip won't overwrite existing timestamp |
| `test_skip_onboarding_requires_auth` | Returns 401 without auth |
| `test_profile_refresh_includes_onboarding_completed_at` | Profile includes field (non-null) |
| `test_profile_refresh_returns_null_onboarding_for_new_user` | Profile includes field (null) |
| `test_login_returns_onboarding_completed_at` | Login response includes the field |
| `test_login_does_not_leak_sensitive_fields` | Login response excludes stripe_id, token, etc. |

## File Map

### New files
- `resources/js/views/Onboarding/OnboardingWelcome.vue`
- `resources/js/views/Onboarding/Celebration.vue`
- `resources/js/components/onboarding/GpsInstructions.vue`
- `resources/js/components/onboarding/OnboardingChips.vue`
- `resources/js/components/onboarding/StepIndicator.vue`
- `resources/js/router/middleware/onboarding.js`
- `resources/js/router/middleware/onboardingNotCompleted.js`
- `database/migrations/2026_03_28_182859_add_onboarding_completed_at_to_users.php`
- `tests/Feature/OnboardingTest.php`

### Modified files
- `app/Http/Controllers/API/Tags/PhotoTagsController.php` — completion trigger
- `app/Http/Controllers/Auth/LoginController.php` — buildFullProfileData
- `app/Http/Controllers/User/ProfileController.php` — onboarding_completed_at in response
- `app/Traits/ResolvesUserProfile.php` — onboarding_completed_at in response
- `app/Models/Users/User.php` — fillable + casts
- `routes/api.php` — skip endpoint
- `resources/js/router/index.js` — onboarding routes + middleware on existing routes
- `resources/js/stores/user/index.js` — onboardingCompleted getter
- `resources/js/stores/user/requests.js` — login redirect logic
- `resources/js/views/Account/CreateAccount.vue` — signup redirect logic
- `resources/js/views/General/Tagging/v2/AddTags.vue` — onboarding mode
- `resources/js/views/Upload/Upload.vue` — onboarding mode + GPS error handling
- `resources/js/components/Nav.vue` — hide links during onboarding
- `package.json` — version bump 5.3.0 → 5.4.0
