let Supercluster = require('supercluster');

const fs = require('fs');
const prefix = process.argv.slice(2)[0]; // '/home/forge/openlittermap.com'; or '/home/vagrant/Code/olm';
const zoom = process.argv.slice(3)[0];
const featuresFilename = process.argv.slice(4)[0] || 'features.json';
const clustersFilename = process.argv.slice(5)[0] || 'clusters.json';
const storagePath = '/storage/app/data/';

const file = prefix + storagePath + featuresFilename;

let rawData = fs.readFileSync(file);
let features = JSON.parse(rawData);

let index = new Supercluster({
    log: true,
    radius: 40,
    maxZoom: 16
});

index.load(features);

const bbox = [-180, -85, 180, 85];
let clusters = index.getClusters(bbox, zoom);

fs.writeFile(prefix + storagePath + clustersFilename, JSON.stringify(clusters), function (err, data) {
    if (err) return console.log(err);
});
