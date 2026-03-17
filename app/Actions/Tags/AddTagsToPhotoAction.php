<?php

namespace App\Actions\Tags;

use App\Enums\VerificationStatus;
use App\Enums\XpScore;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AddTagsToPhotoAction
{
    public function __construct() {}

    /**
     * Add tags to a photo, generate summary, calculate XP, and handle verification.
     *
     * After this runs:
     * - photo_tags + extra_tags rows exist
     * - photo.summary JSON is populated
     * - photo.xp is set
     * - If trusted user → TagsVerifiedByAdmin fires → MetricsService processes everything
     *
     * @throws \Exception
     */
    public function run(int $userId, int $photoId, array $tags, bool $skipVerification = false): array
    {
        return DB::transaction(function () use ($userId, $photoId, $tags, $skipVerification) {
            $photoTags = $this->addTagsToPhoto($userId, $photoId, $tags);

            // Generate summary JSON + XP — MetricsService reads from these
            $photo = Photo::find($photoId);
            $photo->generateSummary();
            $photo->refresh();

            // Handle verification + dispatch TagsVerifiedByAdmin if trusted.
            // Admin controllers pass skipVerification=true because they handle
            // verification and metrics themselves (atomic approve + event/processPhoto).
            if (! $skipVerification) {
                $this->updateVerification($userId, $photo);
            }

            return $photoTags;
        });
    }

    /**
     * Create PhotoTag records with extra tags (materials, brands, custom tags).
     *
     * Accepts two payload formats:
     * - New: { category_litter_object_id, litter_object_type_id?, ... }
     * - Legacy: { object: {id, key}, category?, brand_only?, material_only?, ... }
     *
     * @throws \Exception
     */
    protected function addTagsToPhoto(int $userId, int $photoId, array $tags): array
    {
        $photoTags = [];

        foreach ($tags as $tag) {
            // Detect payload format
            if (isset($tag['category_litter_object_id'])) {
                $photoTags[] = $this->createTagFromClo($userId, $photoId, $tag);
            } elseif ($this->isExtraTagOnly($tag)) {
                $photoTags[] = $this->createExtraTagOnly($userId, $photoId, $tag);
            } else {
                $photoTags[] = $this->createTagLegacy($userId, $photoId, $tag);
            }
        }

        return $photoTags;
    }

    /**
     * Check if this tag payload contains only extra tags (no object).
     */
    protected function isExtraTagOnly(array $tag): bool
    {
        return (! empty($tag['brand_only']) && isset($tag['brand']))
            || (! empty($tag['material_only']) && isset($tag['material']))
            || (! empty($tag['custom']) && isset($tag['key']));
    }

    /**
     * Create a PhotoTag with no object — only extra tags (brand, material, or custom tag).
     */
    protected function createExtraTagOnly(int $userId, int $photoId, array $tag): PhotoTag
    {
        $quantity = max(1, (int) ($tag['quantity'] ?? 1));
        $pickedUp = $tag['picked_up'] ?? null;

        $photoTag = PhotoTag::create([
            'photo_id' => $photoId,
            'quantity' => $quantity,
            'picked_up' => $pickedUp,
        ]);

        if (! empty($tag['brand_only']) && isset($tag['brand'])) {
            $brandModel = BrandList::find($tag['brand']['id']);
            if (! $brandModel) {
                throw new \Exception("Brand {$tag['brand']['key']} not found.");
            }
            $photoTag->attachExtraTags([['id' => $brandModel->id, 'quantity' => $quantity]], 'brand');
        } elseif (! empty($tag['material_only']) && isset($tag['material'])) {
            $materialModel = Materials::find($tag['material']['id']);
            if (! $materialModel) {
                throw new \Exception("Material with ID {$tag['material']['id']} not found.");
            }
            $photoTag->attachExtraTags([['id' => $materialModel->id]], 'material');
        } elseif (! empty($tag['custom']) && isset($tag['key'])) {
            $this->attachCustomTags($userId, $photoTag, [$tag['key']]);
        }

        // Attach additional extras on custom/brand/material-only tags
        if (! empty($tag['brands'])) {
            $this->attachBrands($photoTag, $tag['brands']);
        }
        if (! empty($tag['materials'])) {
            $this->attachMaterials($photoTag, $tag['materials']);
        }
        if (! empty($tag['custom_tags'])) {
            $this->attachCustomTags($userId, $photoTag, $tag['custom_tags']);
        }

        return $photoTag;
    }

    /**
     * New CLO-based tag creation.
     *
     * @throws \Exception
     */
    protected function createTagFromClo(int $userId, int $photoId, array $tag): PhotoTag
    {
        $cloId = $tag['category_litter_object_id'];
        $clo = CategoryObject::find($cloId);

        if (! $clo) {
            throw ValidationException::withMessages([
                'tags' => ["Invalid category_litter_object_id: {$cloId}"],
            ]);
        }

        $quantity = max(1, (int) ($tag['quantity'] ?? 1));
        $pickedUp = $tag['picked_up'] ?? null;

        // Validate type if provided
        $typeId = $tag['litter_object_type_id'] ?? null;
        if ($typeId) {
            $validType = DB::table('category_object_types')
                ->where('category_litter_object_id', $cloId)
                ->where('litter_object_type_id', $typeId)
                ->exists();

            if (! $validType) {
                throw ValidationException::withMessages([
                    'tags' => ["Type {$typeId} is not valid for CLO {$cloId}"],
                ]);
            }
        }

        // Validate "other" object requires at least one extra tag
        $object = LitterObject::find($clo->litter_object_id);
        if ($object && $object->key === 'other') {
            $hasMaterials = ! empty($tag['materials']);
            $hasBrands = ! empty($tag['brands']);
            $hasCustomTags = ! empty($tag['custom_tags']);

            if (! $hasMaterials && ! $hasBrands && ! $hasCustomTags) {
                throw ValidationException::withMessages([
                    'tags' => ['Object "other" requires at least one material, brand, or custom tag.'],
                ]);
            }
        }

        // Create the PhotoTag
        $photoTag = PhotoTag::create([
            'photo_id' => $photoId,
            'category_litter_object_id' => $cloId,
            'category_id' => $clo->category_id,
            'litter_object_id' => $clo->litter_object_id,
            'litter_object_type_id' => $typeId,
            'quantity' => $quantity,
            'picked_up' => $pickedUp,
        ]);

        // Attach materials
        $this->attachMaterials($photoTag, $tag['materials'] ?? []);

        // Attach brands
        $this->attachBrands($photoTag, $tag['brands'] ?? []);

        // Attach custom tags
        $this->attachCustomTags($userId, $photoTag, $tag['custom_tags'] ?? []);

        return $photoTag;
    }

    /**
     * Legacy format tag creation (backward compatibility for old frontend/mobile).
     *
     * @throws \Exception
     */
    protected function createTagLegacy(int $userId, int $photoId, array $tag): PhotoTag
    {
        [$category, $object, $quantity, $pickedUp] = $this->resolveTag($tag);

        // Resolve CLO from category + object
        $clo = null;
        if ($category && $object) {
            $clo = CategoryObject::where('category_id', $category->id)
                ->where('litter_object_id', $object->id)
                ->first();

            if (! $clo) {
                throw ValidationException::withMessages([
                    'tags' => [[
                        'msg' => 'Category does not contain object',
                        'category' => $category->key,
                        'object' => $object->key,
                    ]],
                ]);
            }
        }

        $photoTag = PhotoTag::create([
            'photo_id' => $photoId,
            'category_litter_object_id' => $clo?->id,
            'category_id' => $clo?->category_id,
            'litter_object_id' => $clo?->litter_object_id,
            'quantity' => $quantity,
            'picked_up' => $pickedUp,
        ]);

        // TODO: Re-enable CheckLocationTypeAward when badge system is ready
        // if ($object?->key === 'bags_litter' && $pickedUp) {
        //     $this->checkLocationTypeAward->checkLandUseAward($userId, $photoTag);
        // }

        // Custom tag as primary (legacy format: { custom: true, key: "..." })
        if (isset($tag['custom']) && $tag['custom'] && isset($tag['key'])) {
            $customTagModel = CustomTagNew::firstOrCreate(['key' => $tag['key']]);

            if ($customTagModel->wasRecentlyCreated) {
                $customTagModel->created_by = $userId;
                $customTagModel->save();
            }

            $photoTag->attachExtraTags([['id' => $customTagModel->id]], 'custom_tag');
        }

        // Materials as extra tags
        if (! empty($tag['materials'])) {
            $materialExtras = collect($tag['materials'])->map(fn($m) => [
                'id' => is_array($m) ? $m['id'] : $m,
            ])->all();

            $photoTag->attachExtraTags($materialExtras, 'material');
        }

        // Custom tags as extra tags
        if (! empty($tag['custom_tags'])) {
            $this->attachCustomTags($userId, $photoTag, $tag['custom_tags']);
        }

        // Brands as extra tags
        if (! empty($tag['brands'])) {
            $brandExtras = collect($tag['brands'])->map(fn($b) => [
                'id' => $b['id'],
                'quantity' => $b['quantity'] ?? 1,
            ])->all();

            $photoTag->attachExtraTags($brandExtras, 'brand');
        }

        return $photoTag;
    }

    /**
     * Attach material extras to a PhotoTag.
     */
    protected function attachMaterials(PhotoTag $photoTag, array $materialIds): void
    {
        if (empty($materialIds)) {
            return;
        }

        $extras = [];
        foreach ($materialIds as $materialId) {
            $id = is_array($materialId) ? $materialId['id'] : $materialId;

            if (! Materials::where('id', $id)->exists()) {
                throw new \Exception("Material with ID {$id} not found.");
            }

            $extras[] = ['id' => $id];
        }

        $photoTag->attachExtraTags($extras, 'material');
    }

    /**
     * Attach brand extras to a PhotoTag.
     */
    protected function attachBrands(PhotoTag $photoTag, array $brands): void
    {
        if (empty($brands)) {
            return;
        }

        $extras = [];
        foreach ($brands as $brandData) {
            $id = is_array($brandData) ? $brandData['id'] : $brandData;
            $qty = is_array($brandData) ? ($brandData['quantity'] ?? 1) : 1;

            if (! BrandList::where('id', $id)->exists()) {
                throw new \Exception("Brand with ID {$id} not found.");
            }

            $extras[] = ['id' => $id, 'quantity' => $qty];
        }

        $photoTag->attachExtraTags($extras, 'brand');
    }

    /**
     * Attach custom tag extras to a PhotoTag.
     */
    protected function attachCustomTags(int $userId, PhotoTag $photoTag, array $customTags): void
    {
        if (empty($customTags)) {
            return;
        }

        $extras = [];
        foreach ($customTags as $customTagData) {
            $customTagKey = is_array($customTagData)
                ? ($customTagData['key'] ?? '')
                : $customTagData;

            $cleanTag = trim(strip_tags($customTagKey));

            if (! preg_match('/^[\w\s:-]+$/', $cleanTag)) {
                throw new \Exception('Invalid custom tag.');
            }

            $customTagModel = CustomTagNew::firstOrCreate(['key' => $cleanTag]);

            if ($customTagModel->wasRecentlyCreated) {
                $customTagModel->created_by = $userId;
                $customTagModel->save();
            }

            $extras[] = ['id' => $customTagModel->id];
        }

        $photoTag->attachExtraTags($extras, 'custom_tag');
    }


    /**
     * Resolve category and object from tag input (legacy format).
     * Accepts either { id: int } or string key for both.
     * Auto-resolves category from object if not explicitly provided.
     */
    protected function resolveTag(array $tag): array
    {
        $category = null;
        $object = null;

        if (isset($tag['category_id'])) {
            $category = Category::find($tag['category_id']);
        } elseif (isset($tag['category'])) {
            $category = is_array($tag['category']) && isset($tag['category']['id'])
                ? Category::find($tag['category']['id'])
                : Category::where('key', $tag['category'])->first();
        }

        if (isset($tag['object'])) {
            $object = is_array($tag['object']) && isset($tag['object']['id'])
                ? LitterObject::find($tag['object']['id'])
                : LitterObject::where('key', $tag['object'])->first();

            if ($object) {
                // Validate provided category belongs to this object, fall back otherwise
                if ($category && ! $object->categories()->where('categories.id', $category->id)->exists()) {
                    $category = null;
                }

                if (! $category) {
                    $category = $object->categories()->first();
                }
            }
        }

        return [
            $category,
            $object,
            max(1, (int) ($tag['quantity'] ?? 1)),
            $tag['picked_up'] ?? null,
        ];
    }

    /**
     * Calculate XP from PhotoTag records using XpScore enum multipliers.
     *
     * Upload=5, Object=1 (special objects override), Brand=3, Material=2, CustomTag=1.
     * Materials and custom tags use the parent tag's quantity (set membership).
     * Brands use their own independent quantity.
     */
    protected function calculateXp(array $photoTags): int
    {
        $xp = 0; // Tag XP only — upload XP is awarded separately by UploadPhotoController

        foreach ($photoTags as $photoTag) {
            // Object XP — only if there's an actual object
            $objectKey = $photoTag->object?->key;
            if ($objectKey) {
                $typeKey = $photoTag->type?->key;
                $objectXp = XpScore::getObjectXp($objectKey, $typeKey);
                $xp += $photoTag->quantity * $objectXp;
            }

            // Reload extra tags if not already loaded
            if (! $photoTag->relationLoaded('extraTags')) {
                $photoTag->load('extraTags');
            }

            foreach ($photoTag->extraTags as $extraTag) {
                $xp += match ($extraTag->tag_type) {
                    'brand'      => $extraTag->quantity * XpScore::Brand->xp(),
                    'material'   => $photoTag->quantity * XpScore::Material->xp(),
                    'custom_tag' => $photoTag->quantity * XpScore::CustomTag->xp(),
                    default      => $extraTag->quantity,
                };
            }
        }

        return $xp;
    }

    /**
     * Set verification status and dispatch metrics event.
     *
     * All users get immediate leaderboard credit via TagsVerifiedByAdmin → ProcessPhotoMetrics.
     * Only trusted users get ADMIN_APPROVED (photos visible on map).
     * School students wait for teacher approval (safeguarding pipeline).
     */
    protected function updateVerification(int $userId, Photo $photo): void
    {
        $user = User::find($userId);
        $isSchoolStudent = false;

        if ($user->verification_required) {
            $photo->verification = 0.1;

            if ($photo->team_id) {
                $team = Team::find($photo->team_id);

                if ($team && $team->isSchool()) {
                    $photo->verified = VerificationStatus::VERIFIED->value;
                    $isSchoolStudent = true;
                }
            }
        } else {
            $photo->verification = 1;
            $photo->verified = VerificationStatus::ADMIN_APPROVED->value;
        }

        $photo->save();

        // Process metrics for all users except school students (teacher must approve first).
        // Non-trusted users' photos stay at verified=0 (not on map) but still get leaderboard XP.
        if (! $isSchoolStudent) {
            event(new TagsVerifiedByAdmin(
                $photo->id,
                $photo->user_id,
                $photo->country_id,
                $photo->state_id,
                $photo->city_id,
                $photo->team_id
            ));
        }
    }
}
