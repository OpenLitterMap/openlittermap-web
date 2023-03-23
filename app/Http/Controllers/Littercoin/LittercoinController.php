<?php

namespace App\Http\Controllers\Littercoin;

use App\Http\Controllers\Controller;
use App\Models\Littercoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User\User;

class LittercoinController extends Controller {

    /**
     * Apply middleware to all of these routes
     */
    public function __construct () {
        $this->middleware('auth');
    }

    /**
     * Get an array of all of the Littercoin the User is owed
     */
    public function getUsersLittercoin () {

        $userId = Auth::user()->id;

        $littercoinEarned = Littercoin::where('user_id', $userId)->count();
        $littercoinDue = Littercoin::where('user_id', $userId)->whereNull('transaction_id')->count();
        return [
            'littercoinEarned' => $littercoinEarned,
            'littercoinDue' => $littercoinDue
        ];
    }

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

    /**
     * Build the littercoin mint transaction.
     */
    public function mintTx (Request $request) {

        $destAddr = $request->input('destAddr');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        $userId = Auth::user()->id;
        $littercoinEarned = Littercoin::where('user_id', $userId)->count();
        $littercoinDue = Littercoin::where('user_id', $userId)->whereNull('transaction_id')->count();

        if ($littercoinDue > 0) {
            $cmd = '(cd ../littercoin/;node ./run/build-lc-mint-tx.mjs '.$littercoinDue.' '.escapeshellarg($destAddr).' '.escapeshellarg($changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log'; 
            $response = exec($cmd);
    
            return [
                $response
            ];   
        } else {
            return [
                '{"status": "406", "msg": "Littercoin due must be greater than zero"}'
            ];
        }
    }

    /**
     * Submit the littercoin mint transaction 
     */
    public function submitMintTx (Request $request) {

        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg($cborSig).' '.escapeshellarg($cborTx).') 2>> ../storage/logs/littercoin.log'; 
        $response = exec($cmd);
        try {
            $responseJSON = json_decode($response, false);

            if ($responseJSON->status == 200) {

                // Update the amount of littercoin paid to user in the DB
                $userId = Auth::user()->id;
                $littercoin = Littercoin::where('user_id', $userId)
                                        ->whereNull('transaction_id')
                                        ->update(['transaction_id' => $responseJSON->txId,
                                                  'timestamp' => $responseJSON->date]);

                return [
                    $response
                ];
            } else {
                return [
                    $response
                ];
            }
        } catch (Exception $e) {
            return [
                '{"status": "400", "msg": "Transaction could not be submitted"}'
            ];
        }
    }

    /**
     * Build the littercoin burn transaction.
     */
    public function burnTx (Request $request) {

        $lcQty = $request->input('lcQty');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        if ($lcQty > 0) {
            $cmd = '(cd ../littercoin/;node ./run/build-lc-burn-tx.mjs '.escapeshellarg($lcQty).' '.escapeshellarg($changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log'; 
            $response = exec($cmd);
            try {  
                $responseJSON = json_decode($response, false);

                if ($responseJSON->status == 200) {
                    return [
                        $response
                    ];
                } else if ($responseJSON->status == 501) {
                    return [
                        '{"status": "401", "msg": "Insufficient Littercoin In Wallet For Burn"}'
                    ];
                } else if ($responseJSON->status == 502) {
                    return [
                        '{"status": "402", "msg": "There must be at least and only one Merchant Token in the Wallet"}'
                    ];
                } else if ($responseJSON->status == 503) {
                    return [
                        '{"status": "403", "msg": "Ada Withdraw amount is less than the minimum 2 Ada"}'
                    ];
                } else if ($responseJSON->status == 504) {
                    return [
                        '{"status": "404", "msg": "Insufficient funds in Littercoin contract"}'
                    ];
                } else if ($responseJSON->status == 505) {
                    return [
                        '{"status": "405", "msg": "No valid merchant token found in the wallet"}'
                    ];
                }else {
                    return [
                        $response
                    ];   
                } 
            } catch (Exception $e) {
                return [
                    '{"status": "400", "msg": "Transaction could not be submitted"}'
                ];
            }
        } else {
            return [
                '{"status": "405", "msg": "Littercoin amount must be greater than zero"}'
            ];
        }
    }

    /**
     * Submit the littercoin burn transaction 
     */
    public function submitBurnTx (Request $request) {

        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg($cborSig).' '.escapeshellarg($cborTx).') 2>> ../storage/logs/littercoin.log'; 
        $response = exec($cmd);
 
        return [
            $response
        ];  
    }

    /**
     * Build the merchant token mint transaction.
     */
    public function merchTx (Request $request) {

        $destAddr = $request->input('destAddr');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        if ((Auth::user() && ((Auth::user()->hasRole('admin') 
                       || Auth::user()->hasRole('superadmin'))))) {

            $cmd = '(cd ../littercoin/;node ./run/build-merch-mint-tx.mjs '.escapeshellarg($destAddr).' '.escapeshellarg($changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log'; 
            $response = exec($cmd);
    
            return [
                $response
            ]; 
              
        } else {
            return [
                '{"status": "407", "msg": "User must be an admin"}'
            ];
        }
    }

    /**
     * Submit the merchant mint transaction.
     */
    public function submitMerchTx (Request $request) {

        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        // Check that the user is an admin
        if ((Auth::user() && ((Auth::user()->hasRole('admin') 
                       || Auth::user()->hasRole('superadmin'))))) {

            $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg($cborSig).' '.escapeshellarg($cborTx).') 2>> ../storage/logs/littercoin.log'; 
            $response = exec($cmd);

            try {
                $responseJSON = json_decode($response, false);

                return [
                    $response
                ];
            } catch (Exception $e) {
                return [
                    '{"status": "400", "msg": "Transaction could not be submitted"}'
                ];
            }
        } else {
            return [
                '{"status": "407", "msg": "User must be an admin"}'
            ];
        }
    }

    /**
     * Build the add Ada transaction.
     */
    public function addAdaTx (Request $request) {
        
        $adaQty = $request->input('adaQty');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);
        
        if ($adaQty >= 2) {
            $cmd = '(cd ../littercoin/;node ./run/build-add-ada-tx.mjs '.escapeshellarg($adaQty).' '.escapeshellarg($changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log'; 
            $response = exec($cmd);

            try {
                $responseJSON = json_decode($response, false);

                if ($responseJSON->status == 200) {
                    return [
                        $response
                    ];
                } else if ($responseJSON->status == 501) {
                    return [
                        '{"status": "408", "msg": "Not enough Ada in Wallet"}'
                    ];
                } else {
                    return [
                        $response
                    ];
                }
            } catch (Exception $e) {
                return [
                    '{"status": "400", "msg": "Transaction could not be submitted"}'
                ];
            }
        } else {
            return [
                '{"status": "409", "msg": "Minimum 2 Ada donation is required"}'
            ];
        }
    }

    /**
     * Submit the Add Ada transaction
     */
    public function submitAddAdaTx (Request $request) {
        
        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg($cborSig).' '.escapeshellarg($cborTx).') 2>> ../storage/logs/littercoin.log'; 
        $response = exec($cmd);

        return [
            $response
        ];
    }
}
