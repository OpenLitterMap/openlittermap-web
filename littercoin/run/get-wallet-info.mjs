import {
    hexToBytes, 
    Value, 
    UTxO 
} from "../lib/helios.mjs";

import { getLittercoinContractDetails } from "./lc-info.mjs";
import { tokenCount } from "./utils.mjs";

/**
 * Main calling function via the command line 
 * Usage: node get-wallet-info.mjs balanceCborHex [cborUtxo1,cborUtxo2,...]
 * @params {string[]}
 * @output {string} AdaBalance LittercoinBalance MerchantTokenBalance
 */
const main = async () => {

    try {
        const args = process.argv;
        const balanceCBORHex = args[2];
        const cborUtxos = args[3].split(',');

        // Get wallet Ada lovelace balance
        const balanceAmountValue =  Value.fromCbor(hexToBytes(balanceCBORHex));

        // Get UTXOs from wallet
        const utxos = cborUtxos.map(u => UTxO.fromCbor(hexToBytes(u)));

        // Get littercoin smart contract and related script details
        const lcDetails = await getLittercoinContractDetails();

        // Get the total number of littercoins in the wallet
        const lcCount = await tokenCount(lcDetails.lcMPH, utxos);

        // Get the total number of merchant tokens in the wallet
        const mtCount = await tokenCount(lcDetails.mtMPH, utxos);

        const output = {
            adaAmt: Number(balanceAmountValue.lovelace) / 1000000,
            lcAmt: Number(lcCount),
            mtAmt: Number(mtCount)
        }

        const returnObj = {
            status: 200,
            payload: output
        }
        process.stdout.write(JSON.stringify(returnObj));
    
    } catch (err) {
        const returnObj = {
            status: 500
        }
        console.error("get-wallet-info: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}

main();
