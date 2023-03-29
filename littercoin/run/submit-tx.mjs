import {
    hexToBytes, 
    Tx, 
    TxWitnesses,
    } from "../lib/helios.mjs";

import { submitTx } from './utils.mjs';


/**
 * Main calling function via the command line. 
 * Usage: node submit-tx.mjs walletSignature cborTx
 * @params {string, string}
 * @output {string} txId
 */
const main = async () => {
    try {

        const args = process.argv;
        const cborSig = args[2];
        const cborTx = args[3];

        // Reconstruct the helios tx object
        const tx = Tx.fromCbor(hexToBytes(cborTx));

        // Add signature from the users wallet
        const signatures = TxWitnesses.fromCbor(hexToBytes(cborSig)).signatures;
        tx.addSignatures(signatures);

        const txId = await submitTx(tx);
        var timestamp = new Date().toISOString();
        const returnObj = {
            status: 200,
            txId: txId,
            date: timestamp
        }
        // Log tx submission success 
        console.error(timestamp);
        console.error("submit-tx success - txId: ", txId);
        process.stdout.write(JSON.stringify(returnObj));

    } catch (err) {
        const returnObj = {
            status: 500,
            date: timestamp
        }
        // Log tx submission failure
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("submit-tx error: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}


main();




