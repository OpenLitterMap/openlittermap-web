let Supercluster = require('supercluster');

const fs = require('fs');
const prefix = process.argv.slice(2)[0]; // '/home/forge/openlittermap.com'; or '/home/vagrant/Code/olm';
const x = '/storage/app/data/features.json';
const file = prefix + x;

const zoom = process.argv.slice(3)[0];

let rawdata = fs.readFileSync(file);
let features = JSON.parse(rawdata);

let index = new Supercluster({
    log: true,
    radius: 40,
    maxZoom: 16
});

index.load(features);

const bbox = [-180, -85, 180, 85];
let clusters = index.getClusters(bbox, zoom);

fs.writeFile(prefix + '/storage/app/data/clusters.json', JSON.stringify(clusters), function (err, data) {
    if (err) return console.log(err);
});
