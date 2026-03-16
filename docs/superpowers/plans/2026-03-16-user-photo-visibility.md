# User Photo Visibility Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a user-level `public_photos` default setting and per-photo visibility toggle so users control whether photos appear on the global map while still earning XP.

**Architecture:** New `public_photos` boolean on `users` table sets the default for new uploads. `PATCH /api/v3/photos/{id}/visibility` toggles individual photos. PhotoObserver marks dirty tiles on `is_public` change. School pipeline unchanged — school team override takes precedence.

**Tech Stack:** PHP 8.2, Laravel 11, Vue 3 (Composition API), Pinia, Tailwind CSS 3.4

**Spec:** `docs/superpowers/specs/2026-03-16-user-photo-visibility-design.md`

**Pre-requisites already done (this session):**
- Metrics gate in `UploadPhotoController:170` already changed from `is_public` check to school team check
- `ProfileController` location counts already have `is_public` filter for public profile
- Bug fixes committed to branch

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/XXXX_add_public_photos_to_users.php` | Add `public_photos` boolean column |
| Modify | `app/Http/Requests/UploadPhotoRequest.php:20-35` | Add `is_public` validation rule |
| Modify | `app/Http/Controllers/Uploads/UploadPhotoController.php:103-120` | Apply user default to photo creation |
| Modify | `app/Http/Controllers/ApiSettingsController.php:15-25` | Add `public_photos` to whitelist |
| Modify | `app/Http/Controllers/User/ProfileController.php:271-290` | Add `public_photos` to index response |
| Modify | `app/Http/Controllers/User/ProfileController.php:78-80` | Remove `is_public` filter from own geojson |
| Modify | `app/Http/Controllers/User/ProfileController.php:253-256` | Remove `is_public` filter from own location counts |
| Modify | `app/Http/Controllers/User/Photos/UsersUploadsController.php` | Add `toggleVisibility()` method |
| Modify | `app/Observers/PhotoObserver.php:80` | Add `is_public` to dirty tile tracking |
| Modify | `routes/api.php` | Add PATCH visibility route |
| Modify | `resources/js/views/Profile/components/ProfileSettings.vue:69-101` | Add "Photos Public" toggle |
| Modify | `resources/js/views/User/Uploads/Uploads.vue:36-77` | Add per-photo eye icon toggle |
| Modify | `tests/Feature/Api/Photos/UploadPhotoTest.php` | Upload default tests |
| Modify | `tests/Feature/ProfileSettingsTest.php` | Settings + profile tests |
| Create | `tests/Feature/User/PhotoVisibilityTest.php` | Per-photo toggle tests |

---

## Chunk 1: Backend — Migration, Settings, Upload Default

### Task 1: Migration

**Files:**
- Create: `database/migrations/2026_03_16_000000_add_public_photos_to_users.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration add_public_photos_to_users --table=users --no-interaction
```

- [ ] **Step 2: Write migration**

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('public_photos')->default(true)->after('public_profile');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('public_photos');
    });
}
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*add_public_photos*
git commit -m "migration: add public_photos boolean to users table"
```

---

### Task 2: Settings API — Add `public_photos` to whitelist

**Files:**
- Modify: `app/Http/Controllers/ApiSettingsController.php:15-25`
- Test: `tests/Feature/ProfileSettingsTest.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/ProfileSettingsTest.php`:

```php
public function test_public_photos_can_be_set_to_false(): void
{
    $user = User::factory()->create(['public_photos' => true]);

    $response = $this->actingAs($user)->postJson('/api/settings/update', [
        'key' => 'public_photos',
        'value' => false,
    ]);

    $response->assertOk();
    $this->assertFalse((bool) $user->fresh()->public_photos);
}

public function test_public_photos_can_be_set_to_true(): void
{
    $user = User::factory()->create(['public_photos' => false]);

    $response = $this->actingAs($user)->postJson('/api/settings/update', [
        'key' => 'public_photos',
        'value' => true,
    ]);

    $response->assertOk();
    $this->assertTrue((bool) $user->fresh()->public_photos);
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php artisan test --compact --filter="test_public_photos_can_be_set"
```

Expected: FAIL — `public_photos` not in allowed settings whitelist.

- [ ] **Step 3: Add to whitelist**

In `app/Http/Controllers/ApiSettingsController.php`, add to `ALLOWED_SETTINGS`:

```php
'public_photos' => 'boolean',
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
php artisan test --compact --filter="test_public_photos_can_be_set"
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ApiSettingsController.php tests/Feature/ProfileSettingsTest.php
git commit -m "feat: add public_photos to settings whitelist"
```

