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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddTagsToPhotoAction
{
    public function __construct() {}

    /**
     * Add tags to a photo, generate summary, calculate XP, and handle verification.
     *
     * After this runs:
     * - photo_tags rows and any supplied extra tags exist
     * - photo.summary JSON is populated
     * - photo.xp is calculated
     *
     * Unless skipVerification is true:
     * - Verification status is updated
     * - School students await teacher approval
     * - Other users fire TagsVerifiedByAdmin so metrics are processed
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

            // Admin controllers skip this because they handle approval and metrics themselves.
            if (! $skipVerification) {
                $this->updateVerification($userId, $photo);
            }

            return $photoTags;
        });
    }

    /**
     * Choose how to save each tag based on the fields supplied.
     *
     * A pairing ID identifies the category and object together. Some callers still send
     * the object and category separately. Brand/material/custom-only tags have no object.
     *
     * @throws \Exception
     */
    protected function addTagsToPhoto(int $userId, int $photoId, array $tags): array
    {
        $tags = $this->normalizeTags($tags);

        $photoTags = [];

        foreach ($tags as $tag) {
            if (isset($tag['category_litter_object_id'])) {
                $photoTags[] = $this->createTagFromClo($userId, $photoId, $tag);
            } elseif ($this->isExtraTagOnly($tag)) {
                $photoTags[] = $this->createExtraTagOnly($userId, $photoId, $tag);
            } else {
                $photoTags[] = $this->createTagFromObject($userId, $photoId, $tag);
            }
        }

        return $photoTags;
    }

    /**
     * Normalise extra tags before any rows are created.
     *
     * - Materials and brands accept IDs or objects containing an ID.
     * - Custom tags accept text, {key: text}, or the older {tag: text} format.
     * - Materials and custom tags inherit the parent quantity; brands keep their own.
     * - Reject malformed entries instead of silently losing them during replacement.
     */
    protected function normalizeTags(array $tags): array
    {
        foreach ($tags as $i => &$tag) {
            if (! is_array($tag)) {
                continue;
            }
            foreach (['materials', 'brands', 'custom_tags'] as $field) {
                if (($tag[$field] ?? null) === null) {
                    $tag[$field] = [];
                }
                if (! is_array($tag[$field])) {
                    continue;
                }
                foreach ($tag[$field] as $j => &$extra) {
                    if ($field !== 'custom_tags') {
                        $extra = is_array($extra) ? $extra : ['id' => $extra];
                        continue;
                    }
                    if (! is_array($extra)) {
                        $extra = ['key' => $extra];
                    } elseif (array_key_exists('tag', $extra)) {
                        if (array_key_exists('key', $extra) && $extra['key'] !== $extra['tag']) {
                            throw ValidationException::withMessages([
                                "tags.{$i}.custom_tags.{$j}" => ['Supply one custom tag value.'],
                            ]);
                        }
                        $extra['key'] = $extra['tag'];
                        unset($extra['tag']);
                    }
                }
                unset($extra);
            }
        }
        unset($tag);

        $namedTag = static function ($attribute, $value, $fail): void {
            if (! is_string($value) && (! is_array($value)
                || filter_var($value['id'] ?? null, FILTER_VALIDATE_INT) === false)) {
                $fail('Supply a tag key or an object containing an integer id.');
            }
        };
        Validator::make(['tags' => $tags], [
            'tags.*' => 'array',
            'tags.*.category_litter_object_id' => 'sometimes|integer|exists:category_litter_object,id',
            'tags.*.litter_object_type_id' => 'nullable|integer|exists:litter_object_types,id',
            'tags.*.category_id' => 'sometimes|integer|exists:categories,id',
            'tags.*.object' => ['nullable', $namedTag],
            'tags.*.category' => ['nullable', $namedTag],
            'tags.*.quantity' => 'sometimes|integer|min:1',
            'tags.*.picked_up' => 'nullable|boolean',
            'tags.*.materials' => 'array',
            'tags.*.materials.*.id' => 'required|integer|exists:materials,id',
            'tags.*.brands' => 'array',
            'tags.*.brands.*.id' => 'required|integer|exists:brandslist,id',
            'tags.*.brands.*.quantity' => 'sometimes|integer|min:1',
            'tags.*.custom_tags' => 'array',
            'tags.*.custom_tags.*.key' => 'present|nullable|string',
            'tags.*.brand_only' => 'sometimes|boolean',
            'tags.*.brand' => 'required_if:tags.*.brand_only,true|array',
            'tags.*.brand.id' => 'required_with:tags.*.brand|integer|exists:brandslist,id',
            'tags.*.material_only' => 'sometimes|boolean',
            'tags.*.material' => 'required_if:tags.*.material_only,true|array',
            'tags.*.material.id' => 'required_with:tags.*.material|integer|exists:materials,id',
            'tags.*.custom' => 'sometimes|boolean',
            'tags.*.key' => 'sometimes|string',
        ])->validate();

        return $tags;
    }

    /**
     * Reject a retired tag when no valid replacement can be found.
     *
     * @throws ValidationException
     */
    protected function rejectRetiredObject(string $key): never
    {
        throw ValidationException::withMessages([
            'tags' => ["Litter object '{$key}' is retired and can no longer be tagged."],
        ]);
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
     * Save a tag using its category-object pairing ID (category_litter_object_id).
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

        $mapping = $clo->resolveForWrite($tag['litter_object_type_id'] ?? null);
        $clo = $mapping['clo'];
        $cloId = $clo->id;
        $typeId = $mapping['type_id'];
        $quantity = max(1, (int) ($tag['quantity'] ?? 1));
        $pickedUp = $tag['picked_up'] ?? null;

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
     * Resolve an object-format tag, then save it through createTagFromClo.
     *
     * - Web/admin fallbacks and older clients send object and category separately.
     * - Use the supplied category; infer one only when there is a single choice.
     * - Reject undeclared pairings and follow recorded replacements.
     * - Keep this adapter until every object tag is submitted with a pairing ID.
     *
     * @throws ValidationException
     */
    protected function createTagFromObject(int $userId, int $photoId, array $tag): PhotoTag
    {
        [$category, $object] = $this->resolveTag($tag);
        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)->first();

        // - Prefer the recorded category/object pairing.
        // - Older retirements may have only an object replacement.
        // - Both paths use the same redirect and subtype checks when saving.
        if ($clo === null && $object->isRetired()) {
            $active = $object->activeObject();
            $clo = $active === null ? null : CategoryObject::where('category_id', $category->id)
                ->where('litter_object_id', $active->id)->first();
            if ($clo === null) {
                $this->rejectRetiredObject($object->key);
            }
        }

        if ($clo === null) {
            throw ValidationException::withMessages([
                'tags' => ["The tag '{$object->key}' is not available in '{$category->key}'. Choose a valid tag in that category before saving."],
            ]);
        }

        $tag['category_litter_object_id'] = $clo->id;

        return $this->createTagFromClo($userId, $photoId, $tag);
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

            // Sanitize and accept — strip HTML, trim, cap to the key column length
            // (custom_tags_new.key is varchar(255)). Punctuation like & . ' / is
            // legitimate in brand/product names, so there is no allowlist and no throw.
            $cleanTag = mb_substr(trim(strip_tags($customTagKey)), 0, 255);

            // Skip empties instead of throwing — one cosmetically-bad custom tag
            // must never abort the whole POST or roll back the user's valid tags.
            if ($cleanTag === '') {
                continue;
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
     * Look up the supplied object and category by ID or key.
     * Require a category when the object has more than one pairing.
     */
    protected function resolveTag(array $tag): array
    {
        $category = null;
        $object = null;
        $categoryProvided = isset($tag['category_id']) || isset($tag['category']);

        if (isset($tag['category_id'])) {
            $category = Category::find($tag['category_id']);
        } elseif (isset($tag['category'])) {
            $category = is_array($tag['category']) && isset($tag['category']['id'])
                ? Category::find($tag['category']['id'])
                : Category::where('key', $tag['category'])->first();
        }

        if ($categoryProvided && $category === null) {
            throw ValidationException::withMessages([
                'tags' => ['The supplied category does not exist. Refresh the tag list before saving.'],
            ]);
        }

        if (isset($tag['object'])) {
            $object = is_array($tag['object']) && isset($tag['object']['id'])
                ? LitterObject::find($tag['object']['id'])
                : LitterObject::where('key', $tag['object'])->first();
        }

        if ($object === null) {
            throw ValidationException::withMessages([
                'tags' => ['The supplied object does not exist. Refresh the tag list before saving.'],
            ]);
        }

        // Never substitute another category for one the caller supplied.
        // When none was supplied, infer it only if there is exactly one choice.
        if (! $categoryProvided) {
            $categories = $object->categories()
                // A live object's old category redirects are no longer choices.
                // A retired object still needs its source pairing to resolve the replacement.
                ->when(! $object->isRetired(), fn ($query) => $query->whereNull('category_litter_object.merged_into_clo_id'))
                ->limit(2)
                ->get();

            if ($categories->count() !== 1) {
                throw ValidationException::withMessages([
                    'tags' => ["Choose a category for '{$object->key}' before saving."],
                ]);
            }

            $category = $categories->first();
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
