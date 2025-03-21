<?php

namespace App\Actions\Tags;

use App\Actions\Badges\CheckLocationTypeAward;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use Illuminate\Support\Facades\Auth;

class AddTagsToPhotoActionNew
{
    private UpdateLeaderboardsForLocationAction $updateLeaderboards;
    private CheckLocationTypeAward $checkLocationTypeAward;

    public function __construct(
        UpdateLeaderboardsForLocationAction $updateLeaderboards,
        CheckLocationTypeAward $checkLocationTypeAward
    )
    {
        $this->updateLeaderboards = $updateLeaderboards;
        $this->checkLocationTypeAward = $checkLocationTypeAward;
    }

    /**
     * @throws \Exception
     */
    public function run (int $userId, int $photoId, array $tags): array
    {
        $photoTags = $this->addTagsToPhoto($userId, $photoId, $tags);

        $this->updateLeaderboards->updateLeaderboardsAndRewardXP($userId, $photoId, $photoTags);

        $this->updateVerification($photoId);

        return $photoTags;
    }

    protected function addTagsToPhoto (int $userId, int $photoId, array $tags): array
    {
        $photoTags = [];

        foreach ($tags as $tag)
        {
            $category = isset($tag['category']['id']) ? Category::find($tag['category']['id']) : null;
            $object = isset($tag['object']['id']) ? LitterObject::find($tag['object']['id']) : null;
            $quantity = $tag['quantity'] ?? 1;
            $pickedUp = $tag['picked_up'] ?? null;

            // Verify that the category and object are associated.
            if ($category && $object && !$category->litterObjects->contains($object)) {
                throw new \Exception("Category '{$category->key}' does not contain object '{$object->key}'.");
            }

            // The parent-level tag on a PhotoTag can either by Category.id + Object.id
            // or custom_tag_primary_id
            $photoTag = PhotoTag::firstOrCreate([
                'photo_id' => $photoId,
                'category_id' => $category?->id,
                'litter_object_id' => $object?->id,
                'quantity' => $quantity,
                'picked_up' => $pickedUp
            ]);

            // Check for verified user
            if ($object->key === 'bagsLitter' && $pickedUp) {
                $this->checkLocationTypeAward->checkLandUseAward($userId, $photoTag);
            }

            // If custom_tag the primary tag
            if (isset($tag['custom']) && $tag['custom']) {
                $customTagModel = CustomTagNew::firstOrCreate(['key' => $tag['custom']]);

                // if new -> send to admin for approval
                if ($customTagModel->wasRecentlyCreated) {
                    $customTagModel->created_by = $userId;
                    $customTagModel->save();
                }

                $photoTag->custom_tag_primary_id = $customTagModel->id;
                $photoTag->save();
            }

            if (isset($tag['materials']) && is_array($tag['materials']) && count($tag['materials']) > 0) {
                foreach ($tag['materials'] as $materialData) {
                    $materialModel = Materials::find($materialData['id']);

                    if (!$materialModel) {
                        throw new \Exception("Material with ID {$materialData['id']} not found.");
                    }

                    $photoTag->extraTags()->create([
                        'tag_type' => 'material',
                        'tag_type_id' => $materialModel->id,
                        'quantity' => $materialData['quantity'] ?? 1,
                    ]);
                }
            }

            // CustomTags attached to an object
            if (isset($tag['custom_tags']) && is_array($tag['custom_tags']) && count($tag['custom_tags'])) {
                foreach ($tag['custom_tags'] as $customTagData) {

                    // Clean for vulnerabilities
                    $cleanTag = strip_tags($customTagData);
                    $cleanTag = trim($cleanTag);

                    // Validate against a whitelist pattern (only letters, numbers, spaces, hyphens, colons, and underscores).
                    if (!preg_match('/^[\w\s:-]+$/', $cleanTag)) {
                        throw new \Exception('Invalid custom tag.');
                    }

                    $customTagModel = CustomTagNew::firstOrCreate(['key' => $cleanTag]);

                    // if new -> send to admin for approval
                    if ($customTagModel->wasRecentlyCreated) {
                        // $customTagModel->sendForApproval();
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

            // Brands
            if (isset($tag['brands']) && is_array($tag['brands']) && count($tag['brands'])) {
                foreach ($tag['brands'] as $brandData) {
                    $brandModel = BrandList::find($brandData['id']);

                    if (!$brandModel) {
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

    protected function updateVerification(int $photoId): void
    {
        $user = Auth::user();
        $photo = Photo::find($photoId);

        if (!$user->is_trusted)
        {
            // Bring the photo to an initial state of verification
            // 0 for testing, 0.1 for production
            // This value can be +/- 0.1 when users vote True or False
            // When verification reaches 1.0, it verified increases from 0 to 1
            $photo->verification = 0.1;
        }
        else
        {
            // the user is trusted. Dispatch event to update OLM.
            $photo->verification = 1;
            $photo->verified = 2;
            event (new TagsVerifiedByAdmin($photo->id));
        }

        $photo->save();
    }
}
