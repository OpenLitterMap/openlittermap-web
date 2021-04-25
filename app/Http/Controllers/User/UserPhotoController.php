<?php

namespace App\Http\Controllers\User;

use App\Models\Photo;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class UserPhotoController extends Controller
{
    /**
     *
     */
    public function index ()
    {
        return Photo::select('id', 'filename', 'total_litter', 'verified', 'datetime', 'created_at')
            ->where('user_id', auth()->user()->id)
            ->simplePaginate(1);
    }
}
