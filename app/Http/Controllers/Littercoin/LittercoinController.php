<?php

namespace App\Http\Controllers\Littercoin;

use App\Http\Controllers\Controller;
use App\Models\Littercoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LittercoinController extends Controller
{
    /**
     * Get an array of all of the Littercoin the User is owed
     */
    public function getUsersLittercoin ()
    {
        $userId = Auth::user()->id;

        $littercoin = Littercoin::where('user_id', $userId)->get();

        return [
            'littercoin' => $littercoin
        ];
    }

    /**
     * Sign the transaction
     *
     * Submit the transaction
     *
     * Update the Littercoin as sent
     */
    public function signSubmit (Request $request)
    {
        return [
            'test' => $request->all()
        ];
    }
}
