let map = L.map('map', {
    center: [0,0],
    zoom: 2,
});

const date = new Date();
const year = date.getFullYear();

// Add tiles, attribution, set limits
const mapLink = '<a href="http://openstreetmap.org">OpenStreetMap</a>';
L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
    maxZoom: 18,
    minZoom: 2,
}).addTo(map);
map.attributionControl.addAttribution('Litter data &copy OpenLitterMap & Contributors ' + year);

// Create groups
// let artGroup = new L.LayerGroup();

// Create clusters object
let clusters = L.markerClusterGroup().addTo(map);

// Loop over points : todo, vectorize this with numpy integration
for (let i=0;i<litterGeojson["features"].length;i++)
{
    let lat = litterGeojson["features"][i]["geometry"]["coordinates"][1];
    let lon = litterGeojson["features"][i]["geometry"]["coordinates"][0];

    let a = '';
    // Default global marker
    L.marker([lat, lon]).addTo(clusters).bindPopup(a + ' '
      + '<p>' + litterGeojson["features"][i]["properties"]["result_string"] + '</p>'
      + '<p>Taken on ' + moment(litterGeojson["features"][i]["properties"]["datetime"]).format('LLL') + '</p>'
      + '<img style="height: 100px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
      + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
    );

    // todo - Extract Specific Features (art)
    // let artString = '';
    // if(litterGeojson["features"][i]["properties"]["art"] != 'null') {
    //     L.marker([lat, lon]).addTo(artGroup).bindPopup('Litter Art!' + '<br>'
    //         + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + '</p>'
    //         + '<img style="height: 100px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
    //         + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>' 
    //     );
    // }
}

// todo - Add the cluster & overlays to the map
// let overlays = {
//     Global: clusters,
//     Art: artGroup,
// };

map.addLayer(clusters);

