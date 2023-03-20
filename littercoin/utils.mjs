import axios from 'axios';
import { bytesToText, 
         UTxO } from "./lib/helios.mjs";

export { getTokens,
         submitTx,
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
            timeout: 30000,
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
 * @param {MintingPolicyHash, UTxO[]} tokenMph, utxos
 * @returns {int} 
 */
const tokenCount = async (tokenMph, utxos) => {
    let tokenCount = BigInt(0);
    for (const utxo of utxos) {
        const mphs = utxo.value.assets.mintingPolicies;
        for (const mph of mphs) {
            if (mph.hex == tokenMph.hex) {
                const tokenNames = utxo.value.assets.getTokenNames(mph);
                for (const tokenName of tokenNames) {
                    tokenCount += utxo.value.assets.get(mph, tokenName);
                }
            }
        }
    }
    return tokenCount;
}


/**
 * Get the list of tokens names that match the minting policy
 * hash provided
 * @param {MintingPolicyHash, UTxO[]} tokenMph, utxos
 * @returns {string[]} 
 */
const getTokens = async (tokenMph, utxos) => {
    let tn = [];
    for (const utxo of utxos) {
        const mphs = utxo.value.assets.mintingPolicies;
        for (const mph of mphs) {
            if (mph.hex == tokenMph.hex) {
                const tokenNames = utxo.value.assets.getTokenNames(mph);
                for (const tokenName of tokenNames) {
                    tn.push(bytesToText(tokenName));
                }
            }
        }
    }
    return tn;
}