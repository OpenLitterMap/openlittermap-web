import {
    Address, 
    Assets, 
    bytesToHex, 
    CoinSelection,
    ConstrData, 
    hexToBytes, 
    NetworkParams,
    Program, 
    PubKeyHash,
    Value, 
    textToBytes,
    TxOutput,
    Tx, 
    UTxO 
} from "../lib/helios.mjs";

import { getLittercoinContractDetails } from "./lc-info.mjs";
import { signTx } from "./sign-tx.mjs";


/**
 * Main calling function via the command line 
 * Usage: node build-merch-mint-tx.js destAddr cBorChangeAddr [cborUtxo1,cborUtxo2,...]
 * @params {string, string, string[]}
 * @output {string} cborTx
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
        const destAddr = args[2];
        const hexChangeAddr = args[3];
        const cborUtxos = args[4].split(',');

        // Add 1 year expiry date for merchant token name
        const today = Date.now().toString();
        const merchTokenName = process.env.MERCH_TOKEN_NAME + " | " + today.toString();
  
        // Get the change address from the wallet
        const changeAddr = Address.fromHex(hexChangeAddr);

        // Get UTXOs from wallet
        const walletUtxos = cborUtxos.map(u => UTxO.fromCbor(hexToBytes(u)));
        const utxos = CoinSelection.selectSmallestFirst(walletUtxos, minUTXOVal);

        // Get littercoin smart contract and related script details
        const lcDetails = await getLittercoinContractDetails();
        
        // Start building the transaction
        const tx = new Tx();

        // Add the UTXO as inputs
        tx.addInputs(utxos[0]);

        // Add the script as a witness to the transaction
        const compiledMTMintScript = Program.new(lcDetails.mtMintScript).compile(optimize);
        const merchTokenMPH = compiledMTMintScript.mintingPolicyHash;
        tx.attachScript(compiledMTMintScript);

        // Create an empty Redeemer because we must always send a Redeemer with
        // a plutus script transaction even if we don't actually use it.
        const merchRedeemer = new ConstrData(0, []);
        const merchToken = [[textToBytes(merchTokenName), BigInt(1)]];
        
        // Add the mint to the tx
        tx.mintTokens(
            merchTokenMPH,
            merchToken,
            merchRedeemer
        )

        // Attach the output with the minted merchant token to the destination address
        tx.addOutput(new TxOutput(
            Address.fromBech32(destAddr),
            new Value(minAda, new Assets([[merchTokenMPH, merchToken]]))
          ));

        // Add owner pkh as a signer which is required to mint littercoin
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
        console.error("build-merchant-tx: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}

main();


  
