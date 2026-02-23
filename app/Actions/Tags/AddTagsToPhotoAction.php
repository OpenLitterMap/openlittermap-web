<?php

namespace App\Actions\Tags;

use App\Actions\Badges\CheckLocationTypeAward;
use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Users\User;
use Illuminate\Validation\ValidationException;

class AddTagsToPhotoAction
{
    public function __construct(
        private CheckLocationTypeAward $checkLocationTypeAward,
    ) {}

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
    public function run(int $userId, int $photoId, array $tags): array
    {
        $photoTags = $this->addTagsToPhoto($userId, $photoId, $tags);

        // Generate summary JSON — MetricsService reads from this
        $photo = Photo::find($photoId);
        $photo->generateSummary();

        // Calculate and store XP on the photo
        $photo->xp = $this->calculateXp($photoTags);
        $photo->save();

        // Handle verification + dispatch TagsVerifiedByAdmin if trusted
        $this->updateVerification($userId, $photo);

        return $photoTags;
    }

    /**
     * Create PhotoTag records with extra tags (materials, brands, custom tags).
     *
     * @throws \Exception
     */
    protected function addTagsToPhoto(int $userId, int $photoId, array $tags): array
    {
        $photoTags = [];

        foreach ($tags as $tag) {
            [$category, $object, $quantity, $pickedUp] = $this->resolveTag($tag);

            // Handle brand-only tags (no category/object)
            if (! empty($tag['brand_only']) && isset($tag['brand'])) {
                $brandModel = BrandList::find($tag['brand']['id']);

                if (! $brandModel) {
                    throw new \Exception("Brand {$tag['brand']['key']} not found.");
                }

                $photoTag = PhotoTag::firstOrCreate([
                    'photo_id' => $photoId,
                    'category_id' => null,
                    'litter_object_id' => null,
                    'quantity' => $quantity,
                    'picked_up' => $pickedUp,
                ]);

                $photoTag->extraTags()->firstOrCreate([
                    'tag_type' => 'brand',
                    'tag_type_id' => $brandModel->id,
                ], [
                    'quantity' => $quantity,
                ]);

                $photoTags[] = $photoTag;
                continue;
            }

            // Handle material-only tags (no category/object)
            if (! empty($tag['material_only']) && isset($tag['material'])) {
                $materialModel = Materials::find($tag['material']['id']);

                if (! $materialModel) {
                    throw new \Exception("Material with ID {$tag['material']['id']} not found.");
                }

                $photoTag = PhotoTag::firstOrCreate([
                    'photo_id' => $photoId,
                    'category_id' => null,
                    'litter_object_id' => null,
                    'quantity' => $quantity,
                    'picked_up' => $pickedUp,
                ]);

                $photoTag->extraTags()->firstOrCreate([
                    'tag_type' => 'material',
                    'tag_type_id' => $materialModel->id,
                ], [
                    'quantity' => $quantity,
                ]);

                $photoTags[] = $photoTag;
                continue;
            }

            // Verify category-object association
            if ($category && $object && ! $category->litterObjects->contains($object)) {
                throw ValidationException::withMessages([
                    'tags' => [[
                        'msg' => 'Category does not contain object',
                        'category' => $category->key,
                        'object' => $object->key,
                    ]],
                ]);
            }

            $photoTag = PhotoTag::firstOrCreate([
                'photo_id' => $photoId,
                'category_id' => $category?->id,
                'litter_object_id' => $object?->id,
                'quantity' => $quantity,
                'picked_up' => $pickedUp,
            ]);

            // Badge check for bagsLitter + picked_up
            if ($object?->key === 'bagsLitter' && $pickedUp) {
                $this->checkLocationTypeAward->checkLandUseAward($userId, $photoTag);
            }

            // Custom tag as the primary tag
            if (isset($tag['custom']) && $tag['custom'] && isset($tag['key'])) {
                $customTagModel = CustomTagNew::firstOrCreate(['key' => $tag['key']]);

                if ($customTagModel->wasRecentlyCreated) {
                    $customTagModel->created_by = $userId;
                    $customTagModel->save();
                }

                $photoTag->custom_tag_primary_id = $customTagModel->id;
                $photoTag->save();
            }

            // Materials as extra tags
            if (! empty($tag['materials'])) {
                foreach ($tag['materials'] as $materialData) {
                    $materialModel = Materials::find($materialData['id']);

                    if (! $materialModel) {
                        throw new \Exception("Material with ID {$materialData['id']} not found.");
                    }

                    $photoTag->extraTags()->create([
                        'tag_type' => 'material',
                        'tag_type_id' => $materialModel->id,
                        'quantity' => $materialData['quantity'] ?? 1,
                    ]);
                }
            }

            // Custom tags as extra tags
            if (! empty($tag['custom_tags'])) {
                foreach ($tag['custom_tags'] as $customTagData) {
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

                    $photoTag->extraTags()->create([
                        'tag_type' => 'custom_tag',
                        'tag_type_id' => $customTagModel->id,
                        'quantity' => $customTagData['quantity'] ?? 1,
                    ]);
                }
            }

            // Brands as extra tags
            if (! empty($tag['brands'])) {
                foreach ($tag['brands'] as $brandData) {
                    $brandModel = BrandList::find($brandData['id']);

                    if (! $brandModel) {
                        throw new \Exception("Brand {$brandData['key']} not found.");
                    }

                    $photoTag->extraTags()->create([
                        'tag_type' => 'brand',
                        'tag_type_id' => $brandModel->id,
                        'quantity' => $brandData['quantity'] ?? 1,
                    ]);
                }
            }

            $photoTags[] = $photoTag;
        }

        return $photoTags;
    }

