<?php

namespace App\Http\Controllers\Littercoin;

use App\Helpers\Twitter;
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

    /**
     * Build the add Ada transaction.
     */
    public function addAdaTx (Request $request)
    {
        $request->validate([
            'adaQty' => 'required|int|max:1000000',
            'changeAddr' => 'required|alpha_num|max:256',
            'utxos' => 'required|array|max:256',
            'utxos.*' => 'required|alpha_num|max:8192'
        ]);

        $adaQty = $request->input('adaQty');
        $changeAddr = $request->input('changeAddr');
        $utxos = $request->input('utxos');
        $strUtxos=implode(",",$utxos);

        if ($adaQty >= 2)
        {
            $cmd = '(cd ../littercoin/;node ./run/build-add-ada-tx.mjs '.escapeshellarg((string) $adaQty).' '.escapeshellarg((string) $changeAddr).' '.escapeshellarg($strUtxos).') 2>> ../storage/logs/littercoin.log';
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
    public function submitAddAdaTx (Request $request)
    {
        $request->validate([
            'cborSig' => 'required|alpha_num|max:16384',
            'cborTx' => 'required|alpha_num|max:16384'
        ]);

        $cborSig = $request->input('cborSig');
        $cborTx = $request->input('cborTx');

        $cmd = '(cd ../littercoin/;node ./run/submit-tx.mjs '.escapeshellarg((string) $cborSig).' '.escapeshellarg((string) $cborTx).') 2>> ../storage/logs/littercoin.log';
        $response = exec($cmd);

        $responseObject = json_decode($response, false);

        if ($responseObject->status === 200)
        {
            Twitter::sendTweet("{todo} ada was added to the #Littercoin smart contract.");
        }

        return [
            $response
        ];
    }
}
