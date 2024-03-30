<?php

namespace App\Http\Controllers\History;

use App\Models\CustomTag;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\Paginator;

class GetPaginatedHistoryController extends Controller
{
    /**
     * Get a paginated response of all available verified data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke (Request $request): JsonResponse
    {
        // Todo - validate the request

        $currentPage = $request->input('loadPage', 1); // Default to page 1 if not provided

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $countryId = ($request->filterCountry === 'all')
            ? null
            : $request->filterCountry;

        $query = Photo::query()
            ->where('verified', 2);

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

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

        $notInclude = CustomTag::notIncludeTags();
        $query->whereHas('customTags', function ($q) use ($notInclude) {
            $q->whereNotIn('tag', $notInclude);
        });

        $photos = $query->with('customTags')
            ->orderBy('id', 'desc')
            ->paginate($request->paginationAmount);

        return response()->json([
            'success' => true,
            'photos' => $photos
        ]);
    }
}
