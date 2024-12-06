<?php

namespace App\Http\Controllers\API\Tags;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class GetTagsController extends Controller
{
    /**
     * Get the Tags in their nested structure
     */
    public function __invoke (): JsonResponse
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
}
