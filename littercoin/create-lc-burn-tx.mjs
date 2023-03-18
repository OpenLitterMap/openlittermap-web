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
import { signTx } from "./sign-tx.mjs";


/**
 * Main calling function via the command line 
 * Usage: mint.js lcQty destAddr cBorChangeAddr [cborUtxo1,cborUtxo2,...]
 * @params {int, string, string, string[]}
 * @output {string} cborSignature, cborTx
 */
const main = async () => {

    // Set the Helios compiler optimizer flag
    let optimize = (process.env.OPTIMIZE === 'true');
    const minAda = BigInt(process.env.MIN_ADA);  // minimum lovelace needed to send an NFT
    const maxTxFee = BigInt(process.env.MAX_TX_FEE);
    const minChangeAmt = BigInt(process.env.MIN_CHANGE_AMT);
    const minUTXOVal = new Value(minAda + maxTxFee + minChangeAmt);

    try {
        const args = process.argv;
        console.error("args", args);
        const lcQty = args[2];
        const hexChangeAddr = args[3];
        const cborUtxos = args[4].split(',');

        // Get littercoin smart contract and related script details
        const lcDetails = await getLittercoinContractDetails();

        // Construct the littercoin token value to be spent from the wallet
        const lcTokens = [[hexToBytes(process.env.LC_TOKEN_NAME), BigInt(lcQty)]];
        const lcVal = new Value(BigInt(minAda), new Assets([[lcDetails.lcMPH, lcTokens]]));

        // Construct the merchant token to be spent from the wallet
        const merchTokens = [[hexToBytes(process.env.MERCH_TOKEN_NAME), BigInt(1)]];
        const merchVal = new Value(BigInt(minAda), new Assets([[lcDetails.mtMPH, merchTokens]]));

        // Get UTXOs from wallet
        const walletUtxos = cborUtxos.map(u => UTxO.fromCbor(hexToBytes(u)));
        const utxos = CoinSelection.selectSmallestFirst(walletUtxos, minUTXOVal.add(lcVal).add(merchVal));

        // Check the total number of littercoin in the utxos.
        // We will then decrement the number of tokens being burned
        // and put the rest (if any) in an output.
        const lcTokenCount = await tokenCount(lcDetails.lcMPH, utxos[0]);

        // If the user does not have enought littercoins to cover the amount requested
        // to burn, then raise an error now.
        if (Number(lcTokenCount) >= Number(lcQty)) {
  
            console.error("create-lc-burn-tx: Insufficient littercoin in user wallet");
            const returnObj = {
                status: 501
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        // Check to confirm that there exists a merchant token in the user
        // wallet.
        const mtTokenCount = await tokenCount(lcDetails.mtMPH, utxos[0]);

        // If the user does not have the merchant token, then raise an error now.
        if (Number(mtTokenCount) >= 1) {
  
            console.error("create-lc-burn-tx: Merchant token not found in user wallet");
            const returnObj = {
                status: 502
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        // Get littercoin smart contract info
        const lcInfo = await fetchLittercoinInfo();

        const lcQtyAbs = Math.abs(Number(lcQty));
        const datAdaAmt = Number(lcInfo.list[0].int);
        const datLcAmt = Number(lcInfo.list[1].int);
        const newLCAmount = BigInt(datLcAmt) - BigInt(lcQtyAbs);
        const ratio = Math.floor(datAdaAmt / datLcAmt);
        const withdrawAda = ratio * lcQtyAbs;
        const adaDiff = datAdaAmt - withdrawAda;
        const adaAmount = BigInt(datAdaAmt);

        // If the ada value of the withdraw is less than min Ada, then raise an error now.
        if (withdrawAda < minAda) {
  
            console.error("create-lc-burn-tx: Withdraw amount is less than 2 minAda");
            const returnObj = {
                status: 503
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }


        var newAdaAmount;
        if (adaDiff >= minAda) {
            newAdaAmount = adaAmount - BigInt(withdrawAda);
        } else {
            console.error("create-lc-burn-tx: Insufficient funds in Littercoin contract");
            const returnObj = {
                status: 504
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        const newDatAda = new IntData(newAdaAmount.valueOf());
        const newDatLC = new IntData(newLCAmount.valueOf());
        const newDatum = new ListData([newDatAda, newDatLC]);

        // Get the change address from the wallet
        const changeAddr = Address.fromHex(hexChangeAddr);
 
        // Start building the transaction
        const tx = new Tx();

        // Add the UTXO as inputs
        tx.addInputs(utxos[0]);

        // Construct the burn littercoin validator redeemer
        const valRedeemer = new ConstrData(2, [new ByteArrayData(changeAddr.pubKeyHash.bytes)])
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
        const outputValue = new Value(BigInt(newAdaAmount), new Assets([
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
        const mintRedeemer = new ConstrData(1, [new ByteArrayData(lcValHash.bytes)])

        // Construct the amount of littercoin tokens to mint
        const lcBurnTokens = [[hexToBytes(process.env.LC_TOKEN_NAME), (BigInt(lcQty) * BigInt(-1))]];

        // Add the mint to the tx
        tx.mintTokens(
            lcTokenMPH,
            lcBurnTokens,
            mintRedeemer
        )

        // Make sure there is an output that matches the merchant (change addr) pkh provided in the validator redeemer
        tx.addOutput(new TxOutput(
            changeAddr,
            merchVal
        ));

        // Construct the ada withdraw amount
        const withdrawAdaVal = new Value(BigInt(withdrawAda));

        tx.addOutput(new TxOutput(
        changeAddr,
        withdrawAdaVal
        ));

        // Construct the littercoin tokens to be returned if any
        const lcDelta = lcTokenCount.valueOf() - BigInt(lcQty);
        if (lcDelta > 0) {

            const lcTokens = [[hexToBytes(process.env.LC_TOKEN_NAME), lcDelta]];
            const lcVal = new Value(BigInt(minAda), new Assets([[lcDetails.lcMPH, lcTokens]]));
            
            tx.addOutput(new TxOutput(
                changeAddr,
                lcVal
            ));
        } 

        // Add owner pkh as a signer to lock the transaction once it is built
        tx.addSigner(PubKeyHash.fromHex(process.env.OWNER_PKH));

        // Network Params
        const networkParams = new NetworkParams(JSON.parse(lcDetails.netParams));

        console.error("tx before final", tx.dump());

        // Send any change back to the buyer
        await tx.finalize(networkParams, changeAddr, utxos[1]);

        // Add the signature from the server side private key
        // This way, we lock the transaction now and then need
        // the end user to sign the tx.
        const txSigned = await signTx(tx);

        const returnObj = {
            status: 200,
            cborTx: bytesToHex(txSigned.toCbor())
        }
        process.stdout.write(JSON.stringify(returnObj));

    } catch (err) {

        const returnObj = {
            status: 500
        }
        console.error("create-lc-burn-tx: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}

main();


  
