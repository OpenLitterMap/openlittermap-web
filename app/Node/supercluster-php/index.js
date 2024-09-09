import Supercluster from 'supercluster';
import fs from 'fs/promises';
import process from 'process';

// Correct the slicing of process.argv to correctly assign values
const prefix = process.argv[2]; // '/home/forge/openlittermap.com' or '/home/vagrant/Code/olm';
const zoom = parseInt(process.argv[3]); // Ensure zoom is a number
const featuresFilename = process.argv[4] || 'features.json';
const clustersFilename = process.argv[5] || 'clusters.json';
const storagePath = '/storage/app/data/';

async function generateClusters() {
    try {
        const file = `${prefix}${storagePath}${featuresFilename}`;

        // Read and parse the feature data file
        const rawData = await fs.readFile(file);
        const features = JSON.parse(rawData);

        // Set up the Supercluster with configuration
        const index = new Supercluster({
            log: true,
            radius: 80,
            maxZoom: 16,
            minPoints: 1
        });

        // Load features into the Supercluster
        index.load(features);

        // Define the bounding box for the clusters
        const bbox = [-180, -85, 180, 85];
        const clusters = index.getClusters(bbox, zoom);

        // Write the clusters data to a file
        const clustersFilePath = `${prefix}${storagePath}${clustersFilename}`;
        await fs.writeFile(clustersFilePath, JSON.stringify(clusters));
        console.log('Clusters written successfully.');
    } catch (err) {
        console.error('Error:', err);
    }
}

generateClusters();
