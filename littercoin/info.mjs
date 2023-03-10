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
    console.log("blockfrostUrl", blockfrostUrl);

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
            addr: lcValAddr.toBech32()
        }
        return returnObj;
    } else {
        console.log("fetchLittercoin: thread token not found");
    }
}

const output = await fetchLittercoinInfo();
process.stdout.write(JSON.stringify(output));


