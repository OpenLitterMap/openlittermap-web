---
name: photo-pipeline
description: Photo upload, tagging, verification status, summary generation, XP calculation, AddTagsToPhotoAction, UploadPhotoController, and the VerificationStatus enum.
---

# Photo Pipeline

Photos flow through three phases: Upload (observation only) -> Tag (summary + XP) -> Verify (metrics). Each phase is independent and idempotent.

## Key Files

- `app/Http/Controllers/Uploads/UploadPhotoController.php` — Web upload entry point
- `app/Http/Requests/UploadPhotoRequest.php` — Web upload validation (EXIF datetime, GPS, duplicates)
- `app/Http/Controllers/API/Tags/PhotoTagsController.php` — V5 tagging endpoint (`POST /api/v3/tags` add, `PUT /api/v3/tags` replace)
- `app/Actions/Tags/AddTagsToPhotoAction.php` — Core tagging logic (v5)
- `app/Actions/Photos/MakeImageAction.php` — Image processing + EXIF extraction
- `app/Actions/Photos/UploadPhotoAction.php` — S3 storage (requires non-null Carbon datetime)
- `app/Services/Tags/GeneratePhotoSummaryService.php` — Builds summary JSON + calculates XP
- `app/Services/Tags/XpCalculator.php` — XP scoring rules
- `app/Enums/VerificationStatus.php` — Photo verification state machine
- `app/Enums/XpScore.php` — XP values per tag type
- `app/Http/Requests/Api/PhotoTagsRequest.php` — V5 tag request validation (POST — blocks already-verified photos)
- `app/Http/Requests/Api/ReplacePhotoTagsRequest.php` — V5 replace tag request validation (PUT — ownership only, no verification gate)
- `app/Observers/PhotoObserver.php` — Sets `is_public = false` for school team photos
- `app/Helpers/helpers.php` — `getDateTimeForPhoto()`, `getCoordinatesFromPhoto()`, `dmsToDec()`
- `tests/Feature/UploadValidationTest.php` — 11 tests (EXIF datetime, GPS DMS conversion, edge cases)
- `tests/Feature/Tags/ReplacePhotoTagsTest.php` — 5 tests (replace tags, ownership, auth, extra tags cleanup)

## Invariants

1. **Upload creates observation only.** No tags, no XP, no summary, no metrics. Just the photo record with location FKs.
2. **EXIF datetime is required for web uploads.** `UploadPhotoRequest` rejects images without EXIF datetime. Controller has `?? Carbon::now()` safety fallback. `UploadPhotoAction::run()` type-hints `Carbon $datetime` — null will crash.
3. **GPS DMS conversion guards against division by zero.** `dmsToDec()` validates all 6 denominator values before dividing. Returns `null` on malformed data.
4. **0,0 coordinates are accepted.** Photos at latitude 0, longitude 0 are valid. Future: manual coordinate reassignment.
5. **Summary generation is unconditional.** `GeneratePhotoSummaryService::run()` MUST run regardless of trust level. School photos need a summary at tag time so it exists when the teacher approves later. Gating summary behind a trust check causes null summary at approval = zero metrics.
6. **XP calculation is unconditional.** Runs for all users, before verification.
7. **`TagsVerifiedByAdmin` only fires for trusted users.** School students' photos stop at `VERIFIED(1)` and wait for teacher approval.
8. **VerificationStatus is an enum cast.** `$photo->verified` returns the enum, not an int. Use `->value` for `>=`/`<` comparisons, `===` for equality checks. Never compare enum to raw int.

## VerificationStatus Enum

```php
enum VerificationStatus: int
{
    case UNVERIFIED = 0;     // Uploaded, no tags
    case VERIFIED = 1;       // Tagged (school students land here, awaiting teacher)
    case ADMIN_APPROVED = 2; // Verified by admin/trusted user OR teacher-approved
    case BBOX_APPLIED = 3;   // Bounding boxes drawn
    case BBOX_VERIFIED = 4;  // Bounding boxes verified
    case AI_READY = 5;       // Ready for OpenLitterAI training

    public function isPublicReady(): bool  // >= ADMIN_APPROVED
    public function isVerified(): bool     // >= VERIFIED
}
```

## Patterns

### Phase 1: Upload

`UploadPhotoRequest::after()` validates before controller runs:
1. EXIF must exist and be non-empty
2. DateTime must exist (DateTimeOriginal → DateTime → FileDateTime fallback)
3. GPS fields must exist and `dmsToDec()` must succeed (guards zero denominators)
4. Duplicate check (same user + same EXIF datetime)

