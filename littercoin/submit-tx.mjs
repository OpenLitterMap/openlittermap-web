import axios from 'axios';
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
    TxWitnesses,
    UTxO,
    WalletHelper, 
    } from "@hyperionbt/helios";


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
            timeout: 8000,
            headers: {
                'Content-Type': 'application/cbor',
                'project_id': apiKey
            }
        })
        if(res.status == 200){
            return res.data;
        } else {
            console.error("submitTx API Blockfrost Error: ", res.data);
            throw res.data;
        }   
    }
    catch (err) {
        console.error("submitTx API Failed: ", err);
        throw err;
    }
}

try {

    const args = process.argv;
    const cborSig = args[2];
    const cborTx = args[3];

    const tx = Tx.fromCbor(hexToBytes(cborTx));
    const signatures = TxWitnesses.fromCbor(hexToBytes(cborSig)).signatures;
    tx.addSignatures(signatures);
    
    const txId = await submitTx(tx);
    const returnObj = {
        status: 200,
        txId: txId
    }
    process.stdout.write(JSON.stringify(returnObj));

} catch (err) {
    const returnObj = {
        status: 500
    }
    process.stdout.write(JSON.stringify(returnObj));
    throw console.error("submit-tx error: ", err);
}




