import axios from 'axios';
import { UTxO } from "@hyperionbt/helios";

export { submitTx,
         tokenCount };

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
 * Get the number of tokens in a set of utxo for a given mph
 * @param {string, UTxO[]} tokenMph, utxos
 * @returns {int} 
 */
const tokenCount = async (tokenMph, utxos) => {
    let tokenCount = BigInt(0);
    for (const utxo of utxos) {
        const mphs = utxo.value.assets.mintingPolicies;
        for (const mph of mphs) {
        if (mph.hex == tokenMph) {
            const tokenNames = utxo.value.assets.getTokenNames(mph);
            for (const tokenName of tokenNames) {
            tokenCount += utxo.value.assets.get(mph, tokenName);
            }
        }
        }
    }
    return tokenCount;
}