import path from 'path';
import { promises as fs } from 'fs';
import {
    Address, 
    Assets, 
    bytesToHex, 
    Datum, 
    hexToBytes, 
    ListData, 
    Program, 
    Value, 
    TxOutput,
    TxRefInput,
    TxId,
    UTxO, 
    textToBytes} from "../lib/helios.mjs";

import { callBlockfrost } from "./utils.mjs"

export { fetchLittercoinInfo, 
         getLittercoinContractDetails };


// Set in env variables
let optimize = (process.env.OPTIMIZE == 'true');
const blockfrostAPI = process.env.BLOCKFROST_API;
const contractDirectory = path.join(process.cwd(), '../public/contracts');

// Thread token minting script
const threadTokenFile = await fs.readFile(contractDirectory + '/threadToken.hl', 'utf8');
const threadTokenScript = threadTokenFile.toString();
const compiledTTMintScript = Program.new(threadTokenScript).compile(optimize);
const threadTokenMPH = compiledTTMintScript.mintingPolicyHash;
const threadTokenName = textToBytes(process.env.THREAD_TOKEN_NAME);

// Validator script
const lcValScriptName = "lcValidator.hl";
const lcValFile = await fs.readFile(contractDirectory + '/' + lcValScriptName, 'utf8');
const lcValScript = lcValFile.toString();
const compiledValScript = Program.new(lcValScript).compile(optimize);
const lcValHash = compiledValScript.validatorHash; 
const lcValAddr = Address.fromValidatorHash(lcValHash);

/**
 * Get the utxo with the thread token at the littercoin
 * validator sript address
 * @params {}
 * @returns {UTxO} ttUtxo
 */
const getTTUtxo = async () => {

    const blockfrostUrl = blockfrostAPI + "/addresses/" + lcValAddr.toBech32() + "/utxos/" + threadTokenMPH.hex + bytesToHex(threadTokenName);

    let utxos = await callBlockfrost(blockfrostUrl);
    if (utxos.length == 0) {
        throw console.error("thread token not found")
    }
    const lovelaceAmount = utxos[0].amount[0].quantity;
    const value = new Value(BigInt(lovelaceAmount), new Assets([
        [threadTokenMPH, [
            [threadTokenName, BigInt(1)]
        ]]
    ]));

    const ttUtxo = new UTxO(
        TxId.fromHex(utxos[0].tx_hash),
        BigInt(utxos[0].output_index),
                new TxOutput(
                    lcValAddr,
                    value,
                    Datum.inline(ListData.fromCbor(hexToBytes(utxos[0].inline_datum)))
                )
        );
    return ttUtxo;
}

/**
 * The littercoin smart contract info that is part of 
 * the datum values (adaAmount, lcAdmoun), script name, 
 * script address and the thread token utxo in cbor format.
 * @parms {}
 * @returns {lcInfo} lcInfo
 */
const fetchLittercoinInfo = async () => {

    const utxo = await getTTUtxo();

    if (utxo != undefined) {

        if (!utxo.origOutput.datum.isInline()) {
            throw console.error("inline datum not found")
        }
        const datData = utxo.origOutput.datum.data;
        const datJson = datData.toSchemaJson();
        const datObj = JSON.parse(datJson);

        const lcInfo = {
            ...datObj,
            addr: lcValAddr.toBech32(),
            scriptName: lcValScriptName,
            ttUtxo: bytesToHex(utxo.toCbor())
        }
        return lcInfo;
    } else {
        throw console.erorr("fetchLittercoin: thread token not found");
    }
}

/**
 * Get all of the littercoin valdiator and related scripts
 * details in one custom object
 * @parms {}
 * @returns {lcValDetails} lcDetails
 */
const getLittercoinContractDetails = async () => {

    // Network Parameters
    const networkParamsFile = await fs.readFile(contractDirectory + '/' + process.env.NETWORK_PARAMS_FILE, 'utf8');
    const networkParams = networkParamsFile.toString();
    
    // Littercoin minting script
    const lcMintScript = await fs.readFile(contractDirectory + '/lcMint.hl', 'utf8');
    const compiledLCMintScript = Program.new(lcMintScript).compile(optimize);
    const lcMPH = compiledLCMintScript.mintingPolicyHash;

    // Littercoin rewards token minting script
    const rewardsTokenScript = await fs.readFile(contractDirectory + '/rewardsToken.hl', 'utf8');
    const compiledRewardsScript = Program.new(rewardsTokenScript).compile(optimize);
    const rewardsMPH = compiledRewardsScript.mintingPolicyHash;

    // Merchant token minting script
    const merchTokenScript = await fs.readFile(contractDirectory + '/merchToken.hl', 'utf8');
    const compiledMerchTokenScript = Program.new(merchTokenScript).compile(optimize);
    const merchMPH = compiledMerchTokenScript.mintingPolicyHash;

    // Define blockfrost URL
    const blockfrostUrl = blockfrostAPI + "/addresses/" + lcValAddr.toBech32() + "/utxos/?order=asc";

    let utxos = await callBlockfrost(blockfrostUrl);

    // Find the reference utxo with the correct validator hash
    if (utxos.length > 0) {
      for (var utxo of utxos) {
        if (utxo.reference_script_hash === lcValHash.hex) {

            const valRefUTXO = new TxRefInput(
                TxId.fromHex(utxo.tx_hash),
                BigInt(utxo.output_index),
                new TxOutput(
                lcValAddr,
                new Value(BigInt(utxo.amount[0].quantity)),
                null,
                compiledValScript
                )
            );

          const lcValDetails = {
            lcValScript: lcValScript,
            lcValRefUtxo: valRefUTXO, 
            lcMintScript: lcMintScript,
            lcMPH: lcMPH,
            ttMintScript: threadTokenScript,
            ttMPH: threadTokenMPH,
            mtMintScript: merchTokenScript,
            mtMPH: merchMPH,
            rewardsMintScript: rewardsTokenScript,
            rewardsMPH: rewardsMPH,
            netParams: networkParams
          }
          return lcValDetails;
        }
      }
    } else {
      throw console.error("littercoin validator reference utxo not found")
    }
}