    /**
     * Resolve category and object from tag input.
     * Accepts either { id: int } or string key for both.
     * Auto-resolves category from object if not explicitly provided.
     */
    protected function resolveTag(array $tag): array
    {
        $category = null;
        $object = null;

        if (isset($tag['category'])) {
            $category = is_array($tag['category']) && isset($tag['category']['id'])
                ? Category::find($tag['category']['id'])
                : Category::where('key', $tag['category'])->first();
        }

        if (isset($tag['object'])) {
            $object = is_array($tag['object']) && isset($tag['object']['id'])
                ? LitterObject::find($tag['object']['id'])
                : LitterObject::where('key', $tag['object'])->first();

            // Auto-resolve category from object if not explicitly provided
            if (! $category && $object) {
                $category = $object->categories()->first();
            }
        }

        return [
            $category,
            $object,
            $tag['quantity'] ?? 1,
            $tag['picked_up'] ?? null,
        ];
    }

    /**
     * Calculate XP from PhotoTag records.
     *
     * XP = sum of all tag quantities + sum of all extra tag quantities
     */
    protected function calculateXp(array $photoTags): int
    {
        $xp = 0;

        foreach ($photoTags as $photoTag) {
            $xp += $photoTag->quantity;

            // Reload extra tags if not already loaded
            if (! $photoTag->relationLoaded('extraTags')) {
                $photoTag->load('extraTags');
            }

            foreach ($photoTag->extraTags as $extraTag) {
                $xp += $extraTag->quantity;
            }
        }

        return $xp;
    }

    /**
     * Set verification status and dispatch event if user is trusted.
     */
    protected function updateVerification(int $userId, Photo $photo): void
    {
        $user = User::find($userId);

        if ($user->verification_required) {
            $photo->verification = 0.1;

            if ($photo->team_id) {
                $team = Team::find($photo->team_id);

                if ($team && $team->isSchool()) {
                    $photo->verified = VerificationStatus::VERIFIED->value;
                }
            }
        } else {
            $photo->verification = 1;
            $photo->verified = VerificationStatus::ADMIN_APPROVED->value;

            event(new TagsVerifiedByAdmin(
                $photo->id,
                $photo->user_id,
                $photo->country_id,
                $photo->state_id,
                $photo->city_id,
                $photo->team_id
            ));
        }

        $photo->save();
    }
}
