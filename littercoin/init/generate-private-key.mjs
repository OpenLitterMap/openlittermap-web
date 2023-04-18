import { Buffer } from "buffer";
import { blake2b } from "blakejs";
import { mnemonicToEntropy } from 'bip39';
import { Address, 
         bytesToHex, 
         PubKeyHash} from '../lib/helios.mjs';
import pkg from '@stricahq/bip32ed25519';
const { Bip32PrivateKey } = pkg;

/***************************************************
* Usage:
* export ENTROPY="witness pipe egg awake hood false fury announce one wool diagram weird phone treat bacon"
* node ./generate-private-key.mjs
* @output {string} ROOT_KEY OWNER_PKH ADDRESS
****************************************************/

const hash28 = (data) => {
    const hash = blake2b(data, undefined, 28);
    return Buffer.from(hash);
};

function harden(num) {
    return 0x80000000 + num;
}

const main = async () => {

    if (!process.env.ENTROPY) {
        console.error("ENTROPY must be set as an environment variable");
        return;
    }
    const entropy = mnemonicToEntropy(process.env.ENTROPY);
    const buffer = Buffer.from(entropy, 'hex');
    const rootKey = await Bip32PrivateKey.fromEntropy(buffer);

    const accountKey = rootKey
    .derive(harden(1852)) // purpose
    .derive(harden(1815)) // coin type
    .derive(harden(0)); // account #0

    const addrPubKey = accountKey
    .derive(0) // external
    .derive(0)
    .toBip32PublicKey();

    const key = [...rootKey.toBytes()];
    const keyStore = bytesToHex(key);
    console.log("ROOT_KEY=" + keyStore);

    const pubKey = addrPubKey.toPublicKey().toBytes();
    const pubKeyHash = bytesToHex(hash28(pubKey));
    console.log("OWNER_PKH=" + pubKeyHash);

    const pkh = PubKeyHash.fromHex(pubKeyHash);
    const addr = Address.fromPubKeyHash(pkh);
    console.log("ADDRESS=" + addr.toBech32());
}

main();
