<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;

/**
 * @deprecated No route exists. Orphaned Ethereum wallet code.
 */
class BlockchainController extends Controller
{

	public function __construct() {
	  	return $this->middleware('auth');
	  	parent::__construct();
	}

    public function updateWallet(Request $request) {
    	$wallet = $request->wallet;
    	$user = Auth::user();
    	$user->eth_wallet = $wallet;
    	$user->save();
    }

    public function removeWallet(Request $request) {
        $wallet = $request->wallet;
        $user = Auth::user();
        $user->eth_wallet = null;
        $user->save();
    }
}
