<?php

namespace App\Http\Controllers\User\Photos;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class GetMyPhotosController extends Controller
{
    /**
     * Get the users photos to display on a paginated table
     * Optional filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke (Request $request): JsonResponse
    {
        // Todo - validate the request

        $user = Auth::user();

        $query = Photo::where('user_id', $user->id);

        // Filter by date range
        if ($request->filterDateFrom) {
            $query->whereDate('created_at', '>=', $request->filterDateFrom);
        }

        if ($request->filterDateTo) {
            $query->whereDate('created_at', '<=', $request->filterDateTo);
        }

        // Filter by tags: needs improvement to search by category, item, quantity
        // instead of looking at the result_string, we should be looking at the photos relationships
        if ($request->filterTag) {
            $query->where('result_string', 'like', '%' . $request->filterTag . '%');
        }

        if ($request->filterCustomTag) {
            $customTag = $request->filterCustomTag;

            $query->whereHas('customTags', function ($q) use ($customTag) {
                 $q->where('tag', 'like', '%' . $customTag . '%');
            });
        }

        $photos = $query->with('customTags')
            ->orderBy('created_at', 'desc')
            ->paginate($request->paginationAmount);

        return response()->json([
            'success' => true,
            'photos' => $photos
        ]);
    }
}
