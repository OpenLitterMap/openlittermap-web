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
    WalletHelper, 
    } from "@hyperionbt/helios";

import { fetchLittercoinInfo,
            getLittercoinContractDetails } from "./info.mjs";
import { signTx } from "./sign.mjs";
import { tokenCount } from "./utils.mjs";

try {
      // Set the Helios compiler optimizer flag
    const optimize = false;
    const minAda = BigInt(2000000); // minimum lovelace needed to send an NFT
    const maxTxFee = BigInt(500000); // maximum estimated transaction fee
    const minChangeAmt = BigInt(1000000); // minimum lovelace needed to be sent back as change
    const minUTXOVal = new Value(minAda + maxTxFee + minChangeAmt);

    /*
     * usage: mint.js "lcQty" "destAddr" "changeAddr" "[cborUtxo1,cborUtxo2,...]"
    */

    const args = process.argv;
    //console.log("args", args);
    const lcQty = args[2];
    const destAddr = args[3];
    const hexChangeAddr = args[4];
    const cborUtxos = args[5].replace('[','').replace(']','').split(',');

    // Get the change address from the wallet
    const changeAddr = Address.fromHex(hexChangeAddr);

    //const output = changeAddr.toBech32().toString();
    //process.stdout.write(output);

    // Get UTXOs from wallet
    const walletUtxos = cborUtxos.map(u => UTxO.fromCbor(hexToBytes(u)));
    const utxos = CoinSelection.selectSmallestFirst(walletUtxos, minUTXOVal);

    //console.log("utxos", utxos[0][0].txId.hex);
    //process.stdout.write(utxos[0][0].txId.hex);

    const lcInfo = await fetchLittercoinInfo();
    //const lcInfoJSON = await JSON.parse(lcInfoStr);
    console.log("lcInfo", lcInfo);

    const adaAmount = BigInt(lcInfo.list[0].int);
    const newLCAmount = BigInt(lcInfo.list[1].int) + BigInt(lcQty);
    const newDatAda = new IntData(adaAmount.valueOf());
    const newDatLC = new IntData(newLCAmount.valueOf());
    const newDatum = new ListData([newDatAda, newDatLC]);

    const lcDetails = await getLittercoinContractDetails();
    //console.log("lcDetails", lcDetails);


    //console.log("lcTokenCount", lcTokenCount);

    // Start building the transaction
    const tx = new Tx();

    // Add the UTXO as inputs
    tx.addInputs(utxos[0]);

    // Construct the mint littercoin validator redeemer
    const valRedeemer = new ConstrData(1,[]);
    const valUtxo = UTxO.fromCbor(hexToBytes(lcInfo.ttUtxo));
    tx.addInput(valUtxo, valRedeemer);

    // Add littercoin reference input
    const compiledValScript = Program.new(lcDetails.lcValScript).compile(optimize);
    const lcValHash = compiledValScript.validatorHash; 
    const lcValAddr = Address.fromValidatorHash(lcValHash);
    tx.addRefInput(
        lcDetails.lcValRefUtxo,
        compiledValScript
    );

    const newInlineDatum = Datum.inline(newDatum);
    const outputValue = new Value(BigInt(adaAmount), new Assets([
      [lcDetails.ttMPH, [
          [hexToBytes(process.env.THREAD_TOKEN_NAME), BigInt(1)]
      ]]
    ]));

    // send Ada, updated dautm and thread token back to script address
    tx.addOutput(new TxOutput(lcValAddr, outputValue, newInlineDatum));

    // Add the script as a witness to the transaction
    const compiledLCMintScript = Program.new(lcDetails.lcMintScript).compile(optimize);
    const lcTokenMPH = compiledLCMintScript.mintingPolicyHash;
    tx.attachScript(compiledLCMintScript);

    // Construct a mint littecoin minting redeemer
    const mintRedeemer = new ConstrData(0, [new ByteArrayData(lcValHash.bytes)])

    // Construct the amount of littercoin tokens to mint
    const mintTokens = [[hexToBytes(process.env.LC_TOKEN_NAME), BigInt(lcQty)]];

    // Add the mint to the tx
    tx.mintTokens(
        lcTokenMPH,
        mintTokens,
        mintRedeemer
    )

    // Check the total number of littercoin already in the utxos.
    // We will then add this number to the minted amount
    // so we can put the total amount of littercoins in the output.
    const lcTokenCount = await tokenCount(lcTokenMPH, utxos[0]);

    // Construct the total amount of littercoin tokens
    const lcTokens = [[hexToBytes(process.env.LC_TOKEN_NAME), 
        (BigInt(lcQty) + lcTokenCount.valueOf())]];

    // Attached the output with the minted littercoins to the destination address
    tx.addOutput(new TxOutput(
        Address.fromBech32(destAddr),
        new Value(minAda, new Assets([[lcTokenMPH, lcTokens]]))
        ));

    // Add owner pkh as a signer which is required to mint littercoin
    tx.addSigner(PubKeyHash.fromHex(process.env.OWNER_PKH));

    // Network Params
    const networkParams = new NetworkParams(JSON.parse(lcDetails.netParams));

    // Send any change back to the buyer
    await tx.finalize(networkParams, changeAddr, utxos[1]);

    const txSigned = await signTx(tx);

    console.log("txSigned: ",txSigned.dump());
    const returnObj = {
        status: 200,
        cborTx: bytesToHex(txSigned.toCbor())
    }
    process.stdout.write(JSON.stringify(returnObj));

} catch {
    const returnObj = {
        status: 500
    }
    process.stdout.write(JSON.stringify(returnObj));
}


  
