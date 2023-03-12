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

export { tokenCount };

// Get the number of tokens in a set of utxo for a given mph
const tokenCount = async (tokenMph, utxos) => {
let tokenCount = BigInt(0);
for (const utxo of utxos) {
    const mphs = utxo.value.assets.mintingPolicies;
    for (const mph of mphs) {
    if (mph.hex == tokenMph) {
        const tokenNames = utxo.value.assets.getTokenNames(mph);
        for (const tokenName of tokenNames) {
        tokenCount += utxo.value.assets.get(mph, tokenName);
        }
    }
    }
}
return tokenCount;
}