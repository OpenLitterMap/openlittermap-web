<?php

namespace App\Http\Controllers\API\Tags;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class GetTagsController extends Controller
{
    /**
     * Get the Tags in their nested structure
     */
    public function getAllTags (): JsonResponse
    {
        $categories = Category::with([
            'litterObjects' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            }
        ])->orderBy('key', 'asc')->get();

        return response()->json([
            'categories' => $categories
        ]);
    }

    /**
     * Get tags for a specific category
     */
    public function getTagsForCategory (Category $category): JsonResponse
    {
        $category = $category->load([
            'litterObjects' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes' => function ($q) use ($category) {
                $q->where('category_id', $category->id)
                  ->orderBy('key', 'asc');
            },
            'litterObjects.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            }
        ]);

        return response()->json([
            'category' => $category
        ]);
    }
}
