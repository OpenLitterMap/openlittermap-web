<?php

namespace App\Http\Controllers\GlobalMap\Search;

use App\Models\CustomTag;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class FindCustomTagsController extends Controller
{
    /**
     * Search custom tags by starting at string
     */
    public function __invoke (): JsonResponse
    {
        $search = request('search');

        $notInclude = CustomTag::notIncludeTags();

        $tags = CustomTag::where('tag', 'like', $search . '%')
            ->whereNotIn('tag', $notInclude)
            ->select('tag', DB::raw('count(*) as total'))
            ->groupBy('tag')
            ->orderBy('total', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'tags' => $tags
        ]);
    }
}
