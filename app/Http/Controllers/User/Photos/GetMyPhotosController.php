<?php

namespace App\Http\Controllers\User\Photos;

use App\Models\Photo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class GetMyPhotosController extends Controller
{
    public function __invoke (Request $request)
    {
        // Todo - validate the request
        \Log::info($request->all());

        $user = Auth::user();

        $query = Photo::where('user_id', $user->id);

        // Filter by date range
        if ($request->filterDateFrom) {
            $query->whereDate('created_at', '>=', $request->filterDateFrom);
        }

        if ($request->filterDateTo) {
            $query->whereDate('created_at', '<=', $request->filterDateTo);
        }

        // Filter by tags: needs improvement
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
