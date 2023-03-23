import path from 'path';
import { promises as fs } from 'fs';
import * as helios from "../lib/helios.mjs"

// Set compiler optimizer flag
let optimize = (process.env.OPTIMIZE == 'true');
const contractDirectory = path.join(process.cwd(), './src');

// Littercoin Token
const lcMintScript = await fs.readFile(contractDirectory + '/lcMint.hl', 'utf8');
const compiledLCMintScript = helios.Program.new(lcMintScript).compile(optimize);
const lcMPH = compiledLCMintScript.mintingPolicyHash;

console.log("littercoin token mph: ", lcMPH.hex);

// Rewards token 
const rewardsTokenScript = await fs.readFile(contractDirectory + '/rewardsToken.hl', 'utf8');
const compiledRewardsScript = helios.Program.new(rewardsTokenScript).compile(optimize);
const rewardsMPH = compiledRewardsScript.mintingPolicyHash;

console.log("rewards token mph: ",rewardsMPH.hex);

