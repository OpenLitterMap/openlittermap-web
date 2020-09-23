// 1.1 Create the map, center and zoom (added dynamically through MapController)
let map = L.map('map', {
    center: center_map, // center_map,
    zoom: map_zoom,
});

const date = new Date();
const year = date.getFullYear();

// data from MapController@getCity
// console.log(litterGeojson);

// 1.2 Add tiles, attribution, set limits
let mapLink = '<a href="http://openstreetmap.org">OpenStreetMap</a>';
L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
    maxZoom: 20,
    minZoom: 1,
    // todo: maxBounds: bounds -> import from MapController -> not yet configured
}).addTo(map);

map.attributionControl.addAttribution('Litter data &copy; OpenLitterMap & Contributors ' + year);

/*
 * Step 2 - Add hex / shapefile grid
 */

// 2.1 Characterize hex grid
var cellWidth = hex;
var units = 'meters';

// 2.1.1 create a bounding box from a set of features
var bbox = turf.bbox(litterGeojson);
// also turf.bboxPolygon

// Needs to be filtered to only display hex that has data
var hexgrid = turf.hexGrid(bbox, cellWidth, units);
// console.log(bbox);
// console.log(cellWidth);
// console.log(units);
// L.geoJson(hexgrid).addTo(map); // full extent of bounding boxm unfiltered

// 2.1.2 Filter Polygons by counting hex values with point in polygon and removing 0 values

// [1. polygons, 2. points, 3. ["Item" to count], 4. [attach 'values' to the hex input]]
// this annotates the input variable (eg. adminZone, hexgrid) with a new property Array(n) "values" counting "Item"
// hexgrid will get an extra array, "values" with counts of "Item" that fall within the polygon of input parameter
var aggregate = turf.collect(hexgrid, litterGeojson, 'total_litter', 'values');
// console.log(aggregate); // size of grid = 100
// console.log(aggregate.features);
// console.log(aggregate.features[0]);
// var aggredatedValues = aggregate.features[0].properties.values; // array of hex grid values
// console.log(aggredatedValues);

// 2.2 Filter Hex Grid polygons
// only show the zones where data exists
var hexFiltered = L.geoJson(aggregate, {
    style: style,
    onEachFeature: onEachFeature,
    filter: function(feature, layer) {
        if (feature.properties.values.length > 0) {
            var sum = 0;
            for(var i=0; i < feature.properties.values.length; i++) {
                sum += feature.properties.values[i]
            }
            feature.properties.total = sum;
        }
        return feature.properties.values.length > 0;
    }
}).addTo(map);
// console.log('hex filtered');
// console.log(hexFiltered);

// 2.3: Style admin polygons
function getColor(n) {
    return n > 60 ? '#800026' :
           n > 20 ? '#BD0026' :
           n > 10 ? '#E31A1C' :
           n > 4  ? '#FD8D3C' :
           n > 2  ? '#FED976' :
                    '#FFEDA0';
    }

function style (feature) {
    return {
        weight: 2,
        opacity: 1,
        color: 'white',
        dashArray: '3',
        fillOpacity: 0.7,
        fillColor: getColor(feature.properties.total)
    };
}

// 2.4 Add legend and div display
var info = L.control();

info.onAdd = function (map) {
    this._div = L.DomUtil.create('div', 'info');
    this.update();
    return this._div;
};

// 2.5 Get Counts
info.update = function (props) {
    this._div.innerHTML = '<h4>' + hex + ' meter hex grids</h4>' +  (props ?
        '<b>Hover over to count'+'</b><br />' + props.total + ' pieces of litter'
        : 'Hover over polygons to count.');
};
info.addTo(map);

function highlightFeature (e) {

    var layer = e.target;

    layer.setStyle({
        weight: 5,
        color: '#666',
        dashArray: '',
        fillOpacity: 0.7
    });

    if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
        layer.bringToFront();
    }

    info.update(layer.feature.properties);
}

var geojson;

function resetHighlight(e) {
    hexFiltered.resetStyle(e.target);
    info.update();
}

for (var x in hexFiltered["_layers"]) {
    hexFiltered.resetStyle(hexFiltered["_layers"][x]);
};
info.update();