`UploadPhotoController::__invoke()` flow:
1. `MakeImageAction::run($file)` — extract EXIF
2. `getDateTimeForPhoto($exif) ?? Carbon::now()` — EXIF datetime with safety fallback
3. `UploadPhotoAction::run()` x2 — S3 full image + bbox thumbnail
4. `getCoordinatesFromPhoto($exif)` → `ResolveLocationAction::run($lat, $lon)` — Country/State/City FKs
5. `Photo::create()` — observation record with FKs only
6. `event(new ImageUploaded(...))` — real-time broadcast

### Phase 2: Tagging

`PhotoTagsController::store()` -> `AddTagsToPhotoAction::run()`:

```php
public function run(int $userId, int $photoId, array $tags): array
{
    $photoTags = $this->addTagsToPhoto($userId, $photoId, $tags);
    // Creates PhotoTag + PhotoTagExtraTags (materials, brands, custom)
    // Handles 4 tag types: object, custom-only, brand-only, material-only

    $photo->generateSummary();
    // ALWAYS — generates summary JSON from PhotoTag records

    $photo->xp = $this->calculateXp($photoTags);
    // ALWAYS — uses XpScore enum multipliers (Upload=5, Object=1, Brand=3, Material=2, Custom=1)

    $this->updateVerification($userId, $photo);
    // Routes to trusted path or school-pending path
}
```

### Frontend tag types handled by AddTagsToPhotoAction

The web frontend sends 4 distinct tag types. `resolveTag()` handles each:

1. **Object tag** — `{ object: { id, key }, quantity, materials?, brands? }`. Category auto-resolved from `object->categories()->first()`.
2. **Custom-only** — `{ custom: true, key: "dirty-bench", quantity }`. Uses `$tag['key']` (not `$tag['custom']`).
3. **Brand-only** — `{ brand_only: true, brand: { id, key }, quantity }`. PhotoTag with null category/object.
4. **Material-only** — `{ material_only: true, material: { id, key }, quantity }`. Same as brand-only pattern.

### Verification routing

```php
protected function updateVerification(int $userId, Photo $photo): void
{
    $user = User::find($userId);

    if ($user->verification_required) {
        if ($photo->team_id) {
            $team = Team::find($photo->team_id);
            if ($team && $team->isSchool()) {
                $photo->verified = VerificationStatus::VERIFIED->value;
                // STOP here — no TagsVerifiedByAdmin, no metrics
            }
        }
    } else {
        // Trusted user — immediate approval
        $photo->verified = VerificationStatus::ADMIN_APPROVED->value;
        event(new TagsVerifiedByAdmin(
            $photo->id, $photo->user_id,
            $photo->country_id, $photo->state_id,
            $photo->city_id, $photo->team_id
        ));
    }
}
```

### XP calculation

```php
// XpScore enum values:
Upload    => 5   // Base for every photo
Object    => 1   // Per litter item (default)
Material  => 2   // Per material tag
Brand     => 3   // Per brand tag
CustomTag => 1   // Per custom tag
PickedUp  => 5   // Bonus if photo.remaining = false
Small     => 10  // Special objects: 'small'
Medium    => 25  // Special objects: 'medium'
Large     => 50  // Special objects: 'large'
BagsLitter => 10 // Special objects: 'bagsLitter'
```

### Phase 2b: Replace Tags (edit mode)

`PhotoTagsController::update()` handles `PUT /api/v3/tags` for replacing all tags on an already-tagged photo:

1. Delete all existing PhotoTags + PhotoTagExtraTags
2. Reset photo: `summary=null, xp=0, verified=0`
3. Call `AddTagsToPhotoAction::run()` — regenerates summary, XP, fires `TagsVerifiedByAdmin`
4. `MetricsService::processPhoto()` detects prior processing (has `processed_at`), calls `doUpdate()` which calculates deltas between old `processed_tags` and new summary, applies adjustments to all metrics

**Frontend edit mode:** `/tag?photo=<id>` loads a specific photo. If it has existing tags, `isEditMode=true` → uses PUT. If untagged, uses POST. `convertExistingTags()` transforms API `new_tags` format back to frontend format.

**Security:** `ReplacePhotoTagsRequest` checks `$photo->user_id === $this->user()->id`. `GET_SINGLE_PHOTO` calls `/api/v3/user/photos` which filters by authenticated user.

### result_string and total_litter (v4 compatibility — write-only)

