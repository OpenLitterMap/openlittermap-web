<?php

namespace App\Http\Controllers\API\Tags;

use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UploadTagsController extends Controller
{
    /**
     * Attach tags to a photo.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store (Request $request): JsonResponse
    {
        $request->validate([
            'photo_id' => [
                'required',
                'integer',
                Rule::exists('photos', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id());
                })
            ],
            'tags' => 'required|array',
        ]);

        $photoTags = $this->addTagsToPhoto($request['tags'], $request->input('photo_id'));

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
    public function addTagsToPhoto(array $tags, int $photoId): array
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

            // CustomTags
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
}