---

### Task 3: Profile API — Return `public_photos` in index response

**Files:**
- Modify: `app/Http/Controllers/User/ProfileController.php:271-290`
- Test: `tests/Feature/ProfileSettingsTest.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/ProfileSettingsTest.php`:

```php
public function test_profile_index_returns_public_photos_setting(): void
{
    $user = User::factory()->create(['public_photos' => false]);

    $response = $this->actingAs($user)->getJson('/api/user/profile/index');

    $response->assertOk();
    $response->assertJsonPath('user.public_photos', false);
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --compact --filter="test_profile_index_returns_public_photos_setting"
```

- [ ] **Step 3: Add to response**

In `ProfileController::index()`, inside the `'user'` array (around line 286), add:

```php
'public_photos' => (bool) $user->public_photos,
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php artisan test --compact --filter="test_profile_index_returns_public_photos_setting"
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/User/ProfileController.php tests/Feature/ProfileSettingsTest.php
git commit -m "feat: return public_photos in profile index response"
```

---

### Task 4: Upload — Apply user `public_photos` default

**Files:**
- Modify: `app/Http/Requests/UploadPhotoRequest.php:20-35`
- Modify: `app/Http/Controllers/Uploads/UploadPhotoController.php:103-120`
- Test: `tests/Feature/Api/Photos/UploadPhotoTest.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Feature/Api/Photos/UploadPhotoTest.php`:

```php
public function test_upload_uses_user_public_photos_default()
{
    Storage::fake('s3');
    Storage::fake('bbox');

    $user = User::factory()->create([
        'picked_up' => true,
        'public_photos' => false,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v3/upload', [
        'photo' => new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'photo.jpg', 'image/jpeg', null, true
        ),
    ]);

    $response->assertOk();
    $photo = Photo::find($response->json('photo_id'));
    $this->assertFalse((bool) $photo->is_public);
}

public function test_upload_request_is_public_overrides_user_default()
{
    Storage::fake('s3');
    Storage::fake('bbox');

    $user = User::factory()->create([
        'picked_up' => true,
        'public_photos' => false,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v3/upload', [
        'photo' => new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'photo.jpg', 'image/jpeg', null, true
        ),
        'is_public' => true,
    ]);

    $response->assertOk();
    $photo = Photo::find($response->json('photo_id'));
    $this->assertTrue((bool) $photo->is_public);
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php artisan test --compact --filter="test_upload_uses_user_public_photos_default|test_upload_request_is_public_overrides"
```

- [ ] **Step 3: Add `is_public` to UploadPhotoRequest validation**

In `app/Http/Requests/UploadPhotoRequest.php`, add to the `rules()` array:

```php
'is_public' => ['sometimes', 'boolean'],
```

- [ ] **Step 4: Set `is_public` on Photo::create in UploadPhotoController**

In `app/Http/Controllers/Uploads/UploadPhotoController.php`, before the `Photo::create()` call (around line 103), add:

```php
// Determine photo visibility: request value → user default → true
// PhotoObserver::creating() overrides to false for school teams
if ($request->has('is_public')) {
    $isPublic = $request->boolean('is_public');
} else {
    $isPublic = $user->public_photos ?? true;
}
```

Then add `'is_public' => $isPublic,` to the `Photo::create()` array.

- [ ] **Step 5: Run tests — expect PASS**

```bash
php artisan test --compact --filter="test_upload_uses_user_public_photos_default|test_upload_request_is_public_overrides"
```

- [ ] **Step 6: Write test for school override**

Add to `tests/Feature/Api/Photos/UploadPhotoTest.php`:

```php
public function test_school_team_overrides_user_public_photos_true()
{
    Storage::fake('s3');
    Storage::fake('bbox');

    $schoolType = TeamType::firstOrCreate(
        ['team' => 'school'],
        ['team' => 'school']
    );
    $team = Team::factory()->create([
        'type_id' => $schoolType->id,
        'type_name' => 'school',
    ]);

    // User wants public photos, but school team forces private
    $user = User::factory()->create([
        'active_team' => $team->id,
        'picked_up' => true,
        'public_photos' => true,
    ]);
    $team->users()->attach($user->id);

    $response = $this->actingAs($user)->postJson('/api/v3/upload', [
        'photo' => new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'photo.jpg', 'image/jpeg', null, true
        ),
    ]);

    $response->assertOk();
    $photo = Photo::find($response->json('photo_id'));
    // School override wins — photo is private
    $this->assertFalse((bool) $photo->is_public);
    // XP deferred for school photos
    $this->assertEquals(0, $response->json('xp_awarded'));
}

public function test_user_leaving_school_team_uses_own_default()
{
    Storage::fake('s3');
    Storage::fake('bbox');

    // User is NOT on a school team (active_team = null)
    $user = User::factory()->create([
        'active_team' => null,
        'picked_up' => true,
        'public_photos' => false,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v3/upload', [
        'photo' => new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'photo.jpg', 'image/jpeg', null, true
        ),
    ]);

    $response->assertOk();
    $photo = Photo::find($response->json('photo_id'));
    // No school team, user default applies
    $this->assertFalse((bool) $photo->is_public);
    // Private-by-choice still gets XP
    $this->assertEquals(XpScore::Upload->xp(), $response->json('xp_awarded'));
}
```

