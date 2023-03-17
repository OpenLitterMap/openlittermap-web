import {
    hexToBytes, 
    Tx, 
    TxWitnesses,
    } from "@hyperionbt/helios";

import { signTx } from "./sign-tx.mjs";
import { submitTx } from './utils.mjs';


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
        const cborSig = args[2];
        const cborTx = args[3];

        // Create the helios tx object
        const tx = Tx.fromCbor(hexToBytes(cborTx));

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
        console.error("submit-merch-mint-tx success - txId: ", txId);
        process.stdout.write(JSON.stringify(returnObj));

    } catch (err) {
        const returnObj = {
            status: 500
        }
        // Log tx submission failure
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("submit-merch-mint-tx error: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}


main();




