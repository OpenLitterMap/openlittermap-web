<?php

namespace App\Http\Controllers\API\Tags;

use App\Models\Photo;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UploadTagsController extends Controller
{
    /**
     * Attach tags to a photo.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store (Request $request): JsonResponse
    {
        $request->validate([
            'photo_id' => 'required|integer|exists:photos,id',
            'tags' => 'required|array'
        ]);

        $photoId = $request->input('photo_id');

        // Check the user making this request owns this photo.
        $user = Auth::user();
        $photo = Photo::find($photoId);

        if (!$photo || $photo->user_id !== $user->id) {
            return response()->json(['msg' => 'Unauthenticated.'], 403);
        }

        $photoTags = [];
        $errors = [];

        foreach ($request['tags'] as $tag)
        {
            $category = isset($tag['category']['id']) ? Category::find($tag['category']['id']) : null;
            $object   = isset($tag['object']['id']) ? LitterObject::find($tag['object']['id']) : null;
            $quantity = $tag['quantity'] ?? 1;
            $pickedUp = $tag['picked_up'] ?? null;

            // Verify that the category and object are associated.
            if ($category && $object && !$category->litterObjects->contains($object)) {
                throw new \Exception("Category '{$category->key}' does not contain object '{$object->key}'.");
            }

            $photoTag = PhotoTag::firstOrCreate([
                'photo_id' => $photoId,
                'category_id' => $category?->id,
                'object_id' => $object?->id,
                'quantity' => $quantity,
                'picked_up' => $pickedUp
            ]);

            if (isset($tag['materials']) && is_array($tag['materials']) && count($tag['materials']) > 0)
            {
                foreach ($tag['materials'] as $materialData)
                {
                    $materialModel = Materials::find($materialData['id']);

                    if (!$materialModel) {
                        throw new \Exception("Material with ID {$materialData['id']} not found.");
                    }

                    // Use syncWithoutDetaching to prevent duplicate entries.
                    $photoTag->materials()->syncWithoutDetaching($materialModel->id);
                }

                $photoTag->load('materials');
            }

            // CustomTags
            // Brands

//            if (isset($tag['brand'])) {
//                $brand = BrandList::where('key', $tag['brand'])->first();
//            }

            $photoTags[] = $photoTag;
        }

        return response()->json([
            'photoTags' => $photoTags,
        ]);
    }
}
