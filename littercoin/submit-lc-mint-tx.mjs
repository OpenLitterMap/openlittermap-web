import {
    hexToBytes, 
    Tx, 
    TxWitnesses,
    } from "@hyperionbt/helios";


import { submitTx } from './utils.mjs';


/**
 * Main calling function via the command line. 
 * Usage: node submit-lc-mint-tx.mjs walletSignature cborTx
 * @params {string, string}
 * @output {string} txId
 */
const main = async () => {
    try {

        const args = process.argv;
        const cborSig = args[2];
        const cborTx = args[3];

        // Confirm that the amount of minted littercoins matches what is in the tx
        const tx = Tx.fromCbor(hexToBytes(cborTx));

        // Add signature from the users wallet
        const signatures = TxWitnesses.fromCbor(hexToBytes(cborSig)).signatures;
        tx.addSignatures(signatures);

        const txId = await submitTx(tx);

        const returnObj = {
            status: 200,
            txId: txId
        }
        // Log tx submission success
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("submit-lc-mint-tx success - txId: ", txId);
        process.stdout.write(JSON.stringify(returnObj));

    } catch (err) {
        const returnObj = {
            status: 500
        }
        // Log tx submission failure
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("submit-lc-mint-tx error: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}


main();




