---
name: photo-pipeline
description: Photo upload, tagging, verification status, summary generation, XP calculation, AddTagsToPhotoAction, UploadPhotoController, and the VerificationStatus enum.
---

# Photo Pipeline

Photos flow through three phases: Upload (observation only) -> Tag (summary + XP) -> Verify (metrics). Each phase is independent and idempotent.

## Key Files

- `app/Http/Controllers/Uploads/UploadPhotoController.php` — Web upload entry point
- `app/Http/Controllers/API/Tags/PhotoTagsController.php` — V5 tagging endpoint (`POST /api/v3/tags`)
- `app/Actions/Tags/AddTagsToPhotoAction.php` — Core tagging logic (v5)
- `app/Services/Tags/GeneratePhotoSummaryService.php` — Builds summary JSON + calculates XP
- `app/Services/Tags/XpCalculator.php` — XP scoring rules
- `app/Enums/VerificationStatus.php` — Photo verification state machine
- `app/Enums/XpScore.php` — XP values per tag type
- `app/Http/Requests/Api/PhotoTagsRequest.php` — V5 tag request validation
- `app/Observers/PhotoObserver.php` — Sets `is_public = false` for school team photos

## Invariants

1. **Upload creates observation only.** No tags, no XP, no summary, no metrics. Just the photo record with location FKs.
2. **Summary generation is unconditional.** `GeneratePhotoSummaryService::run()` MUST run regardless of trust level. School photos need a summary at tag time so it exists when the teacher approves later. Gating summary behind a trust check causes null summary at approval = zero metrics.
3. **XP calculation is unconditional.** Runs for all users, before verification.
4. **`TagsVerifiedByAdmin` only fires for trusted users.** School students' photos stop at `VERIFIED(1)` and wait for teacher approval.
5. **VerificationStatus is an enum cast.** `$photo->verified` returns the enum, not an int. Use `->value` for `>=`/`<` comparisons, `===` for equality checks. Never compare enum to raw int.

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

`UploadPhotoController::__invoke()` flow:
1. `MakeImageAction::run($file)` — extract EXIF
2. `UploadPhotoAction::run()` x2 — S3 full image + bbox thumbnail
3. `ResolveLocationAction::run($lat, $lon)` — Country/State/City FKs
4. `Photo::create()` — observation record with FKs only
5. `event(new ImageUploaded(...))` — real-time broadcast

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
    // ALWAYS — sets XP before verification

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
- **Forgetting `city_id` in factory.** PhotoFactory doesn't include `city_id` by default. Add `'city_id' => City::factory()` when testing location-dependent features.
