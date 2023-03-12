import axios from 'axios';
import path from 'path';
import { promises as fs } from 'fs';
import {
    Address, 
    Assets, 
    bytesToHex, 
    ByteArrayData,
    Cip30Wallet,
    CoinSelection,
    ConstrData, 
    Datum, 
    hexToBytes, 
    IntData, 
    ListData, 
    MintingPolicyHash,
    NetworkParams,
    Program, 
    PubKeyHash,
    Value, 
    TxOutput,
    TxRefInput,
    Tx, 
    TxId,
    UTxO,
    WalletHelper } from "@hyperionbt/helios";

export { fetchLittercoinInfo, getLittercoinContractDetails };


// set in env variables
const optimize = false;
const blockfrostAPI = process.env.BLOCKFROST_API;

async function getUtxos(blockfrostUrl) {

    const apiKey = process.env.BLOCKFROST_API_KEY;

    try {
        let res = await axios({
            url: blockfrostUrl,
            method: 'get',
            timeout: 8000,
            headers: {
                'Content-Type': 'application/json',
                'project_id': apiKey
            }
        })
        if(res.status == 200){
            return res.data;
        } else {
            throw console.error("getUtxos: error getting utxos from blockfrost: ", res);
        }   
    }
    catch (err) {
        throw console.error("getUtxos: error getting utxos from blockfrost: ", err);
    }
}


const contractDirectory = path.join(process.cwd(), 'contracts');

// Thread token minting script
const threadTokenFile = await fs.readFile(contractDirectory + '/threadToken.hl', 'utf8');
const threadTokenScript = threadTokenFile.toString();
const compiledTTMintScript = Program.new(threadTokenScript).compile(optimize);
const threadTokenMPH = compiledTTMintScript.mintingPolicyHash;
const threadTokenName = process.env.THREAD_TOKEN_NAME;

// Validator script
const lcValFile = await fs.readFile(contractDirectory + '/lcValidator.hl', 'utf8');
const lcValScript = lcValFile.toString();
const compiledValScript = Program.new(lcValScript).compile(optimize);
const lcValHash = compiledValScript.validatorHash; 
const lcValAddr = Address.fromValidatorHash(lcValHash);


// Get the utxo with the thread token at the LC validator address
const getTTUtxo = async () => {

    const blockfrostUrl = blockfrostAPI + "/addresses/" + lcValAddr.toBech32() + "/utxos/" + threadTokenMPH.hex + threadTokenName;
    //console.log("blockfrostUrl", blockfrostUrl);

    let utxos = await getUtxos(blockfrostUrl);
    if (utxos.length == 0) {
        throw console.error("thread token not found")
    }
    const lovelaceAmount = utxos[0].amount[0].quantity;
    const token = hexToBytes(threadTokenName);
    const value = new Value(BigInt(lovelaceAmount), new Assets([
        [threadTokenMPH, [
            [token, BigInt(1)]
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


const fetchLittercoinInfo = async () => {

    const utxo = await getTTUtxo();

    if (utxo != undefined) {

        if (!utxo.origOutput.datum.isInline()) {
            throw console.error("inline datum not found")
        }
        const datData = utxo.origOutput.datum.data;
        const datJson = datData.toSchemaJson();
        const datObj = JSON.parse(datJson);
        //const adaAmount = datObj.list[0].int;
        //const lcAmount = datObj.list[1].int;
        
        //return [lcValAddr.toBech32(), adaAmount, lcAmount];
        const returnObj = {
            ...datObj,
            addr: lcValAddr.toBech32(),
            ttUtxo: bytesToHex(utxo.toCbor())
        }
        //return JSON.stringify(returnObj);
        return returnObj;
    } else {
        throw console.erorr("fetchLittercoin: thread token not found");
    }
}


// Main calling function

try {
    const output = await fetchLittercoinInfo();
    const returnObj = {
        status: 200,
        payload: output
    }
    process.stdout.write(JSON.stringify(returnObj));

} catch (err) {
    const returnObj = {
        status: 500
    }
    process.stdout.write(JSON.stringify(returnObj));
    throw console.error("info error: ", err);
}



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

    // Littercoin rewards token minting script
    const merchTokenScript = await fs.readFile(contractDirectory + '/merchToken.hl', 'utf8');
    const compiledMerchTokenScript = Program.new(merchTokenScript).compile(optimize);
    const merchMPH = compiledMerchTokenScript.mintingPolicyHash;


    // Define blockfrost URL
    const blockfrostUrl = blockfrostAPI + "/addresses/" + lcValAddr.toBech32() + "/utxos/?order=asc";
    console.log("blockfrostUrl: ", blockfrostUrl);

    let utxos = await getUtxos(blockfrostUrl);

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

          const lcVal = {
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
          return lcVal;
        }
      }
    } else {
      throw console.error("littercoin validator reference utxo not found")
    }
}






