<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;

class BecomeAMerchantController extends Controller
{
    public function __invoke (Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'website' => 'nullable|url|max:255',
            'message' => 'nullable|string',
        ]);

        try
        {
            $merchant = Merchant::create([
                'name' => $request->name,
                'address' => $request->address,
                'email' => $request->email,
                'phone' => $request->phone,
                'website' => $request->website,
                'message' => $request->message,
            ]);
        }
        catch (\Exception $exception)
        {
            \Log::info(['BecomeAMerchantController', $exception->getMessage()]);

            return [
                'success' => false,
                'msg' => 'problem'
            ];
        }

        return [
            'success' => true,
        ];
    }
}
