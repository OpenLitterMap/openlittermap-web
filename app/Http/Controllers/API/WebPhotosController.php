<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\API\APIPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class WebPhotosController extends Controller
{
    /**
     *
     */
    public function index ()
    {
        $user = Auth::guard('api')->user();

        $query = APIPhoto::where([
            'user_id' => $user->id,
            'verification' => 0
        ]);

        $photo = null;

        $count = $query->count();

        if ($count > 0) $photo = $query->select('id', 'filename')->first();

        return [
            'count' => $count,
            'photo' => $photo
        ];
    }
}
