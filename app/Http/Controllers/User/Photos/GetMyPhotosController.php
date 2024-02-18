<?php

namespace App\Http\Controllers\User\Photos;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetMyPhotosController extends Controller
{
    public function __invoke ()
    {
        $user = Auth::user();

        $photos = Photo::where('user_id', $user->id)
            ->orderBy('created_at', 'desc') // Corrected line
            ->paginate(25);

        return response()->json([
            'success' => true,
            'photos' => $photos
        ]);
    }
}
