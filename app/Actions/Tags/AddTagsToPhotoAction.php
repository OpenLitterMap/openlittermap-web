<?php

namespace App\Actions\Tags;

use App\Enums\VerificationStatus;
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
     * - Choose the save method from the fields in each tag.
     * - CLO ID (category_litter_object_id): {category_litter_object_id: 42, quantity: 2}.
     * - Without a CLO ID, web tagging, admin review and the facilitator queue send
     *   object and category separately, e.g. {object: "butts", category: "smoking"}.
     * - Standalone extras have no object, e.g. {custom: true, key: "found on bench"}.
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
     * - Convert extra tags to one format and validate them before creating rows.
     * - Materials/brands: [10] becomes [{id: 10}]. Brand quantity defaults to 1.
     * - Custom tags: "found on bench" and {tag: "found on bench"} become {key: "found on bench"}.
     * - Materials and custom tags use the photo tag's quantity; brands have their own quantity.
     * - Example: 3 bottles with glass and 1 brand count as 3 glass items and 1 brand tag.
     * - Invalid entries return 422; conflicting key/tag values are rejected.
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
            'tags.*.brand.quantity' => 'sometimes|integer|min:1',
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
     * - Return 422 when a retired object has no valid replacement.
     * - Example: retired_at is set but merged_into_id is missing.
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
     * - Identify standalone brand, material or custom tags.
     * - Example: {custom: true, key: "found on bench"} needs no object or CLO ID.
     */
    protected function isExtraTagOnly(array $tag): bool
    {
        return (! empty($tag['brand_only']) && isset($tag['brand']))
            || (! empty($tag['material_only']) && isset($tag['material']))
            || (! empty($tag['custom']) && isset($tag['key']));
    }

    /**
     * - Create a PhotoTag with null category_id, litter_object_id and CLO ID.
     * - Attach the standalone brand, material or custom tag, plus any extra tags.
     * - Example: {material_only: true, material: {id: 10}, quantity: 3}.
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
            $photoTag->attachExtraTags([['id' => $brandModel->id, 'quantity' => $tag['brand']['quantity'] ?? $quantity]], 'brand');
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
     * - Save a tag using its CLO ID (category_litter_object_id).
     * - Follow recorded redirects and check litter_object_type_id on the final CLO.
     * - Example: {category_litter_object_id: 42, quantity: 2, materials: [10]}.
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
     * - Web tagging (AddTags.vue), AdminQueue and FacilitatorQueue use this fallback
     *   when a tag has no CLO ID (category_litter_object_id).
     * - Example: {object: "butts", category: "smoking", quantity: 2}.
     * - Find the CLO for the supplied object and category, then call createTagFromClo().
     * - Use an omitted category only when the object has exactly one available category.
     * - Return 422 if the category/object has no CLO and no recorded replacement.
     *
     * @throws ValidationException
     */
    protected function createTagFromObject(int $userId, int $photoId, array $tag): PhotoTag
    {
        [$category, $object] = $this->resolveTag($tag);
        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)->first();

        // - Look up the category/object's CLO first.
        // - If it has no CLO, a retired object may still have merged_into_id.
        // - Find that replacement's CLO in the same category, then follow its redirects too.
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
     * - Attach each material once; its stored extra-tag quantity is 1.
     * - Summary and XP use the photo tag's quantity, e.g. 3 bottles + glass = 3 glass items.
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
     * - Attach brands with their own quantities, defaulting to 1.
     * - Example: [{id: 10, quantity: 2}] records 2 tags for that brand.
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
     * - Save custom text in custom_tags_new and attach it to the PhotoTag.
     * - Remove HTML and surrounding whitespace, then limit the key to 255 characters.
     * - Example: "  <b>found on bench</b>  " becomes "found on bench".
     * - Skip blank text; each custom tag uses the photo tag's quantity in summary and XP.
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

            // - Keep punctuation, e.g. "Black & Mild"; only HTML and surrounding spaces are removed.
            // - custom_tags_new.key holds up to 255 characters.
            $cleanTag = mb_substr(trim(strip_tags($customTagKey)), 0, 255);

            // - Skip text that is empty after cleaning; keep the other valid tags.
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
     * - Look up object and category by key or ID, e.g. "butts" or {id: 5}.
     * - Keep the category the caller supplied.
     * - If category is omitted, require exactly one available category for that object.
     * - Example: an object available in both alcohol and softdrinks needs a category.
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

        // - Choose a category only when the caller omitted it and there is exactly one choice.
        if (! $categoryProvided) {
            // - Active objects choose among offerable CLOs only.
            // - Retired objects keep their old CLOs so the recorded redirects can be followed.
            $categories = ($object->isRetired() ? $object->categories() : $object->offerableCategories())
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
     * - Users who do not require verification receive ADMIN_APPROVED.
     * - School students who require verification receive VERIFIED and wait for teacher approval.
     * - Other users fire TagsVerifiedByAdmin so MetricsService can update their metrics.
     * - Example: a school student's tags get summary and XP, but no leaderboard credit until approval.
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

        // - School students wait for teacher approval before metrics are processed.
        // - Other users receive leaderboard credit through TagsVerifiedByAdmin.
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
