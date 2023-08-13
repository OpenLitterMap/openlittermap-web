<?php

namespace App\Http\Controllers\Littercoin\Merchants;

use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CreateMerchantController extends Controller
{
    /**
     * Create a Littercoin Merchant
     *
     * - Requires create merchant permission
     */
    public function __invoke (Request $request)
    {
        $request->validate([
            'name' => 'required',
            'lat' => 'required',
            'lon' => 'required',
            'website' => 'required'
        ]);

        $user = Auth::user();

        if (!$user->hasPermissionTo('create merchants'))
        {
            return [
                'success' => false,
                'msg' => 'unauthorised'
            ];
        }

        $merchant = Merchant::firstOrCreate([
            'name' => $request->name,
            'lat' => $request->lat,
            'lon' => $request->lon,
            'email' => $request->email,
            'website' => $request->website,
            'about' => $request->about,
            'address' => '',
            'created_by' => $user->id
        ]);

        return [
            'success' => true,
            'merchant' => $merchant
        ];
    }
}
