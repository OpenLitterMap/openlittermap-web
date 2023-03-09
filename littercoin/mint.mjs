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

    const minAda = BigInt(2000000); // minimum lovelace needed to send an NFT
    const maxTxFee = BigInt(500000); // maximum estimated transaction fee
    const minChangeAmt = BigInt(1000000); // minimum lovelace needed to be sent back as change
    const minUTXOVal = new Value(minAda + maxTxFee + minChangeAmt);

    const args = process.argv;
    const destAddr = args[2];
    const hexChangeAddr = args[3];
    const cborUtxos = args[4].replace('[','').replace(']','').split(',');

    // Get the change address from the wallet
    const changeAddr = Address.fromHex(hexChangeAddr);

    //const output = changeAddr.toBech32().toString();
    //process.stdout.write(output);

    // Get UTXOs from wallet
    const walletUtxos = cborUtxos.map(u => UTxO.fromCbor(hexToBytes(u)));
    const utxos = CoinSelection.selectSmallestFirst(walletUtxos, minUTXOVal);

    console.log("utxos", utxos[0][0].txId.hex);
    process.stdout.write(utxos[0][0].txId.hex);

    // Start building the transaction
    const tx = new Tx();



  