function zoomToFeature(e) {
    map.fitBounds(e.target.getBounds());
}

function onEachFeature(feature, layer) {
    layer.on({
        mouseover: highlightFeature,
        mouseout: resetHighlight,
        click: zoomToFeature
    });
}

// Style the legend
// needs to dynamically and statiscally significantly reflect input variables ie. function of zones + needles
var legend = L.control({ position: 'bottomleft' });

legend.onAdd = function (map) {

    var div = L.DomUtil.create('div', 'info legend'),
        grades = [1, 3, 6, 10, 20],
        labels = [],
        from, to;

    for (var i = 0; i < grades.length; i++) {
        from = grades[i];
        to = grades[i + 1];

        labels.push(
            '<i style="background:' + getColor(from + 1) + '"></i> ' +
            from + (to ? '&ndash;' + to : '+'));
    }

    div.innerHTML = labels.join('<br>');
    return div;
};

legend.addTo(map);

var smokingGroup = new L.LayerGroup();
var foodGroup = new L.LayerGroup();
var coffeeGroup = new L.LayerGroup();
var alcoholGroup = new L.LayerGroup();
// var drugsGroup = new L.LayerGroup();
var softdrinksGroup = new L.LayerGroup().addTo(map);
var sanitaryGroup = new L.LayerGroup();
var otherGroup = new L.LayerGroup();
var coastalGroup = new L.LayerGroup();
// var pathwayGroup = new L.LayerGroup();
// var artGroup = new L.LayerGroup();
var brandsGroup = new L.LayerGroup();
var dogshitGroup = new L.LayerGroup();
var dumpingGroup = new L.LayerGroup();
var industrialGroup = new L.LayerGroup();
// var trashdogGroup = new L.LayerGroup();

var lat;
var lon;

