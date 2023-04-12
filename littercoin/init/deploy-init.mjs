import path from 'path';
import { promises as fs } from 'fs';
import * as helios from "../lib/helios.mjs"

// Set compiler optimizer flag
let optimize = (process.env.OPTIMIZE == 'true');
const contractDirectory = path.join(process.cwd(), './src');

// Thread token
const threadTokenFile = await fs.readFile(contractDirectory + '/threadToken.hl', 'utf8');
const threadTokenScript = threadTokenFile.toString();
const compiledTTMintScript = helios.Program.new(threadTokenScript).compile(optimize);
const threadTokenMPH = compiledTTMintScript.mintingPolicyHash;

console.log("thread token mph: ", threadTokenMPH.hex);

// Merchant token 
const merchTokenScript = await fs.readFile(contractDirectory + '/merchToken.hl', 'utf8');
const compiledMerchTokenScript = helios.Program.new(merchTokenScript).compile(optimize);
const merchMPH = compiledMerchTokenScript.mintingPolicyHash;

console.log("merchant token mph: ",merchMPH.hex);

