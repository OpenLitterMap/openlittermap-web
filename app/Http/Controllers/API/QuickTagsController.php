<?php

namespace App\Http\Controllers\API;

use App\Actions\QuickTags\SyncQuickTagsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncQuickTagsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickTagsController extends Controller
{
    /**
     * GET /api/user/quick-tags
     */
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->quickTags()->get();

        return response()->json([
            'success' => true,
            'tags' => $tags,
        ]);
    }

    /**
     * PUT /api/user/quick-tags
     */
    public function update(SyncQuickTagsRequest $request, SyncQuickTagsAction $action): JsonResponse
    {
        $tags = $action->run(
            $request->user(),
            $request->validated('tags')
        );

        return response()->json([
            'success' => true,
            'tags' => $tags,
        ]);
    }
}
