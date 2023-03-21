import { Buffer } from "buffer";
import { blake2b } from "blakejs";
import { mnemonicToEntropy } from 'bip39';
import { bytesToHex} from './lib/helios.mjs';
import pkg from '@stricahq/bip32ed25519';
const { Bip32PrivateKey } = pkg;

/***************************************************
* Usage: node ./generate-private-key.mjs witness,pipe,egg,awake,hood,false,fury,announce,one,wool,diagram,weird,phone,treat,bacon
* @params {string} seed phrase
* @output {string} ROOT_KEY OWNER_PKH
****************************************************/

const hash28 = (data) => {
    const hash = blake2b(data, undefined, 28);
    return Buffer.from(hash);
};

function harden(num) {
    return 0x80000000 + num;
}

const main = async () => {

    const args = process.argv;
    const entropyArray = args[2].split(',');
    const entropy = mnemonicToEntropy(entropyArray.join(' '));
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

    const publicKey = addrPubKey.toPublicKey().toBytes();
    console.log("OWNER_PKH=" + bytesToHex(hash28(publicKey)));
}

main();
