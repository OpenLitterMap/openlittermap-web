---
name: mobile-shim
description: Mobile API endpoints, v4 tag format conversion, AddTagsToUploadedImageController, old mobile tagging routes, and ConvertV4TagsAction shim design.
---

# Mobile API Shim

The mobile app sends v4 tag format (`{smoking: {butts: 3}}`) to old endpoints. The backend must convert this to v5 PhotoTags. Zero mobile app changes — the shim is backend-only.

## Key Files

- `app/Actions/Tags/ConvertV4TagsAction.php` — Shim: v4 payload → UpdateTagsService → v5 PhotoTags
- `app/Http/Controllers/API/AddTagsToUploadedImageController.php` — Mobile tag endpoint (wired to shim)
- `app/Http/Controllers/ApiPhotosController.php` — Upload-with-tags endpoint (wired to shim)
- `app/Http/Requests/Api/AddTagsRequest.php` — Validation for old mobile format
- `app/Services/Tags/UpdateTagsService.php` — Reused: same pipeline as olm:v5 migration
- `app/Services/Tags/ClassifyTagsService.php` — Tag classification + deprecated key mapping
- `tests/Feature/Mobile/ConvertV4TagsTest.php` — 7 tests (payload, summary, idempotency, verification)
- `readme/Mobile.md` — Design document for the shim

## Current State — DEPLOYED

The `ConvertV4TagsAction` shim is built and wired into both mobile tagging controllers. Mobile users contribute to v5 metrics immediately without an app update. The shim reuses the same `UpdateTagsService` pipeline as the `olm:v5` migration script (battle-tested against 500k+ photos).

### Old endpoints (routes/api.php)

```php
// Root API (legacy)
Route::post('add-tags', 'API\AddTagsToUploadedImageController')
    ->middleware('auth:api');

// V2 (still active for mobile)
Route::group(['prefix' => 'v2', 'middleware' => 'auth:api'], function () {
    Route::post('/add-tags-to-uploaded-image', 'API\AddTagsToUploadedImageController');
});

// Upload endpoints that accept optional tags
Route::post('photos/submit-with-tags', ...);
Route::post('photos/upload-with-tags', ...);
Route::post('photos/upload/with-or-without-tags', ...);
```

### Old request format (v4)

```json
{
    "photo_id": 123,
    "tags": {
        "smoking": { "butts": 5, "cigaretteBox": 1 },
        "softdrinks": { "tinCan": 2 },
        "brands": { "marlboro": 3 }
    },
    "picked_up": true,
    "custom_tags": ["my_custom_tag"]
}
```

### AddTagsRequest validation

```php
// photo_id: required, exists:photos,id
// tags: required_without_all:litter,custom_tags, array, min:1
// picked_up: nullable, boolean
// custom_tags: array, max:3
// custom_tags.*: distinct, min:3, max:100
```

## Invariants

1. **Zero mobile app changes.** The shim converts v4 payloads to v5 on the backend.
2. **Must handle mobile retries (idempotency).** Mobile may re-send the same tags.
3. **Must handle trust/verification gating.** Same rules as v5: trusted users get immediate `TagsVerifiedByAdmin`, school students stop at `VERIFIED(1)`.
4. **Brand matching is deferred.** Same as migration — brands extracted but not attached to specific objects.
5. **Summary must be generated.** After converting to PhotoTags, call `GeneratePhotoSummaryService::run()`.
6. **Endpoints eventually deprecated** when mobile app refactored to send v5 format to `POST /api/v3/tags`.

## Patterns

### Conversion flow (ConvertV4TagsAction — BUILT)

```php
class ConvertV4TagsAction
{
    public function __construct(
        private OldAddTagsToPhotoAction $oldAddTagsAction,     // Writes v4 data to category columns
        private AddCustomTagsToPhotoAction $oldAddCustomTagsAction,
        private UpdateTagsService $updateTagsService,           // v4→v5 conversion (same as olm:v5)
    ) {}

    public function run(int $userId, int $photoId, array $v4Tags, bool $pickedUp, array $customTags = []): void
    {
        // Idempotency: skip if already converted
        if ($photo->migrated_at !== null || $photo->photoTags()->exists()) return;

        // Step 1: Set remaining (affects XP picked_up bonus)
        // Step 2: Filter to known categories via Photo::categories()
        // Step 3: Old action writes v4 data to category columns
        // Step 4: UpdateTagsService reads back, creates v5 PhotoTags + summary + XP
        // Step 5: Handle verification (trusted/school/untrusted)
    }
}
```

### Key deprecated mappings used by mobile

```php
ClassifyTagsService::normalizeDeprecatedTag('beerBottle')
// → ['object' => 'beer_bottle', 'materials' => ['glass']]

ClassifyTagsService::normalizeDeprecatedTag('tinCan')
// → ['object' => 'soda_can', 'materials' => ['aluminium']]

ClassifyTagsService::normalizeDeprecatedTag('coffeeCups')
// → ['object' => 'cup', 'materials' => ['paper']]
```

### Old AddTags job flow (REPLACED by ConvertV4TagsAction)

The old `AddTags` job has been replaced. Both `AddTagsToUploadedImageController` and `ApiPhotosController::uploadWithOrWithoutTags()` now call `ConvertV4TagsAction::run()` synchronously instead of dispatching the old job.

## Common Mistakes

- **Building ConvertV4TagsAction without handling the `brands` category.** Mobile sends brands under `tags.brands.{brandKey}`. These need brand-only PhotoTags.
- **Not using `ClassifyTagsService::normalizeDeprecatedTag()`.** Old mobile keys like `beerBottle`, `tinCan` must be normalized before lookup.
- **Skipping summary generation.** Without summary, MetricsService processes zero metrics.
- **Duplicating tag records on retry.** Use `PhotoTag::firstOrCreate()` or check existing tags before creating.
- **Modifying the mobile API contract.** The shim must accept the exact same request format. No new required fields.
