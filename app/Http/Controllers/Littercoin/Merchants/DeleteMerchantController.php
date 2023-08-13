<?php

namespace App\Http\Controllers\Littercoin\Merchants;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeleteMerchantController extends Controller
{
    /**
     * Admin can delete a merchant
     */
    public function __invoke (Request $request)
    {
        $user = Auth::user();

        if (!$user->hasPermissionTo('delete merchants'))
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

        $merchant->delete();

        return [
            'success' => true
        ];
    }
}
