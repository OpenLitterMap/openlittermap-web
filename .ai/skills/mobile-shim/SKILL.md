---
name: mobile-shim
description: Mobile API endpoints, v4 tag format conversion, AddTagsToUploadedImageController, old mobile tagging routes, and ConvertV4TagsAction shim design.
---

# Mobile API Shim

The mobile app sends v4 tag format (`{smoking: {butts: 3}}`) to old endpoints. The backend must convert this to v5 PhotoTags. Zero mobile app changes — the shim is backend-only.

## Key Files

- `app/Http/Controllers/API/AddTagsToUploadedImageController.php` — Current old mobile tag endpoint
- `app/Http/Requests/Api/AddTagsRequest.php` — Validation for old mobile format
- `app/Jobs/Api/AddTags.php` — Queued job dispatched by old controller
- `app/Jobs/Photos/AddTagsToPhoto.php` — Alternative queued tagging job
- `app/Services/Tags/ClassifyTagsService.php` — Tag classification + deprecated key mapping
- `readme/Mobile.md` — Design document for the shim

## Current State

The old mobile flow uses **deprecated v4 category tables** (being removed). It needs to be replaced with a shim that converts v4 format to v5 PhotoTags.

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

### Conversion flow (ConvertV4TagsAction — to be built)

```php
// Planned flow:
// 1. Accept v4 payload: {category: {tagKey: quantity}}
// 2. For each tag:
//    - ClassifyTagsService::classify($tagKey) → handles deprecated keys
//    - ClassifyTagsService::normalizeDeprecatedTag($key) → old key -> new key + materials
//    - Create PhotoTag with category_id + litter_object_id
//    - Attach materials as PhotoTagExtraTags
// 3. Handle 'brands' category separately (brand-only tags)
// 4. Handle custom_tags
// 5. GeneratePhotoSummaryService::run($photo) → summary + XP
// 6. If trusted: fire TagsVerifiedByAdmin
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

### Current AddTags job flow (to be replaced)

```php
// app/Jobs/Api/AddTags.php
public function handle(): void
{
    // 1. Calls old AddTagsToPhotoAction (writes to v4 category tables)
    // 2. Sets total_litter, result_string (deprecated columns)
    // 3. If trusted: fires TagsVerifiedByAdmin
    // PROBLEM: Writes to deprecated tables, no PhotoTags created
}
```

## Common Mistakes

- **Building ConvertV4TagsAction without handling the `brands` category.** Mobile sends brands under `tags.brands.{brandKey}`. These need brand-only PhotoTags.
- **Not using `ClassifyTagsService::normalizeDeprecatedTag()`.** Old mobile keys like `beerBottle`, `tinCan` must be normalized before lookup.
- **Skipping summary generation.** Without summary, MetricsService processes zero metrics.
- **Duplicating tag records on retry.** Use `PhotoTag::firstOrCreate()` or check existing tags before creating.
- **Modifying the mobile API contract.** The shim must accept the exact same request format. No new required fields.