// this needs to become a dynamic function
// console.log(litterGeojson["features"]);
// for each feature (Object with geometry, properties)
for (var i = 0; i < litterGeojson["features"].length; i++ )
{
    // Extract coordinates
    lat = litterGeojson["features"][i]["geometry"]["coordinates"][1];
    lon = litterGeojson["features"][i]["geometry"]["coordinates"][0];

    if (litterGeojson["features"][i]["properties"]["fullname"]) {
        var userfullname = litterGeojson["features"][i]["properties"]["fullname"];
    } else {
        var userfullname = "";
    }
    if (litterGeojson["features"][i]["properties"]["username"]) {
       var userName = "@" + litterGeojson["features"][i]["properties"]["username"];
    } else {
        var userName = "";
    }

    if (userfullname == "" && userName == "") {
        userfullname = "Anonymous";
    }

    // Extract Features
    // Check if smoking exists and add to smoking object for map toggle
    // then check if anything else exists on the image and add it into smoking
    // repeat for all categories, for each image :-/
    if (litterGeojson["features"][i]["properties"]["smoking"])
    {
        // console.log('smoking is not null');
        var smoke = litterGeojson["features"][i]["properties"]["smoking"];
        var smokingString = '';
        if (smoke['butts']) {
            smokingString += 'Cigarette Butts: ' + smoke['butts'];
        }
        if (smoke['lighters']) {
            smokingString += '<br>Lighters: ' + smoke['lighters'];
        }
        if (smoke['cigaretteBox']) {
            smokingString += '<br>Cigarette Box: ' + smoke['cigaretteBox'];
        }
        if (smoke['skins']) {
            smokingString += '<br>Rolling Papers: ' + smoke['skins'];
        }
        if (smoke['tobaccoPouch']) {
            smokingString += '<br>Tobacco Pouch: ' + smoke['tobaccoPouch'];
        }
        if (smoke['plastic']) {
            smokingString += '<br>Plastic Packaging: ' + smoke['plastic'];
        }
        if (smoke['filters']) {
            smokingString += '<br>Filters: ' + smoke['filters'];
        }
        if (smoke['filterbox']) {
            smokingString += '<br>Filters Box: ' + smoke['filterbox'];
        }
        if (smoke['smokingOther']) {
            smokingString += '<br>Smoking (other): ' + smoke['smokingOther'];
        }
        L.marker([lat, lon]).addTo(smokingGroup).bindPopup(smokingString
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    if (litterGeojson["features"][i]["properties"]["food"]) {

        // console.log('food is not null');
        var foodString = '';
        var food = litterGeojson["features"][i]["properties"]["food"];
        if (food['sweetWrappers']) {
            foodString += '<br>Sweet Wrappers: ' + food['sweetWrappers'];
        }
        if (food['paperFoodPackaging']) {
            foodString += '<br>Paper/Card Food Packaging: ' + food['paperFoodPackaging'];
        }
        if (food['plasticFoodPackaging']) {
            foodString += '<br>Plastic Food Packaging: ' + food['plasticFoodPackaging'];
        }
        if (food['plasticCutlery']) {
            foodString += '<br>Plastic Cutlery: ' + food['plasticCutlery'];
        }
        if (food['crisp_small']) {
            foodString += '<br>Packet Crisps/Chips (small): ' + food['crisp_small'];
        }
        if (food['crisp_large']) {
            foodString += '<br>Packet Crisps/Chips (large): ' + food['crisp_large'];
        }
        if (food['styrofoam_plate']) {
            foodString += '<br>Styrofoam Plate: ' + food['styrofoam_plate'];
        }
        if (food['napkins']) {
            foodString += '<br>Napkins: ' + food['napkins'];
        }
        if (food['sauce_packet']) {
            foodString += '<br>Sauce Packet: ' + food['sauce_packet'];
        }
        if (food['glass_jar']) {
            foodString += '<br>Glass Jar: ' + food['glass_jar'];
        }
        if (food['glass_jar_lid']) {
            foodString += '<br>Glass Jar Lid: ' + food['glass_jar_lid'];
        }
        if (food['foodOther']) {
            foodString += '<br>Other (food): ' + food['foodOther'];
        }

        L.marker([lat, lon]).addTo(foodGroup).bindPopup(foodString +'<br>'
           + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    var coffeeString = '';
    if (litterGeojson["features"][i]["properties"]["coffee"]) {
        // there is also food
        // extractFood();
        var coffee = litterGeojson["features"][i]["properties"]["coffee"];
        if (coffee['coffeeCups']) {
            coffeeString += '<br>Coffee Cups: ' + coffee['coffeeCups'];
        }
        if (coffee['coffeeLids']) {
            coffeeString += '<br>Coffee Lids: ' + coffee['coffeeLids'];
        }
        if (coffee['coffeeOther']) {
            coffeeString += '<br>Other (coffee): ' + coffee['coffeeOther'];
        }

        L.marker([lat, lon]).addTo(coffeeGroup).bindPopup(coffeeString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    // check if the image with smoking has anything else
    var alcoholString = '';
    if (litterGeojson["features"][i]["properties"]["alcohol"]) {
        // there is also alcohol
        // extractAlcohol();
        var alcohol = litterGeojson["features"][i]["properties"]["alcohol"];
        if (alcohol['beerBottle']) {
            alcoholString += '<br>Beer Bottles: ' + alcohol['beerBottle'];
        }
        if (alcohol['beerCan']) {
            alcoholString += '<br>Beer Can: ' + alcohol['beerCan'];
        }
        if (alcohol['bottleTops']) {
            alcoholString += '<br>Bottle Tops: ' + alcohol['bottleTops'];
        }
        if (alcohol['brokenGlass']) {
            alcoholString += '<br>Broken Glass: ' + alcohol['brokenGlass'];
        }
        if (alcohol['paperCardAlcoholPackaging']) {
            alcoholString += '<br>Paper Card Alcohol Packaging: ' + alcohol['paperCardAlcoholPackaging'];
        }
        if (alcohol['plasticAlcoholPackaging']) {
            alcoholString += '<br>Plastic Alcohol Packaging: ' + alcohol['plasticAlcoholPackaging'];
        }
        if (alcohol['spiritBottle']) {
            alcoholString += '<br>Spirit Bottles: ' + alcohol['spiritBottle'];
        }
        if (alcohol['wineBottle']) {
            alcoholString += '<br>Wine Bottles: ' + alcohol['wineBottle'];
        }
        if (alcohol['alcoholOther']) {
            alcoholString += '<br>Other (alcohol): ' + alcohol['alcoholOther'];
        }
        L.marker([lat, lon]).addTo(alcoholGroup).bindPopup(alcoholString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    var softdrinksString = '';
    if (litterGeojson["features"][i]["properties"]["softdrinks"]) {
        // there is also softdrinks
        // extractsoftdrinks();
        var softdrinks = litterGeojson["features"][i]["properties"]["softdrinks"];

        if (softdrinks['waterBottle']) {
            softdrinksString += '<br>Plastic Bottle (Water): ' + softdrinks['waterBottle'];
        }
        if (softdrinks['bottleLid']) {
            softdrinksString += '<br>Plastic Bottle Lid: ' + softdrinks['bottleLid'];
        }
        if (softdrinks['fizzyDrinkBottle']) {
            softdrinksString += '<br>Plastic fizzy drink bottle: ' + softdrinks['fizzyDrinkBottle'];
        }
        if (softdrinks['bottleLabel']) {
            softdrinksString += '<br>Plastic bottle label: ' + softdrinks['bottleLabel'];
        }
        if (softdrinks['tinCan']) {
            softdrinksString += '<br>Tin Can: ' + softdrinks['tinCan'];
        }
        if (softdrinks['sportsDrink']) {
            softdrinksString += '<br>Sports Drink: ' + softdrinks['sportsDrink'];
        }
        if (softdrinks['straws']) {
            softdrinksString += '<br>Straws: ' + softdrinks['straws'];
        }
        if(softdrinks['plastic_cups']) {
            softdrinksString += '<br>Plastic Cups: ' + softdrinks['plastic_cups'];
        }
        if(softdrinks['plastic_cup_tops']) {
            softdrinksString += '<br>Plastic cup tops: ' + softdrinks['plastic_cup_tops'];
        }
        if(softdrinks['milk_bottle']) {
            softdrinksString += '<br>Milk bottle: ' + softdrinks['milk_bottle'];
        }
        if(softdrinks['milk_carton']) {
            softdrinksString += '<br>Milk Carton: ' + softdrinks['milk_carton'];
        }
        if(softdrinks['paper_cups']) {
            softdrinksString += '<br>Paper Cups: ' + softdrinks['paper_cups'];
        }
        if(softdrinks['juice_cartons']) {
            softdrinksString += '<br>Juice Cartons: ' + softdrinks['juice_cartons'];
        }
        if(softdrinks['juice_bottles']) {
            softdrinksString += '<br>Juice Bottles: ' + softdrinks['juice_bottles'];
        }
        if(softdrinks['juice_packet']) {
            softdrinksString += '<br>Juice Packets: ' + softdrinks['juice_packet'];
        }
        if(softdrinks['ice_tea_bottles']) {
            softdrinksString += '<br>Ice Tea Bottles: ' + softdrinks['ice_tea_bottles'];
        }
        if(softdrinks['ice_tea_can']) {
            softdrinksString += '<br>Ice Tea Cans: ' + softdrinks['ice_tea_can'];
        }
        if(softdrinks['energy_can']) {
            softdrinksString += '<br>Energy Can: ' + softdrinks['energy_can'];
        }
        if(softdrinks['styro_cup']) {
            softdrinksString += '<br>Styrofoam Cup: ' + softdrinks['styro_cup'];
        }
        if (softdrinks['softDrinkOther']) {
            softdrinksString += '<br>Other (soft drink): ' + softdrinks['softDrinkOther'];
        }
        L.marker([lat, lon]).addTo(softdrinksGroup).bindPopup(softdrinksString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    // var drugsString = '';
    // // check if the image with smoking contains soft drinks
    // if (litterGeojson["features"][i]["properties"]["drugs"]) {
    //     // there is also drugs
    //     // extractDrugs();
    //     var drugs = litterGeojson["features"][i]["properties"]["drugs"];

    //     if (drugs['needles']) {
    //         drugsString += '<br>Needles ' + drugs['needles'];
    //     }

    //     if (drugs['wipes']) {
    //         drugsString += '<br>Citric Acid Wipes: ' + drugs['wipes'];
    //     }

    //     if (drugs['tops']) {
    //         drugsString += '<br>Needle Tops: ' + drugs['tops'];
    //     }

    //     if (drugs['packaging']) {
    //         drugsString += '<br>Needle Packaging: ' + drugs['packaging'];
    //     }

    //     if (drugs['waterbottle']) {
    //         drugsString += '<br>Sterile water bottle: ' + drugs['waterbottle'];
    //     }

    //     if (drugs['spoons']) {
    //         drugsString += '<br>Metal spoons: ' + drugs['spoons'];
    //     }

    //     if (drugs['needlebin']) {
    //         drugsString += '<br>Needle bin: ' + drugs['needlebin'];
    //     }

    //     if (drugs['usedtinfoil']) {
    //         drugsString += '<br>Tinfoil: ' + drugs['usedtinfoil'];
    //     }

    //     if (drugs['barrels']) {
    //         drugsString += '<br>Empty Syringe Barrels: ' + drugs['barrels'];
    //     }

    //     if (drugs['fullpackage']) {
    //         drugsString += '<br>Full package: ' + drugs['fullpackage'];
    //     }

    //     if(drugs['baggie']) {
    //         drugsString += '<br>Baggie: ' + drugs['baggie'];
    //     }

    //     if(drugs['crack_pipes']) {
    //         drugsString += '<br>Crack Pipes: ' + drugs['crack_pipes'];
    //     }

    //     if (drugs['drugsOther']) {
    //         drugsString += '<br>Other (drugs): ' + drugs['drugsOther'];
    //     }
    //     L.marker([lat, lon]).addTo(drugsGroup).bindPopup(drugsString +'<br>'
    //         + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
    //         + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
    //         + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
    //         + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
    //         + '<p>By: ' + userfullname + userName+'</p>'
    //     );
    // }

    var sanitaryString = '';
    // check if the image with smoking contains soft drinks
    if (litterGeojson["features"][i]["properties"]["sanitary"]) {
        // there is also sanitary
        // extractSanitary();
        var sanitary = litterGeojson["features"][i]["properties"]["sanitary"];

        if (sanitary['condoms']) {
            sanitaryString += '<br>Condoms: ' + sanitary['condoms'];
        }
        if (sanitary['nappies']) {
            sanitaryString += '<br>Nappies: ' + sanitary['nappies'];
        }
        if (sanitary['menstral']) {
            sanitaryString += '<br>Menstral: ' + sanitary['menstral'];
        }
        if (sanitary['deodorant']) {
            sanitaryString += '<br>Deodorant: ' + sanitary['deodorant'];
        }
        if (sanitary['ear_swabs']) {
            sanitaryString += '<br>Ear Swabs: ' + sanitary['ear_swabs'];
        }
        if (sanitary['tooth_pick']) {
            sanitaryString += '<br>Tooth Pick: ' + sanitary['tooth_pick'];
        }
        if (sanitary['tooth_brush']) {
            sanitaryString += '<br>Tooth Brush: ' + sanitary['tooth_brush'];
        }
        if (sanitary['sanitaryOther']) {
            sanitaryString += '<br>Other (sanitary): ' + sanitary['sanitaryOther'];
        }
        L.marker([lat, lon]).addTo(sanitaryGroup).bindPopup(sanitaryString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    var otherString = '';
    var dogshitString = '';
    // check if the image with smoking contains soft drinks
    if (litterGeojson["features"][i]["properties"]["other"]) {
        // there is also other
        // extractSanitary();
        var other = litterGeojson["features"][i]["properties"]["other"];
        if (other['dogshit']) {
            // otherString += '<br>Dogshit: ' + other['dogshit'];
            dogshitString += '<br>Pet Surprise: ' + other['dogshit'];
            L.marker([lat, lon]).addTo(dogshitGroup).bindPopup(dogshitString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
        }
        if (other['dump']) {
            otherString += '<br>Random Dump: ' + other['dump'];
        }
        if (other['plastic']) {
            otherString += '<br>Random plastic: ' + other['plastic'];
        }
        if (other['metal']) {
            otherString += '<br>Metal object: ' + other['metal'];
        }
        if (other['batteries']) {
            otherString += '<br>Batteries: ' + other['batteries'];
        }
        if (other['elec_small']) {
            otherString += '<br>Electrical item (small): ' + other['elec_small'];
        }
        if (other['elec_large']) {
            otherString += '<br>Electrical item (large): ' + other['elec_large'];
        }
        if (other['plastic_bags']) {
            otherString += '<br>Plastic Bags: ' + other['plastic_bags'];
        }
        if (other['election_posters']) {
            otherString += '<br>Election Posters: ' + other['election_posters'];
        }
        if (other['forsale_posters']) {
            otherString += '<br>For Sale Posters: ' + other['forsale_posters'];
        }
        if (other['books']) {
            otherString += '<br>Books: ' + other['books'];
        }
        if (other['magazine']) {
            otherString += '<br>Magazines: ' + other['magazine'];
        }
        if (other['paper']) {
            otherString += '<br>Paper: ' + other['paper'];
        }
        if (other['stationary']) {
            otherString += '<br>Stationary: ' + other['stationary'];
        }
        if (other['washing_up']) {
            otherString += '<br>Washing-up bottle: ' + other['washing_up'];
        }
        if (other['hair_tie']) {
            otherString += '<br>Hair Tie: ' + other['hair_tie'];
        }
        if (other['ear_plugs']) {
            otherString += '<br>Ear Plugs (music): ' + other['ear_plugs'];
        }
        if (other['other']) {
            otherString += '<br>Unidentified item: ' + other['other'];
        }
        L.marker([lat, lon]).addTo(otherGroup).bindPopup(otherString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    var coastalString = '';
    if(litterGeojson["features"][i]["properties"]["coastal"]) {
        var coastal = litterGeojson["features"][i]["properties"]["coastal"];

        if(coastal['microplastics']) {
            coastalString += '<br>Micro-plastics :' + coastal['microplastics'];
        }
        if(coastal['mediumplastics']) {
            coastalString += '<br>Medium-plastics: ' + coastal['mediumplastics'];
        }
        if(coastal['macroplastics']) {
            coastalString += '<br>Macro-plastics: ' + coastal['marcoplastics'];
        }
        if(coastal['rope_small']) {
            coastalString += '<br>Rope (small): ' + coastal['rope_small'];
        }
        if(coastal['rope_medium']) {
            coastalString += '<br>Rope (medium): ' + coastal['rope_medium'];
        }
        if(coastal['rope_large']) {
            coastalString += '<br>Rope (large): ' + coastal['rope_large'];
        }
        if(coastal['fishing_gear_nets']) {
            coastalString += '<br>Fishing Gear/Nets: ' + coastal['fishing_gear_nets'];
        }
        if(coastal['buoys']) {
            coastalString += '<br>Buoys: ' + coastal['buoys'];
        }
        if(coastal['degraded_plasticbottle']) {
            coastalString += '<br>Degraded Plastic Bottle: ' + coastal['degraded_plasticbottle'];
        }
        if(coastal['degraded_plasticbag']) {
            coastalString += '<br>Degraded Plastic Bag: ' + coastal['degraded_plasticbag'];
        }
        if(coastal['degraded_straws']) {
            coastalString += '<br>Degraded Straws: ' + coastal['degraded_straws'];
        }
        if(coastal['degraded_lighters']) {
            coastalString += '<br>Degraded Lighters: ' + coastal['degraded_lighters'];
        }
        if(coastal['balloons']) {
            coastalString += '<br>Ballons: ' + coastal['balloons'];
        }
        if(coastal['lego']) {
            coastalString += '<br>Lego: ' + coastal['lego'];
        }
        if(coastal['shotgun_cartridges']) {
            coastalString += '<br>Shotgun Cartridges: ' + coastal['shotgun_cartridges'];
        }
        if(coastal['styro_small']) {
            coastalString += '<br>Styrofoam small: ' + coastal['styro_small'];
        }
        if(coastal['styro_medium']) {
            coastalString += '<br>Styrofoam medium: ' + coastal['styro_medium'];
        }
        if(coastal['styro_large']) {
            coastalString += '<br>Styrofoam large: ' + coastal['styro_large'];
        }
        if(coastal['coastal_other']) {
            coastalString += '<br>Coastal (other): ' + coastal['coastal_other'];
        }
        L.marker([lat, lon]).addTo(coastalGroup).bindPopup(coastalString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    // var pathwayString = '';
    // if(litterGeojson["features"][i]["properties"]["pathway"]) {
    //     var pathway = litterGeojson["features"][i]["properties"]["pathway"];

    //     if(pathway['gutter']) {
    //         pathwayString += '<br>Gutter: ' + pathway['gutter'];
    //     }
    //     if(pathway['gutter_long']) {
    //         pathwayString += '<br>Long Gutter: ' + pathway['gutter_long'];
    //     }
    //     if(pathway['kerb_hole_small']) {
    //         pathwayString += '<br>Small Kerb Hole: ' + pathway['kerb_hole_small'];
    //     }
    //     if(pathway['kerb_hole_large']) {
    //         pathwayString += '<br>Large Kerb Hole: ' + pathway['kerb_hole_large'];
    //     }
    //     if(pathway['pathwayOther']) {
    //         pathwayString += '<br>Pathway (other): ' + pathway['pathwayOther'];
    //     }
    //     L.marker([lat, lon]).addTo(pathwayGroup).bindPopup(pathwayString +'<br>'
    //         + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
    //         + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
    //         + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
    //         + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
    //         + '<p>By: ' + userfullname + userName+'</p>'
    //     );
    // }

    // var artString = '';
    // if(litterGeojson["features"][i]["properties"]["art"]) {
    //     var art = litterGeojson["features"][i]["properties"]["art"];

    //     if(art['item']) {
    //         artString += '<br>Art: ' + art['item'];
    //     }
    //     L.marker([lat, lon]).addTo(artGroup).bindPopup(artString +'<br>'
    //         + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
    //         + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
    //         + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
    //         + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
    //         + '<p>By: ' + userfullname + userName+'</p>'
    //     );
    // }

    // var trashdogString = '';
    // if(litterGeojson["features"][i]["properties"]["trashdog"]) {
    //     var trashdog = litterGeojson["features"][i]["properties"]["trashdog"];

    //     if(trashdog['trashdog']) {
    //         trashdogString += '<br>TrashDog!';
    //     }
    //     L.marker([lat, lon]).addTo(trashdogGroup).bindPopup(trashdogString +'<br>'
    //         + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
    //         + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
    //         + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
    //         + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
    //         + '<p>By: ' + userfullname + userName+'</p>'
    //     );
    // }

    var brandsString = '';
    if (litterGeojson["features"][i]["properties"]["brands"])
    {
        var brands = litterGeojson["features"][i]["properties"]["brands"];

        if(brands['adidas']) {
            brandsString += '<br>Adidas: ' + brands['adidas'];
        }
        if(brands['amazon']) {
            brandsString += '<br>Amazon: ' + brands['amazon'];
        }
        if(brands['apple']) {
            brandsString += '<br>Apple: ' + brands['apple'];
        }
        if(brands['budweiser']) {
            brandsString += '<br>Budweiser: ' + brands['budweiser'];
        }
        if(brands['camel']) {
            brandsString += '<br>Camel: ' + brands['camel'];
        }
        if(brands['coke']) {
            brandsString += '<br>Coca-Cola: ' + brands['coke'];
        }
        if(brands['colgate']) {
            brandsString += '<br>Colgate: ' + brands['colgate'];
        }
        if(brands['corona']) {
            brandsString += '<br>Corona: ' + brands['corona'];
        }

        if (brands['doritos'])
        {
            brandsString += '<br>Doritos: ' + brands['doritos'];
        }

        if(brands['fritolay']) {
            brandsString += '<br>Frito-Lay: ' + brands['fritolay'];
        }
        if(brands['gillette']) {
            brandsString += '<br>Gillette: ' + brands['gillette'];
        }
        if(brands['heineken']) {
            brandsString += '<br>Heineken: ' + brands['heineken'];
        }
        if(brands['kellogs']) {
            brandsString += '<br>Kellogs: ' + brands['kellogs'];
        }
        if(brands['lego']) {
            brandsString += '<br>Lego: ' + brands['lego'];
        }
        if(brands['loreal']) {
            brandsString += '<br>Loreal: ' + brands['loreal'];
        }
        if(brands['nescafe']) {
            brandsString += '<br>Nescafé: ' + brands['nescafe'];
        }
        if(brands['nestle']) {
            brandsString += '<br>Nestlé: ' + brands['nestle'];
        }
        if(brands['marlboro']) {
            brandsString += '<br>Marlboro: ' + brands['marlboro'];
        }
        if(brands['mcdonalds']) {
            brandsString += '<br>McDonalds: ' + brands['mcdonalds'];
        }
        if(brands['nike']) {
            brandsString += '<br>Nike: ' + brands['nike'];
        }
        if(brands['pepsi']) {
            brandsString += '<br>Pepsi: ' + brands['pepsi'];
        }
        if(brands['redbull']) {
            brandsString += '<br>RedBull: ' + brands['redbull'];
        }
        if(brands['samsung']) {
            brandsString += '<br>Samsung: ' + brands['samsung'];
        }
        if(brands['subway']) {
            brandsString += '<br>Subway: ' + brands['subway'];
        }
        if(brands['starbucks']) {
            brandsString += '<br>Starbucks: ' + brands['starbucks'];
        }
        if(brands['tayto']) {
            brandsString += '<brTayto: ' + brands['tayto'];
        }

        L.marker([lat, lon]).addTo(brandsGroup).bindPopup(brandsString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName+'</p>'
        );
    }

    var dumpingString = '';
    if (litterGeojson["features"][i]["properties"]["dumping"])
    {
        var dumping = litterGeojson["features"][i]["properties"]["dumping"];
        if (dumping['small']) {
            dumpingString += '<br>Dumping (small): ' + dumping['small'];
        }
        if (dumping['medium']) {
            dumpingString += '<br>Dumping (medium): ' + dumping['medium'];
        }
        if (dumping['large']) {
            dumpingString += '<br>Dumping (large): ' + dumping['large'];
        }

        L.marker([lat, lon]).addTo(dumpingGroup).bindPopup(dumpingString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName + '</p>'
        );
    }

    var industrialString = '';
    if (litterGeojson["features"][i]["properties"]["industrial"])
    {
        var industrial = litterGeojson["features"][i]["properties"]["industrial"];
        if (industrial['oil']) {
            industrialString += '<br>Oil: ' + industrial['oil'];
        }
        if (industrial['chemical']) {
            industrialString += '<br>Chemical: ' + industrial['chemical'];
        }
        if (industrial['plastic']) {
            industrialString += '<br>Plastic: ' + industrial['plastic'];
        }
        if (industrial['bricks']) {
            industrialString += '<br>Bricks: ' + industrial['bricks'];
        }
        if (industrial['tape']) {
            industrialString += '<br>Tape: ' + industrial['tape'];
        }
        if (industrial['other']) {
            industrialString += '<br>Other: ' + industrial['other'];
        }

        L.marker([lat, lon]).addTo(dumpingGroup).bindPopup(industrialString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>'
            + '<p>By: ' + userfullname + userName + '</p>'
        );
    }

}


// 4.3 - Create overlays toggle menu
var overlays = {
    Alcohol: alcoholGroup,
    // Art: artGroup,
    Brands: brandsGroup,
    Coastal: coastalGroup,
    Coffee: coffeeGroup,
    Dumping: dumpingGroup,
    // Drugs: drugsGroup,
    Food: foodGroup,
    Industrial: industrialGroup,
    Other: otherGroup,
    // Pathway: pathwayGroup,
    PetSurprise: dogshitGroup,
    Sanitary: sanitaryGroup,
    Smoking: smokingGroup,
    SoftDrinks: softdrinksGroup,
    // TrashDog: trashdogGroup
};

// // 4.4 - Add null basemaps and overlays to the map
L.control.layers(null, overlays).addTo(map);

// Step 5: Timeslider
// var testlayer = smokingGroup;
// console.log(testlayer);
// var sliderControl = L.control.sliderControl({position: "bottomleft", layer: testlayer, range: true});
// console.log('sliderControl');
// console.log(sliderControl);
// // map.addControl(sliderControl);

// //And initialize the slider
// sliderControl.startSlider();

// $('#slider-timestamp').html(options.markers[ui.value].feature.properties.time.substr(0, 19));
