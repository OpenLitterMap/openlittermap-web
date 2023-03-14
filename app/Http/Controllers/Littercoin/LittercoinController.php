<?php

namespace App\Http\Controllers\Littercoin;

use App\Http\Controllers\Controller;
use App\Models\Littercoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User\User;

class LittercoinController extends Controller
{
    /**
     * Apply middleware to all of these routes
     */
    public function __construct ()
    {
        $this->middleware('auth');
    }

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
     * Get the amount Ada and littercoin in circulation and
     * the littercoin script address and UTXO with the thread
     * token in it.
     */
    public function getLittercoinInfo ()
    {
        $cmd = '(cd ../littercoin/;node get-lc-info.mjs) 2>> ../storage/logs/littercoin.log'; 
        $response = exec($cmd);

        return [
            $response
        ];
    }


    /**
     * Create the littercoin mint transaction.
     */
    public function mintTx (Request $request)
    {
        // TODO santize inputs
        $destAddr = $request->input('destAddr');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        $userId = Auth::user()->id;
        $littercoinPaid = Auth::user()->littercoin_paid;
        $littercoinEarned = Littercoin::where('user_id', $userId)->count();
        $littercoinDue = $littercoinEarned - $littercoinPaid;

        if ($littercoinDue > 0) {
            $cmd = '(cd ../littercoin/;node create-mint-tx.mjs '.$littercoinDue.' '.$destAddr.' '.$changeAddr.' '.$strUtxos.') 2>> ../storage/logs/littercoin.log'; 
            $response = exec($cmd);
    
            return [
                $response
            ];   
        } else {
            return [
                '{"status": "400", "msg": "Littercoin due must be greater than zero"}'
            ];
        }
    }

    /**
     * Submit the littercoin mint transaction which includes signing
     * the tx with a private key.
     */
    public function submitMintTx (Request $request)
    {
        // TODO santize inputs
        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        // Check littercoin amount for this transaction
        $user = Auth::user();
        $userId = Auth::user()->id;
        $littercoinPaid = Auth::user()->littercoin_paid;
        $littercoinEarned = Littercoin::where('user_id', $userId)->count();
        $littercoinDue = $littercoinEarned - $littercoinPaid;

        $cmd = '(cd ../littercoin/;node submit-mint-tx.mjs '.$littercoinDue.' '.$cborSig.' '.$cborTx.') 2>> ../storage/logs/littercoin.log'; 
        $response = exec($cmd);
        $responseJSON = json_decode($response, false);

        if ($responseJSON->status == 200) {

            // Update the amount of littercoin paid to user in the DB

            // Commented out for testing purposes
            //$user->littercoin_paid = $littercoinPaid + $littercoinDue;
            //$user->save();
      
            return [
                $response
            ];
        } else {
            return [
                $response
            ];
        }
    }
}
