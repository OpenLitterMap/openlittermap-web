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
    textToBytes,
    Value, 
    TxOutput,
    Tx, 
    UTxO 
} from "../lib/helios.mjs";

import { fetchLittercoinInfo,
         getLittercoinContractDetails } from "./lc-info.mjs";

import { tokenCount,
         getTokens } from "./utils.mjs";
import { signTx } from "./sign-tx.mjs";


/**
 * Main calling function via the command line 
 * Usage: node build-lc-burn-tx.mjs lcQty destAddr cBorChangeAddr [cborUtxo1,cborUtxo2,...]
 * @params {int, string, string, string[]}
 * @output {string} cborTx
 */
const main = async () => {

    // Set the Helios compiler optimizer flag
    let optimize = (process.env.OPTIMIZE === 'true');
    const minAda = BigInt(process.env.MIN_ADA);  // minimum lovelace needed to send an NFT
    const maxTxFee = BigInt(process.env.MAX_TX_FEE);
    const minChangeAmt = BigInt(process.env.MIN_CHANGE_AMT);

    try {
        const args = process.argv;
        const lcQty = args[2];
        const hexChangeAddr = args[3];
        const cborUtxos = args[4].split(',');

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
  
            var timestamp = new Date().toISOString();
            console.error(timestamp);
            console.error("build-lc-burn-tx: Withdraw amount is less than 2 minAda");
            const returnObj = {
                status: 503
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        // If the amount remaining in the datum of the
        // smart contract is less than min Ada, then raise an error.
        var newAdaAmount;
        if (adaDiff >= minAda) {
            newAdaAmount = adaAmount - BigInt(withdrawAda);
        } else {
            var timestamp = new Date().toISOString();
            console.error(timestamp);
            console.error("build-lc-burn-tx: Insufficient funds in Littercoin contract");
            const returnObj = {
                status: 504
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        const newDatAda = new IntData(newAdaAmount.valueOf());
        const newDatLC = new IntData(newLCAmount.valueOf());
        const newDatum = new ListData([newDatAda, newDatLC]);

        // Calcuate the service fee for a burn tx
        const serviceFeeEstimate = Math.floor(withdrawAda * Number(process.env.SERVICE_FEE_PERCENT));
        var serviceFee;
        if (serviceFeeEstimate > minAda) {
            serviceFee = BigInt(serviceFeeEstimate);
        } else {
            serviceFee = minChangeAmt;
        }

        // Get the UTXO to cover the Ada amount required for this tx
        const minUTXOVal = new Value(minAda + maxTxFee + minChangeAmt + serviceFee);

        // Get littercoin smart contract and related script details
        const lcDetails = await getLittercoinContractDetails();

        // Construct the littercoin token value to be spent from the wallet
        const lcTokens = [[textToBytes(process.env.LC_TOKEN_NAME), BigInt(lcQty)]];
        const lcVal = new Value(BigInt(minAda), new Assets([[lcDetails.lcMPH, lcTokens]]));

        // Convert cbor utxos into Helios UTXOs
        const walletUtxos = cborUtxos.map(u => UTxO.fromCbor(hexToBytes(u)));

        // Pull out merchant token(s) that match the merchant token minting policy
        const merchTokenNames = await getTokens(lcDetails.mtMPH, walletUtxos);

        // The wallet must contain at least one merchant token.
        if (merchTokenNames.length == 0) {
  
            var timestamp = new Date().toISOString();
            console.error(timestamp);
            console.error("build-lc-burn-tx: There must be at least one merchant token in the wallet");
            const returnObj = {
                status: 502
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        // Check if there is a merchant token that is still valid
        let validMerch = false;
        var merchantTN;
        for (const merchTokenName of merchTokenNames) {
            const timestamp = (merchTokenName.split('|'))[1];
            const today = Date.now();

            if (today - Number(timestamp) < Number(process.env.EXPIRY_DURATION)) {
                validMerch = true;
                merchantTN = merchTokenName;
                break;
            }
        }
      
        if (!validMerch) {
            var timestamp = new Date().toISOString();
            console.error(timestamp);
            console.error("build-lc-burn-tx: No valid merchant token found in the wallet");
            const returnObj = {
                status: 505
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        // Construct the merchant token to be spent from the wallet
        const merchTokens = [[textToBytes(merchantTN), BigInt(1)]];
        const merchVal = new Value(BigInt(minAda), new Assets([[lcDetails.mtMPH, merchTokens]]));

        // Get UTXOs from wallet
        const utxos = CoinSelection.selectSmallestFirst(walletUtxos, minUTXOVal.add(lcVal).add(merchVal));

        // Check the total number of littercoin in the utxos.
        // We will then decrement the number of tokens being burned
        // and put the rest (if any) in an output.
        const lcTokenCount = await tokenCount(lcDetails.lcMPH, utxos[0]);

        // Get the change address from the wallet
        const changeAddr = Address.fromHex(hexChangeAddr);
 
        // Start building the transaction
        const tx = new Tx();

        // Add the UTXO as inputs
        tx.addInputs(utxos[0]);

        // Construct the burn littercoin validator redeemer
        const valRedeemer = new ConstrData(2, [new ByteArrayData(changeAddr.pubKeyHash.bytes),
                                              new ByteArrayData(textToBytes(merchantTN))])
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
                [textToBytes(process.env.THREAD_TOKEN_NAME), BigInt(1)]
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
        const lcBurnTokens = [[textToBytes(process.env.LC_TOKEN_NAME), (BigInt(lcQty) * BigInt(-1))]];

        // Add the mint to the tx
        tx.mintTokens(
            lcTokenMPH,
            lcBurnTokens,
            mintRedeemer
        )

        // Make sure there is an output that matches the merchant change address 
        // pkh provided in the validator redeemer
        tx.addOutput(new TxOutput(
            changeAddr,
            merchVal
        ));

        // Construct the ada withdraw output
        const withdrawAdaVal = new Value(BigInt(withdrawAda));
        tx.addOutput(new TxOutput(
            changeAddr,
            withdrawAdaVal
        ));

        // Construct the service fee output
        const serviceFeeAdaVal = new Value(serviceFee);
        const serviceFeeAddr = Address.fromBech32(process.env.SERVICE_FEE_ADDR);
        tx.addOutput(new TxOutput(
            serviceFeeAddr,
            serviceFeeAdaVal
        ));

        // Construct the littercoin tokens to be returned if any
        const lcDelta = lcTokenCount - BigInt(lcQty);
        if (lcDelta > 0) {

            const lcTokens = [[textToBytes(process.env.LC_TOKEN_NAME), lcDelta]];
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
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("build-lc-burn-tx: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}

main();


  
