import {
    Address, 
    Assets, 
    bytesToHex, 
    ByteArrayData,
    CoinSelection,
    ConstrData, 
    Datum, 
    hexToBytes, 
    IntData, 
    ListData, 
    NetworkParams,
    Program, 
    PubKeyHash,
    Value, 
    TxOutput,
    Tx, 
    UTxO 
} from "@hyperionbt/helios";

import { fetchLittercoinInfo,
         getLittercoinContractDetails } from "./lc-info.mjs";

import { tokenCount } from "./utils.mjs";


/**
 * Main calling function via the command line 
 * Usage: mint.js lcQty destAddr cBorChangeAddr [cborUtxo1,cborUtxo2,...]
 * @params {int, string, string, string[]}
 * @output {string} cborSignature, cborTx
 */
const main = async () => {

    // Set the Helios compiler optimizer flag
    //const optimize = false;
    let optimize = (process.env.OPTIMIZE === 'true');
    //const minAda = BigInt(2000000); // minimum lovelace needed to send an NFT
    const minAda = BigInt(process.env.MIN_ADA);
    //const maxTxFee = BigInt(500000); // maximum estimated transaction fee
    const maxTxFee = BigInt(process.env.MAX_TX_FEE);
    //const minChangeAmt = BigInt(1000000); // minimum lovelace needed to be sent back as change
    const minChangeAmt = BigInt(process.env.MIN_CHANGE_AMT);
    const minUTXOVal = new Value(minAda + maxTxFee + minChangeAmt);

    try {
        const args = process.argv;
        const lcQty = args[2];
        const destAddr = args[3];
        const hexChangeAddr = args[4];
        const cborUtxos = args[5].split(',');

        // Get the change address from the wallet
        const changeAddr = Address.fromHex(hexChangeAddr);

        // Get UTXOs from wallet
        const walletUtxos = cborUtxos.map(u => UTxO.fromCbor(hexToBytes(u)));
        const utxos = CoinSelection.selectSmallestFirst(walletUtxos, minUTXOVal);

        // Get littercoin smart contract info
        const lcInfo = await fetchLittercoinInfo();

        const adaAmount = BigInt(lcInfo.list[0].int);
        const newLCAmount = BigInt(lcInfo.list[1].int) + BigInt(lcQty);
        const newDatAda = new IntData(adaAmount.valueOf());
        const newDatLC = new IntData(newLCAmount.valueOf());
        const newDatum = new ListData([newDatAda, newDatLC]);

        // Get littercoin smart contract and related script details
        const lcDetails = await getLittercoinContractDetails();

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

        console.log("txMinted: ",tx.dump());
        const returnObj = {
            status: 200,
            cborTx: bytesToHex(tx.toCbor())
        }
        process.stdout.write(JSON.stringify(returnObj));

    } catch (err) {
        const returnObj = {
            status: 500
        }
        console.error("create-mint-tx: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}

main();


  
