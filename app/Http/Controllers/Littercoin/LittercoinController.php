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
        $cmd = '(cd ../littercoin/;node info.mjs)'; 
        $response = exec($cmd);

        return [
            $response
        ];

        //$response = exec('env > env.out');
        /*
        if ($response.status == 200) {
            return [
                'littercoinInfo' => $response.data
            ];
        } else {
            return [
                'littercoinInfo' => $response.status
            ];
        }
        */
    }


    /**
     * Check the amount of Littercoin being minted
     * 
     * Sign the transaction
     */
    public function mintTx (Request $request)
    {
        // TODO santize inputs
        $lcQty = $request->input('lcQty');
        $destAddr = $request->input('destAddr');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');

        // TODO - check backend to confirm backend db for littercoin amount
        $strUtxos=implode(",",$utxos);
        
        $cmd = '(cd ../littercoin/; node mint.mjs '.$lcQty.' '.$destAddr.' '.$changeAddr.' '.$strUtxos.')'; 
        $response = exec($cmd);
        //$response = exec('env > env.out');

        return [
            //'test' => $request->all()
            $response
        ];
    }



    /**
     * Submit the transaction
     *
     * Update the Littercoin amount in DB
     */
    public function submitTx (Request $request)
    {
        // TODO santize inputs
        $lcQty = $request->input('lcQty');
        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        // TODO Check littercoin amount for this user

        $cmd = '(cd ../littercoin/; node submit-tx.mjs '.$cborSig.' '.$cborTx.')'; 
        $response = exec($cmd);

        return [
            //'test' => $request->all()
            $response
        ];
    }
}
