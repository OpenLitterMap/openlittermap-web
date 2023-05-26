import { fetchLittercoinInfo } from "./lc-info.mjs";

/**
 * Main calling function via the command line
 * Usage: node get-info.mjs 
 * @params {}
 * @output {string} lcInfo
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
        console.error("get-lc-info: ", err);
        process.stdout.write(JSON.stringify(returnObj));
    }
}

main();




