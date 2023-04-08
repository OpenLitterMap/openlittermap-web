import path from 'path';
import { promises as fs } from 'fs';
import {
    Address, 
    Assets, 
    ConstrData, 
    Datum, 
    IntData, 
    ListData, 
    NetworkParams,
    Program, 
    PubKeyHash,
    Value, 
    textToBytes,
    TxOutput,
    Tx
} from "../lib/helios.mjs";

import { getAddrUtxo, submitTx } from "../run/utils.mjs";
import { signTx } from "../run/sign-tx.mjs";


/**
 * Main calling function via the command line 
 * Usage: node init-tx.mjs adaQty ownerAddress
 * @params {string}
 * @output {string} txId
 * 
 * Note: Requires 5000000 lovelace locked at address to be used for collateral
  
    ##############################################################
    # You must do these steps first before running this script
    ##############################################################
    #
    # Step 1.   cd to ./littercoin directory
    # Step 2.   update src/threadtoken.hl with admin UTXO that will be spent
    # Step 3.   node ./init/deploy-init.mjs
    # Step 4.   update src/mint.hl and src/rewardsToken.hl with thread token value
    # Step 5.   node ./init/deploy-mint.js
    # Step 6.   update ./src/validator.hl with threadtoken, littercoin, rewards and merchant mph values
    # Step 7.   node ./init/init-tx.mjs
    # Step 8.   cp ./src/*.hl ../public/contracts
    ##############################################################

    Make sure that the following env variables have been set.

    export OPTIMIZE="true"
    export NETWORK_PARAMS_FILE="preprod.json"
    export BLOCKFROST_API_KEY="get-your-blockfrost-key"
    export BLOCKFROST_API="https://cardano-preprod.blockfrost.io/api/v0"
    export MIN_ADA=2000000
    export THREAD_TOKEN_NAME="Thread Token Littercoin"
    export LC_TOKEN_NAME="Littercoin"
    export MERCH_TOKEN_NAME="Merchant Token"
    export REWARDS_TOKEN_NAME="Donation Rewards Littercoin"
    export ROOT_KEY="generate-your-root-key"
    export OWNER_PKH="put-the-public-key-hash here"

 */


const main = async () => {

    try {
        const args = process.argv;
        if (args.length < 4) {
            console.error("Invalid command line arguments");
            console.error("Usage: node init-tx.mjs adaQty ownerAddress");
            return;
        }
        const lovelaceQty = BigInt(args[2]);
        //const lovelaceQty =  BigInt(adaQty) * BigInt(1000000);
        const addr = args[3];
        const ownerAddr = Address.fromBech32(addr);

        // Set variables
        let optimize = (process.env.OPTIMIZE == 'true');
        const contractDirectory = './src';
        const minAda = BigInt(process.env.MIN_ADA);

        // Get the collateral UTXO with exactly 5 Ada
        var colUtxo;
        try {
            colUtxo = await getAddrUtxo(ownerAddr, BigInt(5000000));
        } catch (err) {
            var timestamp = new Date().toISOString();
            console.error(timestamp);
            console.error("init-tx: ", err);
            const returnObj = {
                status: 501
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        console.log("colUtxo", colUtxo.txId.hex);

        // Get a UTXO from the Address with the requried amount of the lovelace
        var utxo;
        try {
            utxo = await getAddrUtxo(ownerAddr, lovelaceQty);
        } catch (err) {
            var timestamp = new Date().toISOString();
            console.error(timestamp);
            console.error("init-tx: ", err);
            const returnObj = {
                status: 501
            }
            process.stdout.write(JSON.stringify(returnObj));
            return;
        }

        console.log("utxo", utxo.txId.hex);
        
        // Set default datum values
        const datAda = new IntData(BigInt(2000000));
        const datLC = new IntData(BigInt(2));
        const datum = new ListData([datAda, datLC]);

        // Network Parameters
        const networkParamsFile = await fs.readFile(contractDirectory + '/' + process.env.NETWORK_PARAMS_FILE, 'utf8');
        const networkParams = new NetworkParams(JSON.parse(networkParamsFile.toString()));
  
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

        // Start building the transaction
        const tx = new Tx();

        // Add the UTXO as inputs
        tx.addInput(utxo);

        // Add the thread token minting script as a witness to the transaction
        tx.attachScript(compiledTTMintScript);

        // Construct a default minting redeemer
        const mintRedeemer = new ConstrData(0, []);

        // Construct the thread token quantity of 1
        const mintTokens = [[threadTokenName, BigInt(1)]];

        // Add the mint to the tx
        tx.mintTokens(
            threadTokenMPH,
            mintTokens,
            mintRedeemer
        )

        const inlineDatum = Datum.inline(datum);
        const outputValue = new Value(minAda, new Assets([
                                [threadTokenMPH, [
                                    [threadTokenName, BigInt(1)]
                                ]]
                                ]));

        // send Ada, updated dautm and thread token to the script address
        tx.addOutput(new TxOutput(lcValAddr, outputValue, inlineDatum));

        // Attached the output with the reference output
        tx.addOutput(new TxOutput(
            lcValAddr,
            new Value(lovelaceQty),
            null,
            compiledValScript
            ));

        tx.addSigner(PubKeyHash.fromHex(process.env.OWNER_PKH));

        console.log("before tx.finalize", tx.dump());
        // Send any change back to the buyer
        await tx.finalize(networkParams, ownerAddr, [colUtxo]);
        console.log("after tx.finalize", tx.dump());

        // Add the signature from the server side private key
        // This way, we lock the transaction now and then need
        // the end user to sign the tx.
        const txSigned = await signTx(tx);
        const txId = await submitTx(txSigned);

        console.log("txId", txId);
        console.log("lcValAddr:", lcValAddr);

    } catch (err) {
        const returnObj = {
            status: 500
        }
        var timestamp = new Date().toISOString();
        console.error(timestamp);
        console.error("init-tx: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}

main();


  