- [ ] **Step 7: Run full upload test file to verify no regressions**

```bash
php artisan test --compact tests/Feature/Api/Photos/UploadPhotoTest.php
```

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/UploadPhotoRequest.php app/Http/Controllers/Uploads/UploadPhotoController.php tests/Feature/Api/Photos/UploadPhotoTest.php
git commit -m "feat: apply user public_photos default to uploads"
```

---

### Task 5: ProfileController — Own-user queries include private photos

**Files:**
- Modify: `app/Http/Controllers/User/ProfileController.php:78-80` (geojson)
- Modify: `app/Http/Controllers/User/ProfileController.php:253-256` (index location counts)

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/ProfileSettingsTest.php`:

```php
public function test_own_geojson_includes_private_photos(): void
{
    $user = User::factory()->create();
    $country = Country::factory()->create();

    // Public photo
    Photo::factory()->create([
        'user_id' => $user->id,
        'country_id' => $country->id,
        'is_public' => true,
        'verified' => \App\Enums\VerificationStatus::ADMIN_APPROVED->value,
        'datetime' => now(),
    ]);

    // Private photo
    Photo::factory()->create([
        'user_id' => $user->id,
        'country_id' => $country->id,
        'is_public' => false,
        'verified' => \App\Enums\VerificationStatus::ADMIN_APPROVED->value,
        'datetime' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/user/profile/geojson?' . http_build_query([
        'period' => 'created_at',
        'start' => now()->subDay()->toDateString(),
        'end' => now()->addDay()->toDateString(),
    ]));

    $response->assertOk();
    $this->assertCount(2, $response->json('geojson.features'));
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --compact --filter="test_own_geojson_includes_private_photos"
```

Expected: FAIL — only 1 feature returned (private excluded).

- [ ] **Step 3: Remove `is_public` filter from geojson**

In `ProfileController::geojson()` (line 80), remove:

```php
->where('is_public', true)
```

- [ ] **Step 4: Remove `is_public` filter from index location counts**

In `ProfileController::index()` (line 254), remove:

```php
->where('is_public', true)
```

(The public `show()` method at line 160 keeps its `is_public` filter.)

- [ ] **Step 5: Write test for own-user index location counts**

Add to `tests/Feature/ProfileSettingsTest.php`:

```php
public function test_own_profile_index_location_counts_include_private_photos(): void
{
    $country1 = Country::factory()->create();
    $country2 = Country::factory()->create();
    $user = User::factory()->create();

    Photo::factory()->create([
        'user_id' => $user->id,
        'country_id' => $country1->id,
        'is_public' => true,
    ]);

    Photo::factory()->create([
        'user_id' => $user->id,
        'country_id' => $country2->id,
        'is_public' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/api/user/profile/index');

    $response->assertOk();
    // Authenticated user sees all their photos in location counts
    $this->assertEquals(2, $response->json('locations.countries'));
}
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
php artisan test --compact --filter="test_own_geojson_includes_private_photos|test_own_profile_index_location_counts_include_private"
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/User/ProfileController.php tests/Feature/ProfileSettingsTest.php
git commit -m "feat: own profile queries include private photos"
```

---

## Chunk 2: Backend — Per-Photo Toggle + PhotoObserver

### Task 6: PhotoObserver — Add `is_public` to dirty tile tracking

**Files:**
- Modify: `app/Observers/PhotoObserver.php:80`

- [ ] **Step 1: Update wasChanged check**

In `PhotoObserver::saved()` (line 80), change:

```php
if ($photo->wasChanged(['lat', 'lon', 'verified', 'tile_key'])) {
```

To:

```php
if ($photo->wasChanged(['lat', 'lon', 'verified', 'tile_key', 'is_public'])) {
```

- [ ] **Step 2: Run existing observer tests to verify no regression**

