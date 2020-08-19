// 1.1 Create the map, center and zoom (added dynamically through MapController)
let map = L.map('map', {
    center: center_map, // center_map,
    zoom: 14  // map_zoom,
});

// data from MapController@getCity
// console.log(litterGeojson);
// console.log(turf);

const date = new Date();
const year = date.getFullYear();

// 1.2 Add tiles, attribution, set limits
mapLink = '<a href="http://openstreetmap.org">OpenStreetMap</a>';
L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
    maxZoom: 18,
    minZoom: 1,
    // todo: maxBounds: bounds -> import from MapController -> not yet configured 
}).addTo(map);

map.attributionControl.addAttribution('Litter data &copy; OpenLitterMap & Contributors ' + year);

let smokingGroup = new L.LayerGroup().addTo(map);
let foodGroup = new L.LayerGroup().addTo(map);
let coffeeGroup = new L.LayerGroup().addTo(map);
let alcoholGroup = new L.LayerGroup().addTo(map);
let softdrinksGroup = new L.LayerGroup().addTo(map);
let sanitaryGroup = new L.LayerGroup().addTo(map);
let otherGroup = new L.LayerGroup().addTo(map);
let coastalGroup = new L.LayerGroup().addTo(map);
let artGroup = new L.LayerGroup().addTo(map);
let brandsGroup = new L.LayerGroup().addTo(map);

let lat;
let lon;

let userfullname = "";
let userName = "";

