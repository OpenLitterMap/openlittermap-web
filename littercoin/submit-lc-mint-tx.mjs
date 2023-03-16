import axios from 'axios';
import {
    Assets, 
    hexToBytes, 
    Program, 
    Value, 
    Tx, 
    TxWitnesses,
    } from "@hyperionbt/helios";

import { getLittercoinContractDetails } from "./lc-info.mjs";
import { signTx } from "./sign-mint-tx.mjs";


/**
 * Submit a Helios Tx to blockfrost and return the
 * txId if successful.
 * @param {Tx} tx
 * @returns {string} txId
 */

const submitTx = async (tx) => {

    const payload = new Uint8Array(tx.toCbor());
    const blockfrostAPI = process.env.BLOCKFROST_API;
    const blockfrostUrl = blockfrostAPI + "/tx/submit";
    const apiKey = process.env.BLOCKFROST_API_KEY;

    try {
        let res = await axios({
            url: blockfrostUrl,
            data: payload,
            method: 'post',
            timeout: 8000,
            headers: {
                'Content-Type': 'application/cbor',
                'project_id': apiKey
            }
        })
        if(res.status == 200){
            return res.data;
        } else {
            throw res.data;
        }   
    }
    catch (err) {
        throw err;
    }
}
/**
 * Main calling function via the command line. 
 * Usage: node submit-tx.mjs lcQty walletSignature cborTx
 * @params {int, string, string}
 * @output {string} txId
 */
const main = async () => {
    try {
        // Set the Helios compiler optimizer flag
        //const optimize = false;
        let optimize = (process.env.OPTIMIZE == 'true');

        const args = process.argv;
        const lcQty = args[2];
        const cborSig = args[3];
        const cborTx = args[4];

        // Check that the minted value from the transaction is equal to the 
        // amount of littercoin due.
        const lcDetails = await getLittercoinContractDetails();
        const compiledLCMintScript = Program.new(lcDetails.lcMintScript).compile(optimize);
        const lcTokenMPH = compiledLCMintScript.mintingPolicyHash;

        // Construct the amount of littercoin tokens that should be minted
        const lcTokens = [[hexToBytes(process.env.LC_TOKEN_NAME), BigInt(lcQty)]];
        const lcValue = new Value(BigInt(0), new Assets([[lcTokenMPH, lcTokens]]));

        // Confirm that the amount of minted littercoins matches what is in the tx
        const tx = Tx.fromCbor(hexToBytes(cborTx));
        const mintedVal = new Value(BigInt(0), tx.body.minted);

        if (!mintedVal.eq(lcValue)) {
            throw console.error("Number of littercoins due does not match littercoins minted");
        }

        // Add signature from the users wallet
        const signatures = TxWitnesses.fromCbor(hexToBytes(cborSig)).signatures;
        tx.addSignatures(signatures);

        // Add the signature from the server side private key
        const txSigned = await signTx(tx);
        
        const txId = await submitTx(txSigned);
        const returnObj = {
            status: 200,
            txId: txId
        }
        // Log tx submission success
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("submit-tx success - txId: ", txId);
        process.stdout.write(JSON.stringify(returnObj));

    } catch (err) {
        const returnObj = {
            status: 500
        }
        // Log tx submission failure
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("submit-mint-tx error: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}


main();




