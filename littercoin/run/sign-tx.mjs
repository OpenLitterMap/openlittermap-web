import { Buffer } from "buffer";
import { blake2b } from "blakejs";
import { bytesToHex, 
         Signature,
         Tx } from "../lib/helios.mjs";
import pkg from '@stricahq/bip32ed25519';
const { Bip32PrivateKey } = pkg;

export { signTx };

/**
 * Sign the tx with a private key
 * @param {Tx} tx
 * @returns {Tx} tx
 */
const signTx = async (tx) => {

    const hash32 = (data) => {
        const hash = blake2b(data, undefined, 32);
        return Buffer.from(hash);
    };
        
    function harden(num) {
        return 0x80000000 + num;
    }
    
    try {
        const rootKeyHex = process.env.ROOT_KEY;
        const buffer = Buffer.from(rootKeyHex, 'hex');
        const rootKey = new Bip32PrivateKey(buffer);

        const accountKey = rootKey
        .derive(harden(1852)) // purpose
        .derive(harden(1815)) // coin type
        .derive(harden(0)); // account #0
        
        const addrPrvKey = accountKey
        .derive(0) // external
        .derive(0)
        .toPrivateKey();

        const addrPubKey = accountKey
        .derive(0) // external
        .derive(0)
        .toBip32PublicKey();
        
        const txBodyCbor = bytesToHex((tx.body).toCbor());
        const txBody = Buffer.from(txBodyCbor, 'hex');
        const txHash = hash32(txBody);

        const pubKeyArray = [...addrPubKey.toBytes().subarray(0, 32)];
        const signatureArray = [...addrPrvKey.sign(txHash)];

        const signature = new Signature(pubKeyArray,
                                        signatureArray);

        tx.addSignature(signature);
        return tx;
    }
    catch (err) {
        throw console.error("sign-tx: ", err);
    }
}