```bash
php artisan test --compact --filter="PhotoObserver|clustering|dirty"
```

- [ ] **Step 3: Commit**

```bash
git add app/Observers/PhotoObserver.php
git commit -m "fix: mark tiles dirty when is_public changes"
```

---

### Task 7: Per-Photo Visibility Endpoint

**Files:**
- Modify: `app/Http/Controllers/User/Photos/UsersUploadsController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/User/PhotoVisibilityTest.php`

- [ ] **Step 1: Create test file**

```bash
php artisan make:test User/PhotoVisibilityTest --no-interaction
```

- [ ] **Step 2: Write tests**

```php
<?php

namespace Tests\Feature\User;

use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Tests\TestCase;

class PhotoVisibilityTest extends TestCase
{
    public function test_user_can_toggle_own_photo_to_private(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => false,
            ]);

        $response->assertOk();
        $this->assertFalse((bool) $photo->fresh()->is_public);
    }

    public function test_user_can_toggle_own_photo_to_public(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => true,
            ]);

        $response->assertOk();
        $this->assertTrue((bool) $photo->fresh()->is_public);
    }

    public function test_user_cannot_toggle_another_users_photo(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $owner->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($other)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => false,
            ]);

        $response->assertForbidden();
        $this->assertTrue((bool) $photo->fresh()->is_public);
    }

    public function test_school_team_photo_toggle_rejected(): void
    {
        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );
        $team = Team::factory()->create([
            'type_id' => $schoolType->id,
            'type_name' => 'school',
        ]);

        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", [
                'is_public' => true,
            ]);

        $response->assertForbidden();
        $this->assertFalse((bool) $photo->fresh()->is_public);
    }

    public function test_unauthenticated_toggle_rejected(): void
    {
        $photo = Photo::factory()->create(['is_public' => true]);

        $response = $this->patchJson("/api/v3/photos/{$photo->id}/visibility", [
            'is_public' => false,
        ]);

        $response->assertUnauthorized();
    }

    public function test_toggle_requires_is_public_field(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v3/photos/{$photo->id}/visibility", []);

        $response->assertUnprocessable();
    }

    /**
     * Spec test case #8: Private photo gets tagged — TagsVerifiedByAdmin fires,
     * metrics processed, user appears on leaderboard, photo stays off map.
     */
    public function test_private_photo_tagging_still_fires_metrics_event(): void
    {
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\TagsVerifiedByAdmin::class,
        ]);

        $user = User::factory()->create();
        $country = \App\Models\Location\Country::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'is_public' => false,
            'verified' => 0,
        ]);

        // Seed tags so AddTagsToPhotoAction can run
        $this->seed(\Database\Seeders\GenerateTagsSeeder::class);

        $category = \App\Models\Litter\Tags\Category::where('key', 'smoking')->first();
        $object = \App\Models\Litter\Tags\LitterObject::where('key', 'butts')->first();
        $clo = \App\Models\Litter\Tags\CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        app(\App\Actions\Tags\AddTagsToPhotoAction::class)->run($user->id, $photo->id, [
            ['category_litter_object_id' => $clo->id, 'quantity' => 1],
        ]);

        // TagsVerifiedByAdmin should fire even for private photos
        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\TagsVerifiedByAdmin::class,
            function ($event) use ($photo) {
                return $event->photo_id === $photo->id;
            }
        );

        // Photo stays private
        $this->assertFalse((bool) $photo->fresh()->is_public);
    }
}
```

- [ ] **Step 3: Run tests — expect FAIL**

```bash
php artisan test --compact tests/Feature/User/PhotoVisibilityTest.php
```

Expected: FAIL — route not defined.

- [ ] **Step 4: Add route**

In `routes/api.php`, inside the `v3` group (line 76), add:

```php
Route::patch('/photos/{photo}/visibility', [\App\Http\Controllers\User\Photos\UsersUploadsController::class, 'toggleVisibility']);
```

- [ ] **Step 5: Implement controller method**

In `app/Http/Controllers/User/Photos/UsersUploadsController.php`, add:

