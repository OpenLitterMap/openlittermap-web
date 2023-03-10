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
     * Get an array of the Ada and littercoin in circulation
     */
    public function getLittercoinInfo ()
    {
        //$userId = Auth::user()->id;

        //$littercoin = Littercoin::where('user_id', $userId)->get();
        $cmd = '(cd ../littercoin/;node info.mjs)'; 
        $response = exec($cmd);

        return [
            'littercoinInfo' => $response
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
        // TODO santize inputs
        $destAddr = $request->input('destAddr');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);
        
        $cmd = '(cd ../littercoin/; node mint.mjs '.$destAddr.' '.$changeAddr.' '.$strUtxos.')'; 
        //$response = exec($cmd);
        $response = exec('env > env.out');

        return [
            //'test' => $request->all()
            'test' => $response
        ];
    }
}
