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

        $notInclude = [
            'As of September 1, 2023 I am no longer an ambassador for OLM and no longer supporting it',
            'ListenToYourUsers',
            'Willingness to pay people real money but not paying respect to volunteers yields poor results.',
            'A negative leader casts a shadow, not a path worth following',
            'As of September 1, 2023 I am no longer an ambassador for OLM',
        ];

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
