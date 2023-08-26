<?php

namespace App\Http\Controllers\Littercoin\Merchants;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetNextMerchantToApproveController extends Controller
{
    /**
     * Admin only
     */
    public function __invoke ()
    {
        $user = Auth::user();

        if (!$user->hasPermissionTo('approve merchants'))
        {
            return [
                'success' => false,
                'msg' => 'unauthorised'
            ];
        }

        $merchant = Merchant::whereNull('approved')->first();

        if (!$merchant)
        {
            return [
                'success' => false,
                'msg' => 'none found'
            ];
        }

        return [
            'success' => true,
            'merchant' => $merchant
        ];
    }
}
