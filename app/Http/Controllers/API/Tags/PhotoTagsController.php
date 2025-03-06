<?php

namespace App\Http\Controllers\API\Tags;

use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\CustomTagNew;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PhotoTagsRequest;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PhotoTagsController extends Controller
{
    private UpdateLeaderboardsForLocationAction $updateLeaderboards;

    public function __construct(UpdateLeaderboardsForLocationAction $updateLeaderboards)
    {
        $this->updateLeaderboards = $updateLeaderboards;
    }

    /**
     * Attach tags to a photo.
     *
     * @param PhotoTagsRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store (PhotoTagsRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $userId = Auth::id();

        $photoId = $validatedData['photo_id'];

        $photoTags = $this->addTagsToPhoto($userId, $validatedData['tags'], $photoId);

        $this->updateLeaderboardsAndXP($userId, $photoId, $photoTags);

        return response()->json([
            'success' => true,
            'photoTags' => $photoTags,
        ]);
    }

    /**
     * @param array $tags
     * @param int $photoId
     * @return array
     * @throws \Exception
     */
    protected function addTagsToPhoto(int $userId, array $tags, int $photoId): array
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

            $photoTag = PhotoTag::firstOrCreate([
                'photo_id' => $photoId,
                'category_id' => $category?->id,
                'litter_object_id' => $object?->id,
                'quantity' => $quantity,
                'picked_up' => $pickedUp
            ]);

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

                    // Validate against a whitelist pattern (only letters, numbers, spaces, hyphens, and underscores).
                    if (!preg_match('/^[\w\s-]+$/', $cleanTag)) {
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

            // Custom tag is the primary tag
            if (isset($tag['custom']) && $tag['custom']) {
                $customTagModel = CustomTagNew::firstOrCreate(['key' => $tag['custom']]);

                // if new -> send to admin for approval
                if ($customTagModel->wasRecentlyCreated) {
                    $customTagModel->created_by = $userId;
                    $customTagModel->save();
                }

                $photoTag->extraTags()->create([
                    'tag_type' => 'custom_tag',
                    'tag_type_id' => $customTagModel->id,
                    'quantity' => $tag['quantity'] ?? 1,
                ]);
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

    protected function updateLeaderboardsAndXP(int $userId, int $photoId, array $photoTags): void
    {
        $photo = Photo::find($photoId);

        $xp = $this->calculateXP($photoTags);

        $this->updateLeaderboards->run($photo, $userId, $xp);
    }

    protected function calculateXP(array $tags) {
        $totalXP = 0;

        foreach ($tags as $tag) {
            $totalXP += $tag['quantity'];

            if (isset($tag['extraTags']) && is_array($tag['extraTags'])) {
                foreach ($tag['extraTags'] as $extra) {
                    if (isset($extra['selected']) && $extra['selected']) {
                        $totalXP++;
                    }
                }
            }
        }

        return $totalXP;
    }
}
