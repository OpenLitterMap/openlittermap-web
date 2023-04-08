<?php

namespace App\Http\Controllers\Littercoin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PublicLittercoinController extends Controller
{
    /**
     * Get the amount Ada and littercoin in circulation and
     * the littercoin script address and UTXO with the thread
     * token in it.
     */
    public function getLittercoinInfo () {

        $cmd = '(cd ../littercoin/;node ./run/get-lc-info.mjs) 2>> ../storage/logs/littercoin.log';
        $response = exec($cmd);

        return [
            $response
        ];
    }
}
