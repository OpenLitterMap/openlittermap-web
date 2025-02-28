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

        $photoId = $request['photo_id'];

        // Check the user making this request owns this photo.
        $userId = Auth::user()->id;
        $photo = Photo::find($photoId);

        if ($photo->user_id !== $userId) {
            return response()->json([
                'msg' => 'Unauthenticated.'
            ], 403);
        }

        $photoTags = [];
        $errors = [];

        foreach ($request['tags'] as $tag)
        {
            $passed = true;

            $category = null;
            $object = null;
            $brand = null;
            $quantity = null;
            $pickedUp = null;

            if (isset($tag['category']['id'])) {
                $category = Category::find($tag['category']['id'])->first();
            }

            if (isset($tag['object']['id'])) {
                $object = LitterObject::find($tag['object']['id'])->first();
            }

            // check if the object->categories is of type category
            if ($category && $object) {
                if (!$category->litterObjects->contains($object)) {

                    $passed = false;

                    $errors[] = [
                        'msg' => 'Category does not contain object',
                        'category' => $tag['category'],
                        'object' => $tag['object']
                    ];
                }
            }

            // Extra Tags


            $quantity = $tag['quantity'] ?? 1;

            if (isset($tag['picked_up'])) {
                $pickedUp = $tag['picked_up'];
            }

            if (!$passed) {
                continue;
            }

            $photoTag = PhotoTag::firstOrCreate([
                'photo_id' => $photoId,
                'category_id' => $category?->id,
                'object_id' => $object?->id,
                'brandlist_id' => $brand?->id,
                'quantity' => $quantity,
                'picked_up' => $pickedUp
            ]);

            if (isset($tag['materials']) && count($tag['materials']) > 0)
            {
                foreach ($tag['materials'] as $material)
                {
                    $materialId = Materials::find($material['id'])->first()->id ?? null;

                    if ($materialId) {
                        $photoTag->materials()->attach($materialId);
                    }
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
            'errors' => $errors
        ]);
    }
}
