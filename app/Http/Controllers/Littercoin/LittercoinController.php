<?php

namespace App\Http\Controllers\Littercoin;

use App\Helpers\Twitter;
use App\Http\Controllers\Controller;
use App\Models\Littercoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LittercoinController extends Controller {

    /**
     * Apply middleware to all of these routes
     */
    public function __construct () {
        $this->middleware('auth');
    }

    /**
     * Get an array of all the Littercoin the User is owed
     */
    public function getUsersLittercoin ()
    {
        $userId = Auth::user()->id;

        $littercoinEarned = Littercoin::where('user_id', $userId)->count();
        $littercoinDue = Littercoin::where('user_id', $userId)->whereNull('transaction_id')->count();

        return [
            'littercoinEarned' => $littercoinEarned,
            'littercoinDue' => $littercoinDue
        ];
    }

    /**
     * Get the amount Ada, littercoins and merchant tokens in the connected
     * wallet.
     */
    public function getWalletInfo (Request $request)
    {
        $request->validate([
            'balanceCborHex' => 'required|alpha_num|max:16384',
            'utxos' => 'required|array|max:256',
            'utxos.*' => 'required|alpha_num|max:8192'
        ]);

        $balanceCborHex = $request->input('balanceCborHex');
        $utxos = $request->input('utxos');
        $strUtxos = implode(",",$utxos);

        $cmd = '(cd ../littercoin/;node ./run/get-wallet-info.mjs '.escapeshellarg((string) $balanceCborHex).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log';
        $response = exec($cmd);

        return [
            $response
        ];
    }

    /**
     * Build the littercoin mint transaction.
     */
    public function mintTx (Request $request)
    {
        $request->validate([
            'destAddr' => 'required|alpha_dash|max:110',
            'changeAddr' => 'required|alpha_num|max:256',
            'utxos' => 'required|array|max:256',
            'utxos.*' => 'required|alpha_num|max:8192'
        ]);

        $destAddr = $request->input('destAddr');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        $userId = Auth::user()->id;
        Littercoin::where('user_id', $userId)->count();
        $littercoinDue = Littercoin::where('user_id', $userId)->whereNull('transaction_id')->count();

        if ($littercoinDue > 0)
        {
            $cmd = '(cd ../littercoin/;node ./run/build-lc-mint-tx.mjs '.$littercoinDue.' '.escapeshellarg((string) $destAddr).' '.escapeshellarg((string) $changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log';
            $response = exec($cmd);

            return [
                $response
            ];
        }
        else {
            return [
                '{"status": "406", "msg": "Littercoin due must be greater than zero"}'
            ];
        }
    }

    /**
     * Submit the littercoin mint transaction
     */
    public function submitMintTx (Request $request)
    {
        $request->validate([
            'cborSig' => 'required|alpha_num|max:16384',
            'cborTx' => 'required|alpha_num|max:16384'
        ]);

        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg((string) $cborSig).' '.escapeshellarg((string) $cborTx).') 2>> ../storage/logs/littercoin.log';
        $response = exec($cmd);

        try
        {
            $responseJSON = json_decode($response, false);

            if ($responseJSON->status == 200)
            {
                // Update the amount of littercoin paid to user in the DB
                $userId = Auth::user()->id;
                $littercoin = Littercoin::where('user_id', $userId)
                                        ->whereNull('transaction_id')
                                        ->update(['transaction_id' => $responseJSON->txId,
                                                  'timestamp' => $responseJSON->date]);

                $littercoinCount = Littercoin::where('user_id', $userId)
                                             ->where('transaction_id', $responseJSON->txId)
                                             ->count();

                Twitter::sendTweet("$littercoinCount #Littercoin have been minted.");

                return [
                    $response
                ];
            } else {
                return [
                    $response
                ];
            }
        } catch (Exception $exception) {
            return [
                '{"status": "400", "msg": "Transaction could not be submitted"}'
            ];
        }
    }

    /**
     * Build the littercoin burn transaction.
     */
    public function burnTx (Request $request)
    {
        $request->validate([
            'lcQty' => 'required|int|max:1000',
            'changeAddr' => 'required|alpha_num|max:256',
            'utxos' => 'required|array|max:256',
            'utxos.*' => 'required|alpha_num|max:8192'
        ]);

        $lcQty = $request->input('lcQty');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        if ($lcQty > 0)
        {
            $cmd = '(cd ../littercoin/;node ./run/build-lc-burn-tx.mjs '.escapeshellarg((string) $lcQty).' '.escapeshellarg((string) $changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log';
            $response = exec($cmd);

            try
            {
                $responseJSON = json_decode($response, false);

                if ($responseJSON->status == 200) {
                    return [
                        $response
                    ];
                } elseif ($responseJSON->status == 501) {
                    return [
                        '{"status": "401", "msg": "Insufficient Littercoin In Wallet For Burn"}'
                    ];
                } elseif ($responseJSON->status == 502) {
                    return [
                        '{"status": "402", "msg": "There must be at least and only one Merchant Token in the Wallet"}'
                    ];
                } elseif ($responseJSON->status == 503) {
                    return [
                        '{"status": "403", "msg": "Ada Withdraw amount is less than the minimum 2 Ada"}'
                    ];
                } elseif ($responseJSON->status == 504) {
                    return [
                        '{"status": "404", "msg": "Insufficient funds in Littercoin contract"}'
                    ];
                } elseif ($responseJSON->status == 505) {
                    return [
                        '{"status": "405", "msg": "No valid merchant token found in the wallet"}'
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
        else {
            return [
                '{"status": "405", "msg": "Littercoin amount must be greater than zero"}'
            ];
        }
    }

    /**
     * Submit the littercoin burn transaction
     */
    public function submitBurnTx (Request $request)
    {
        $request->validate([
            'cborSig' => 'required|alpha_num|max:16384',
            'cborTx' => 'required|alpha_num|max:16384'
        ]);

        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg((string) $cborSig).' '.escapeshellarg((string) $cborTx).') 2>> ../storage/logs/littercoin.log';
        $response = exec($cmd);

        return [
            $response
        ];
    }

    /**
     * Build the merchant token mint transaction.
     */
    public function merchTx (Request $request)
    {
        $request->validate([
            'destAddr' => 'required|alpha_dash|max:110',
            'changeAddr' => 'required|alpha_num|max:256',
            'utxos' => 'required|array|max:256',
            'utxos.*' => 'required|alpha_num|max:8192'
        ]);

        $destAddr = $request->input('destAddr');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        if ((Auth::user() && ((Auth::user()->hasRole('admin') || Auth::user()->hasRole('superadmin')))))
        {
            $cmd = '(cd ../littercoin/;node ./run/build-merch-mint-tx.mjs '.escapeshellarg((string) $destAddr).' '.escapeshellarg((string) $changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log';
            $response = exec($cmd);

            return [
                $response
            ];

        }
        else
        {
            return [
                '{"status": "407", "msg": "User must be an admin"}'
            ];
        }
    }

    /**
     * Submit the merchant mint transaction.
     */
    public function submitMerchTx (Request $request)
    {
        $request->validate([
            'cborSig' => 'required|alpha_num|max:16384',
            'cborTx' => 'required|alpha_num|max:16384'
        ]);

        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        // Check that the user is an admin
        if ((Auth::user() && ((Auth::user()->hasRole('admin') || Auth::user()->hasRole('superadmin')))))
        {
            $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg((string) $cborSig).' '.escapeshellarg((string) $cborTx).') 2>> ../storage/logs/littercoin.log';
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
}
