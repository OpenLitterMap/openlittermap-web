import { fetchLittercoinInfo } from "./info.mjs";

/**
 * Main calling function via the command line
 * Usage: node info.mjs 
 */
const main = async () => {

    try {
        const output = await fetchLittercoinInfo();
        const returnObj = {
            status: 200,
            payload: output
        }
        process.stdout.write(JSON.stringify(returnObj));
    
    } catch (err) {
        const returnObj = {
            status: 500
        }
        process.stdout.write(JSON.stringify(returnObj));
        console.error("info error: ", err);
    }
}

main();