`GeneratePhotoSummaryService::run()` still populates `result_string` from the summary keys for backward compatibility. Format: `category.object qty,category.object qty,...` (e.g., `smoking.butts 3,food.wrapper 2,`). However, **no public-facing endpoint reads `result_string` anymore** — all map endpoints (`GlobalMapController`, `DisplayTagsOnMapController`, `TeamsClusterController`, `PointsController`, `FilterPhotosByGeoHashTrait`) were updated to select and return `summary` instead. Both `result_string` and `total_litter` columns are now write-only and scheduled for eventual removal.

**`total_litter` → `total_tags`:** All active endpoints now read `total_tags` instead of `total_litter`. Fixed: `CommunityController`, `ContributorAggregator`, `TimeSeriesAggregator`, `ProfileController` (global litter fallback), `JoinTeamAction` (team pivot). Safe (location-level Redis, not photo column): `GlobalStatsController`, `WorldCupController`. Safe (correct fallback): `CreateCSVExport`. Console commands that read these columns (`CompileResultsString`, `ResetResultString`) have been deleted. Dead jobs deleted: `Api/AddTags`, `Photos/AddTagsToPhoto` (both wrote `total_litter` + `verification` float).

### Summary JSON structure

```json
{
  "tags": {
    "2": {
      "65": {
        "quantity": 5,
        "materials": {"16": 3, "15": 2},
        "brands": {"12": 3}
      }
    }
  },
  "totals": {
    "total_tags": 15, "total_objects": 5,
    "by_category": {"2": 10},
    "materials": 8, "brands": 3, "custom_tags": 0
  },
  "keys": {
    "categories": {"2": "smoking"},
    "objects": {"65": "wrapper"},
    "materials": {"16": "plastic"},
    "brands": {"12": "marlboro"}
  }
}
```

### Photo model hidden attribute

```php
protected $hidden = ['geom'];  // Binary spatial data — breaks JSON serialization
```

Always ensure `geom` stays in `$hidden`. If you need coordinates, use `lat`/`lon` columns.

## Common Mistakes

- **Gating summary generation behind trust check.** Summary MUST be unconditional. This is the #1 cause of broken metrics for school photos.
- **Comparing VerificationStatus enum to int.** `$photo->verified >= 2` fails. Use `$photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value`.
- **Dispatching `TagsVerifiedByAdmin` for school students.** School photos must wait for teacher approval. Only trusted users get immediate dispatch.
- **Including `geom` in API responses.** Binary spatial data. Keep it in `$hidden`.
- **Using `$photo->toArray()` for queue responses.** The Location model's `updatedAtDiffForHumans` accessor crashes on null `updated_at`. Build response arrays manually when including country relation. See `AdminQueueController` for pattern.
- **Passing null datetime to `UploadPhotoAction::run()`.** The method type-hints `Carbon $datetime`. If EXIF has no datetime, `getDateTimeForPhoto()` returns null. Validation must reject first; controller has `?? Carbon::now()` safety fallback.
- **Not guarding `dmsToDec()` against zero denominators.** EXIF GPS values are `"numerator/denominator"` format. If denominator is 0 in any of the 6 components (degrees/minutes/seconds for lat and lon), division crashes. The function now returns `null` instead.
- **Rejecting 0,0 coordinates.** Photos at latitude 0, longitude 0 are valid (Gulf of Guinea). Do not reject `0,0` — only reject `null`.
- **Forgetting `city_id` in factory.** PhotoFactory doesn't include `city_id` by default. Add `'city_id' => City::factory()` when testing location-dependent features.
- **Confusing `category_litter_object_id` with `category_id`.** Phase 1 adds `category_litter_object_id` (FK to `category_litter_object` pivot) and `litter_object_type_id` (FK to `litter_object_types`) to `photo_tags`. Both are nullable in Phase 1. The existing `category_id` and `litter_object_id` columns remain and are still the authoritative source until Phase 3.
- **Returning `'tags'` instead of `'new_tags'` in upload controller.** `UsersUploadsController` must return tags under the key `'new_tags'` — the `Uploads.vue` frontend reads `photo.new_tags` for tag counts and objects list.
- **Using `doesntHave('photoTags')` for untagged filter.** The `/tag` page filter must use `WHERE verified = 0` (VerificationStatus::UNVERIFIED), not `doesntHave('photoTags')`. V4-migrated photos may have `verified >= 1` but no v5 photoTags records, or photoTags can exist without the photo being fully tagged.