// this needs to become a dynamic function
for (let i = 0; i < litterGeojson["features"].length; i++ )
{
    // Extract coordinates
    lat = litterGeojson["features"][i]["geometry"]["coordinates"][1];
    lon = litterGeojson["features"][i]["geometry"]["coordinates"][0];

    if (litterGeojson["features"][i]["properties"]["fullname"]) {
        userfullname = "@" + litterGeojson["features"][i]["properties"]["fullname"];
    } else {
        userfullname = "";
    }
    if (litterGeojson["features"][i]["properties"]["username"]) {
       userName = litterGeojson["features"][i]["properties"]["username"];
    } else {
        userName = "";
    }

    if (userfullname == "" && userName == "") {
        userfullname == "Anonymous";
    }

    // Extract Features 
    // Check if smoking exists and add to smoking object for map toggle
    // then check if anything else exists on the image and add it into smoking 
    // repeat for all categories, for each image :-/
    if (litterGeojson["features"][i]["properties"]["smoking"])
    {
        // console.log('smoking is not null');
        let smoke = litterGeojson["features"][i]["properties"]["smoking"];
        let smokingString = '';
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
        if (smoke['smoking_plastic']) {
            smokingString += '<br>Plastic Packaging: ' + smoke['smoking_plastic'];
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
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }
        
    if (litterGeojson["features"][i]["properties"]["food"])
    {
        // console.log('food is not null');
        let foodString = '';
        let food = litterGeojson["features"][i]["properties"]["food"];
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
        if (food['pizza_box']) {
            foodString += '<br>Pizza Box: ' + food['pizza_box'];
        }
        if (food['aluminium_foil']) {
            foodString += '<br>Tinfoil: ' + food['tinfoil'];
        }

        L.marker([lat, lon]).addTo(foodGroup).bindPopup(foodString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>' 
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let coffeeString = '';
    if (litterGeojson["features"][i]["properties"]["coffee"])
    {
        let coffee = litterGeojson["features"][i]["properties"]["coffee"];
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
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let alcoholString = '';
    if (litterGeojson["features"][i]["properties"]["alcohol"])
    {
        let alcohol = litterGeojson["features"][i]["properties"]["alcohol"];
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
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let softdrinksString = '';
    if (litterGeojson["features"][i]["properties"]["softdrinks"])
    {
        let softdrinks = litterGeojson["features"][i]["properties"]["softdrinks"];

        if (softdrinks['waterBottle']) {
            softdrinksString += '<br>Plastic Bottle (Water) ' + softdrinks['waterBottle'];
        }
        if (softdrinks['bottleLid']) {
            softdrinksString += '<br>Plastic Bottle Lid ' + softdrinks['bottleLid'];
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
        if (softdrinks['plastic_cups']) {
            softdrinksString += '<br>Plastic Cups: ' + softdrinks['plastic_cups'];
        }
        if (softdrinks['plastic_cup_tops']) {
            softdrinksString += '<br>Plastic cup tops:: ' + softdrinks['plastic_cup_tops'];
        }
        if (softdrinks['milk_bottle']) {
            softdrinksString += '<br>Milk bottle: ' + softdrinks['milk_bottle'];
        }
        if (softdrinks['milk_carton']) {
            softdrinksString += '<br>Milk Carton: ' + softdrinks['milk_carton'];
        }
        if (softdrinks['paper_cups']) {
            softdrinksString += '<br>Paper Cups: ' + softdrinks['paper_cups'];
        }
        if (softdrinks['juice_cartons']) {
            softdrinksString += '<br>Juice Cartons: ' + softdrinks['juice_cartons'];
        }
        if (softdrinks['juice_bottles']) {
            softdrinksString += '<br>Juice Bottles: ' + softdrinks['juice_bottles'];
        }
        if (softdrinks['juice_packet']) {
            softdrinksString += '<br>Juice Packets: ' + softdrinks['juice_packet'];
        }
        if (softdrinks['ice_tea_bottles']) {
            softdrinksString += '<br>Ice Tea Bottles: ' + softdrinks['ice_tea_bottles'];
        }
        if (softdrinks['ice_tea_can']) {
            softdrinksString += '<br>Ice Tea Cans: ' + softdrinks['ice_tea_can'];
        }
        if (softdrinks['energy_can']) {
            softdrinksString += '<br>Energy Can: ' + softdrinks['energy_can'];
        }
        if (softdrinks['softDrinkOther']) {
            softdrinksString += '<br>Other (soft drink): ' + softdrinks['softDrinkOther'];
        }
        L.marker([lat, lon]).addTo(softdrinksGroup).bindPopup(softdrinksString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>' 
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let sanitaryString = '';
    if (litterGeojson["features"][i]["properties"]["sanitary"])
    {
        let sanitary = litterGeojson["features"][i]["properties"]["sanitary"];

        if (sanitary['gloves']) {
            sanitaryString += '<br>Gloves: ' + sanitary['gloves'];
        }
        if (sanitary['facemask']) {
            sanitaryString += '<br>Facemasks: ' + sanitary['facemask'];
        }
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
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let otherString = '';
    if (litterGeojson["features"][i]["properties"]["other"])
    {
        let other = litterGeojson["features"][i]["properties"]["other"];
        if (other['dogshit']) {
            otherString += '<br>Dogshit: ' + other['dogshit'];
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
        if (other['bags_litter']) {
            otherString += '<br>Bags of Litter: ' + other['bags_litter'];
        }
        if (other['cable_tie']) {
            otherString += '<br>Cable Tie: ' + other['cable_tie'];
        }
        if (other['tyre']) {
            otherString += '<br>Tyre: ' + other['tyre'];
        }
        if (other['overflowing_bins']) {
            otherString += '<br>Overflowing bins: ' + other['overflowing_bins'];
        }
        L.marker([lat, lon]).addTo(otherGroup).bindPopup(otherString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>' 
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let coastalString = '';
    if (litterGeojson["features"][i]["properties"]["coastal"])
    {
        let coastal = litterGeojson["features"][i]["properties"]["coastal"];

        if (coastal['microplastics']) {
            coastalString += '<br>Micro-plastics :' + coastal['microplastics'];
        }
        if (coastal['mediumplastics']) {
            coastalString += '<br>Medium-plastics: ' + coastal['mediumplastics'];
        }
        if (coastal['macroplastics']) {
            coastalString += '<br>Macro-plastics: ' + coastal['marcoplastics'];
        }
        if (coastal['rope_small']) {
            coastalString += '<br>Rope (small): ' + coastal['rope_small'];
        }
        if (coastal['rope_medium']) {
            coastalString += '<br>Rope (medium): ' + coastal['rope_medium'];
        }
        if (coastal['rope_large']) {
            coastalString += '<br>Rope (large): ' + coastal['rope_large'];
        }
        if (coastal['fishing_gear_nets']) {
            coastalString += '<br>Fishing Gear/Nets: ' + coastal['fishing_gear_nets'];
        }
        if (coastal['buoys']) {
            coastalString += '<br>Buoys: ' + coastal['buoys'];
        }
        if (coastal['degraded_plasticbottle']) {
            coastalString += '<br>Degraded Plastic Bottle: ' + coastal['degraded_plasticbottle'];
        }
        if (coastal['degraded_plasticbag']) {
            coastalString += '<br>Degraded Plastic Bag: ' + coastal['degraded_plasticbag'];
        }
        if (coastal['degraded_straws']) {
            coastalString += '<br>Degraded Straws: ' + coastal['degraded_straws'];
        }
        if (coastal['degraded_lighters']) {
            coastalString += '<br>Degraded Lighters: ' + coastal['degraded_lighters'];
        }
        if (coastal['baloons']) {
            coastalString += '<br>Ballons: ' + coastal['baloons'];
        }
        if (coastal['lego']) {
            coastalString += '<br>Lego: ' + coastal['lego'];
        }
        if (coastal['shotgun_cartridges']) {
            coastalString += '<br>Shotgun Cartridges: ' + coastal['shotgun_cartridges'];
        }
        if (coastal['coastal_other']) {
            coastalString += '<br>Coastal (other): ' + coastal['coastal_other'];
        }
        L.marker([lat, lon]).addTo(coastalGroup).bindPopup(coastalString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>' 
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let artString = '';
    if (litterGeojson["features"][i]["properties"]["art"]) {
        let art = litterGeojson["features"][i]["properties"]["art"];

        if (art['item']) {
            artString += '<br>Art: ' + art['item'];
        }
        L.marker([lat, lon]).addTo(artGroup).bindPopup(artString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>' 
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

    let brandsString = '';
    if (litterGeojson["features"][i]["properties"]["brands"])
    {
        let brands = litterGeojson["features"][i]["properties"]["brands"];

        if (brands['adidas']) {
            brandsString += '<br>Adidas: ' + brands['adidas'];
        }
        if (brands['amazon']) {
            brandsString += '<br>Amazon: ' + brands['amazon'];
        }
        if (brands['apple']) {
            brandsString += '<br>Apple: ' + brands['apple'];
        }
        if (brands['budweiser']) {
            brandsString += '<br>Budweiser: ' + brands['budweiser'];
        }
        if (brands['coke']) {
            brandsString += '<br>Coca-Cola: ' + brands['coke'];
        }
        if (brands['colgate']) {
            brandsString += '<br>Colgate: ' + brands['colgate'];
        }
        if (brands['corona']) {
            brandsString += '<br>Corona: ' + brands['corona'];
        }
        if (brands['costa']) {
            brandsString += '<br>Costa: ' + brands['costa'];
        }
        if (brands['fritolay']) {
            brandsString += '<br>Frito-Lay: ' + brands['fritolay'];
        }
        if (brands['gillette']) {
            brandsString += '<br>Gillette: ' + brands['gillette'];
        }
        if (brands['heineken']) {
            brandsString += '<br>Heineken: ' + brands['heineken'];
        }
        if (brands['kellogs']) {
            brandsString += '<br>Kellogs: ' + brands['kellogs'];
        }
        if (brands['lego']) {
            brandsString += '<br>Lego: ' + brands['lego'];
        }
        if (brands['loreal']) {
            brandsString += '<br>Loreal: ' + brands['loreal'];
        }
        if (brands['nescafe']) {
            brandsString += '<br>Nescafé: ' + brands['nescafe'];
        }
        if (brands['nestle']) {
            brandsString += '<br>Nestlé: ' + brands['nestle'];
        }
        if (brands['marlboro']) {
            brandsString += '<br>Marlboro: ' + brands['marlboro'];
        }
        if (brands['mcdonalds']) {
            brandsString += '<br>McDonalds: ' + brands['mcdonalds'];
        }
        if (brands['nike']) {
            brandsString += '<br>Nike: ' + brands['nike'];
        }
        if (brands['pepsi']) {
            brandsString += '<br>Pepsi: ' + brands['pepsi'];
        }
        if (brands['redbull']) {
            brandsString += '<br>RedBull: ' + brands['redbull'];
        }
        if (brands['samsung']) {
            brandsString += '<br>Samsung: ' + brands['samsung'];
        }
        if (brands['subway']) {
            brandsString += '<br>Subway: ' + brands['subway'];
        }
        if (brands['starbucks']) {
            brandsString += '<br>Starbucks: ' + brands['starbucks'];
        }
        if (brands['tayto']) {
            brandsString += '<brTayto: ' + brands['tayto'];
        }

        L.marker([lat, lon]).addTo(brandsGroup).bindPopup(brandsString +'<br>'
            + '<p>Taken on ' + litterGeojson["features"][i]["properties"]["datetime"] + ' With a ' + litterGeojson["features"][i]["properties"]["model"] + '</p>'
            + '<img style="height: 150px;" src="' + litterGeojson["features"][i]["properties"]["filename"] + '"/>'
            + '<p>Lat, Lon: ' + litterGeojson["features"][i]["properties"]["lat"] + ', '
            + litterGeojson["features"][i]["properties"]["lon"] + '</p>' 
            + '<p>By: ' + userfullname +userName+'.</p>'
        );
    }

}


// 4.3 - Create overlays toggle menu
let overlays = {
    Alcohol: alcoholGroup,
    Art: artGroup,
    Brands: brandsGroup,
    Coastal: coastalGroup,
    Coffee: coffeeGroup,
    Food: foodGroup,
    Other: otherGroup,
    Sanitary: sanitaryGroup,
    Smoking: smokingGroup,
    SoftDrinks: softdrinksGroup,
};

// // 4.4 - Add null basemaps and overlays to the map
L.control.layers(null, overlays).addTo(map);

// Step 5: Timeslider 
// let testlayer = smokingGroup;
// console.log(testlayer);
// let sliderControl = L.control.sliderControl({position: "bottomleft", layer: testlayer, range: true});
// console.log('sliderControl');
// console.log(sliderControl);
// // map.addControl(sliderControl);

// //And initialize the slider
// sliderControl.startSlider();

// $('#slider-timestamp').html(options.markers[ui.value].feature.properties.time.substr(0, 19));