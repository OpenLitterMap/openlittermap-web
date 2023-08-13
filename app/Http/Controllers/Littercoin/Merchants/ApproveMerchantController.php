<?php

namespace App\Http\Controllers\Littercoin\Merchants;

use App\Models\Merchant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApproveMerchantController extends Controller
{
    /**
     * Admin can approve a merchant
     */
    public function __invoke (Request $request)
    {
        $user = Auth::user();

        if (!$user->hasPermissionTo('approve merchants'))
        {
            return [
                'success' => false,
                'msg' => 'unauthorized'
            ];
        }

        $merchant = Merchant::find($request->merchantId);

        if (!$merchant)
        {
            return [
                'success' => false,
                'msg' => 'does not exist'
            ];
        }

        $merchant->approved = now(); // ->toDateTimeString()
        $merchant->approved_by = $user->id;
        $merchant->save();

        return [
            'success' => true
        ];
    }
}
