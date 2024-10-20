<?php

namespace App\Http\Controllers\History;

use App\Models\Photo;
use App\Models\CustomTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class GetPaginatedHistoryController extends Controller
{
    /**
     * Get a paginated response of all available verified data
     *
     * Todo - validate the request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke (Request $request): JsonResponse
    {
        // Default to page 1 if not provided
        $currentPage = $request->input('loadPage', 1);

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $mobileAppUser = Auth::guard('api')->user();

        $query = Photo::query()

            ->when($mobileAppUser, function ($q) use ($mobileAppUser) {
                $q->where('user_id', $mobileAppUser->id);
            }, function ($q) {
                $q->where('verified', '>=', 2);
            })

            ->when($request->filterCountry !== 'all', function ($q) use ($request) {
                $q->where('country_id', $request->filterCountry);
            })

            ->when($request->filterDateFrom, function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->filterDateFrom);
            })

            ->when($request->filterDateTo, function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->filterDateTo);
            })

            // Filter by tags: needs improvement to search by category, item, quantity
            // instead of looking at the result_string, we should be looking at the photos relationships
            ->when($request->filterTag, function ($q) use ($request) {
                $q->where('result_string', 'like', '%' . $request->filterTag . '%');
            })

            ->when($request->filterCustomTag, function ($q) use ($request) {
                $q->whereHas('customTags', function ($q) use ($request) {
                    $q->where('tag', 'like', '%' . $request->filterCustomTag . '%');
                });
            });

        $notInclude = CustomTag::notIncludeTags();
        $query->whereDoesntHave('customTags', function ($q) use ($notInclude) {
            $q->whereIn('tag', $notInclude);
        });

        $photos = $query->with(['customTags' => function ($query) use ($notInclude) {
            $query->whereNotIn('tag', $notInclude);
        }])
        ->orderBy('id', 'desc')
        ->paginate($request->paginationAmount);

        return response()->json([
            'success' => true,
            'photos' => $photos
        ]);
    }
}