```php
/**
 * Toggle photo visibility (public/private).
 * School team photos cannot be toggled — managed by team leader.
 */
public function toggleVisibility(Request $request, Photo $photo): JsonResponse
{
    $request->validate([
        'is_public' => ['required', 'boolean'],
    ]);

    if ((int) $photo->user_id !== (int) Auth::id()) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // School team photos are managed by the team leader
    if ($photo->team_id) {
        $team = \App\Models\Teams\Team::find($photo->team_id);
        if ($team && $team->isSchool()) {
            return response()->json([
                'message' => 'School team photos are managed by the team leader.',
            ], 403);
        }
    }

    $photo->is_public = $request->boolean('is_public');
    $photo->save();

    return response()->json([
        'success' => true,
        'is_public' => (bool) $photo->is_public,
    ]);
}
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
php artisan test --compact tests/Feature/User/PhotoVisibilityTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/User/Photos/UsersUploadsController.php routes/api.php tests/Feature/User/PhotoVisibilityTest.php
git commit -m "feat: add per-photo visibility toggle endpoint"
```

---

## Chunk 3: Frontend

### Task 8: ProfileSettings — Add "Photos Public" toggle

**Files:**
- Modify: `resources/js/views/Profile/components/ProfileSettings.vue:69-101`

- [ ] **Step 1: Add toggle in Privacy section**

In `ProfileSettings.vue`, after the "Prevent others tagging" toggle (line 99) and before the closing `</div>` of the Privacy section, add:

```vue
<SettingsToggle
    :label="$t('Photos public by default')"
    :description="$t('New photos will appear on the public map')"
    :value="profileStore.user.public_photos"
    @toggle="saveSetting('public_photos', !profileStore.user.public_photos)"
/>
```

- [ ] **Step 2: Verify in browser**

```bash
npm run dev
```

Navigate to Profile → Settings → Privacy. The new toggle should appear below "Prevent others tagging my photos". Toggle it and verify it persists (page refresh shows same state).

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Profile/components/ProfileSettings.vue
git commit -m "feat: add photos public by default toggle to settings"
```

---

### Task 9: Uploads Page — Per-photo visibility icon

**Files:**
- Modify: `resources/js/views/User/Uploads/Uploads.vue:36-77`

- [ ] **Step 1: Add eye icon button**

In the photo card's action buttons row (after the delete button around line 73), add:

```vue
<button
    class="transition-colors p-1"
    :class="photo.is_public
        ? 'text-emerald-500 hover:text-emerald-700'
        : 'text-gray-400 hover:text-gray-600'"
    :disabled="photo.school_team || togglingVisibility[photo.id]"
    :title="photo.school_team
        ? $t('Managed by team leader')
        : (photo.is_public ? $t('Photo is public') : $t('Photo is private'))"
    @click.stop="toggleVisibility(photo)"
>
    <!-- Eye open (public) -->
    <svg v-if="photo.is_public" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
    <!-- Eye closed (private) -->
    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
    </svg>
</button>
```

- [ ] **Step 2: Add script logic**

In the `<script setup>` section, add:

```js
const togglingVisibility = ref({});

const toggleVisibility = async (photo) => {
    if (togglingVisibility.value[photo.id]) return;

    togglingVisibility.value[photo.id] = true;
    try {
        const { data } = await axios.patch(`/api/v3/photos/${photo.id}/visibility`, {
            is_public: !photo.is_public,
        });
        if (data.success) {
            photo.is_public = data.is_public;
        }
    } catch (err) {
        console.error('Failed to toggle visibility', err);
    } finally {
        togglingVisibility.value[photo.id] = false;
    }
};
```

- [ ] **Step 3: Verify in browser**

Navigate to `/uploads`. Each photo card should have an eye icon. Click it to toggle. Verify:
- Public photos show open eye (emerald)
- Private photos show closed eye (gray)
- School team photos have disabled button with tooltip

- [ ] **Step 4: Build for production**

```bash
npm run build
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/views/User/Uploads/Uploads.vue
git commit -m "feat: add per-photo visibility toggle to uploads page"
```

---

## Chunk 4: Final Verification

### Task 10: Full Test Suite

- [ ] **Step 1: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass (1 skipped S3/MinIO is OK).

- [ ] **Step 2: Verify specific test counts**

New tests added by this plan:
- `test_public_photos_can_be_set_to_false`
- `test_public_photos_can_be_set_to_true`
- `test_profile_index_returns_public_photos_setting`
- `test_upload_uses_user_public_photos_default`
- `test_upload_request_is_public_overrides_user_default`
- `test_school_team_overrides_user_public_photos_true`
- `test_user_leaving_school_team_uses_own_default`
- `test_own_geojson_includes_private_photos`
- `test_own_profile_index_location_counts_include_private_photos`
- 7 PhotoVisibilityTest tests (including private-photo-tagging metrics test)

Total: 16 new tests.

- [ ] **Step 3: Final commit with all changes**

If any uncommitted files remain:

```bash
git add -A
git commit -m "feat: user photo visibility — complete implementation"
```
