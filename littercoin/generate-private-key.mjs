import { mnemonicToEntropy } from 'bip39';
import { bytesToHex} from './lib/helios.mjs';
import pkg from '@stricahq/bip32ed25519';
const { Bip32PrivateKey } = pkg;

/***************************************************
* Usage: node ./generate-private-key.mjs witness,pipe,egg,awake,hood,false,fury,announce,one,wool,diagram,weird,phone,treat,bacon
* @params {string} seed phrase
* @output {string} ROOT_KEY (xprv)
****************************************************/

const args = process.argv;
const entropyArray = args[2].split(',');
const entropy = mnemonicToEntropy(entropyArray.join(' '));
const buffer = Buffer.from(entropy, 'hex');
const rootKey = await Bip32PrivateKey.fromEntropy(buffer);
const key = [...rootKey.toBytes()];
const keyStore = bytesToHex(key);
console.log("ROOT_KEY=" + keyStore);