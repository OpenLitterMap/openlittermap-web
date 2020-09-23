<template>
    <div id="map" ref="map" />
</template>

<script>
import * as turf from "@turf/turf";

var info;
var hexFiltered;

var smokingGroup;
var foodGroup;
var coffeeGroup;
var alcoholGroup;
// var drugsGroup;
var softdrinksGroup;
var sanitaryGroup;
var otherGroup;
var coastalGroup;
// var pathwayGroup;
// var artGroup;
var brandsGroup;
var dogshitGroup;
var dumpingGroup;
var industrialGroup;

function style (feature)
{
    return {
        weight: 2,
        opacity: 1,
        color: 'white',
        dashArray: '3',
        fillOpacity: 0.7,
        fillColor: getColor(feature.properties.total)
    };
}

function getColor (n)
{
    return n > 60 ? '#800026' :
        n > 20 ? '#BD0026' :
        n > 10 ? '#E31A1C' :
        n > 4  ? '#FD8D3C' :
        n > 2  ? '#FED976' :
        '#FFEDA0';
}

function onEachFeature(feature, layer)
{
    layer.on({
        mouseover: highlightFeature,
        mouseout: resetHighlight,
        click: zoomToFeature
    });
}

function highlightFeature (e)
{
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

function resetHighlight (e)
{
    hexFiltered.resetStyle(e.target);
    info.update();
}

function zoomToFeature (e)
{
    map.fitBounds(e.target.getBounds());
}


export default {
    name: "TestCityMap",
    mounted ()
    {
        /** 1. Create map object */
        const map = L.map(this.$refs.map, {
            center: this.$store.state.citymap.center, // center_map,
            zoom: this.$store.state.citymap.zoom
        });

        /** 2. Add attribution to the map */
        const date = new Date();
        const year = date.getFullYear();

        let mapLink = '<a href="http://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: 20,
            minZoom: 1,
            // todo: maxBounds: bounds -> import from MapController -> not yet configured
        }).addTo(map);

        map.attributionControl.addAttribution('Litter data &copy; OpenLitterMap & Contributors ' + year);

        /** 3. Create hex grid using aggregated data */
        hexFiltered = L.geoJson(this.aggregate, {
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

        /** 4. Add info/control to the Top-Right */
        info = L.control();
        info.onAdd = function (map) {
            this._div = L.DomUtil.create('div', 'info');
            this.update();
            return this._div;
        };

        // Get Counts
        info.update = function (props) {
            this._div.innerHTML = '<h4>' + this.hex + ' meter hex grids</h4>' +  (props ?
                '<b>Hover over to count'+'</b><br />' + props.total + ' pieces of litter'
                : 'Hover over polygons to count.');
        };
        info.addTo(map);


        /** 5. Style the legend */
        // needs to dynamically and statistically significantly reflect input variables
        let legend = L.control({ position: 'bottomleft' });

        legend.onAdd = function (map)
        {
            let div = L.DomUtil.create('div', 'info legend'),
                grades = [1, 3, 6, 10, 20],
                labels = [],
                from, to;

            for (let i = 0; i < grades.length; i++) {
                from = grades[i];
                to = grades[i + 1];

                labels.push(
                    '<i style="background:' + getColor(from + 1) + '"></i> ' +
                    from + (to ? '&ndash;' + to : '+')
                );
            }

            div.innerHTML = labels.join('<br>');
            return div;
        };
        legend.addTo(map);

        /** 6. Create Groups */
        smokingGroup = new L.LayerGroup();
        foodGroup = new L.LayerGroup();
        coffeeGroup = new L.LayerGroup();
        alcoholGroup = new L.LayerGroup();
        // drugsGroup = new L.LayerGroup();
        softdrinksGroup = new L.LayerGroup().addTo(map);
        sanitaryGroup = new L.LayerGroup();
        otherGroup = new L.LayerGroup();
        coastalGroup = new L.LayerGroup();
        // pathwayGroup = new L.LayerGroup();
        // artGroup = new L.LayerGroup();
        brandsGroup = new L.LayerGroup();
        dogshitGroup = new L.LayerGroup();
        dumpingGroup = new L.LayerGroup();
        industrialGroup = new L.LayerGroup();
        // trashdogGroup = new L.LayerGroup();

        /** 7. Loop over geojson data and add to groups */
        this.addDataToLayerGroups();

        /** 8. Create overlays toggle menu */
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

        /** 9- Add null basemaps and overlays to the map */
        L.control.layers(null, overlays).addTo(map);

        /** 10 - TODO - Timeslider */

    },
    computed: {

        /**
         * From our input geojson object,
         * 1. Create bounding box
         * 2. Create hexgrid with bounding box
         * 3. Count point-in-polygon to filter 0 values
         */
        aggregate ()
        {
            // Create a bounding box from our set of features
            let bbox = turf.bbox(this.geojson);

            // Create a hexgrid from our data. This needs to be filtered to only show relevant data.
            let hexgrid = turf.hexGrid(bbox, 50, { units: 'meters' });

            // we need to parse here to avoid copying the object as shallow copies
            // see https://github.com/Turfjs/turf/issues/1914
            hexgrid = JSON.parse(JSON.stringify(hexgrid));

            // To filter the hexgrid, we need to find hex values with point in polygon and remove 0 values
            // 1. Hexgrid, 2. Points, 3. Our column value, 4. New value will be appended to the hexgrid
            return turf.collect(hexgrid, this.geojson, 'total_litter', 'values');
        },

        /**
         * Where to center the map (on page load)
         */
        center ()
        {
            return this.$store.state.citymap.center;
        },

        /**
         * Return geojson data for map
         */
        geojson ()
        {
            return this.$store.state.citymap.data;
        },

        /**
         * The size of the hex units
         */
        hex ()
        {
            return this.$store.state.citymap.hex;
        },

        /**
         * The current level of zoom
         */
        zoom ()
        {
            return this.$store.state.citymap.zoom;
        }
    },

    methods: {

        /**
         *
         */
        addDataToLayerGroups ()
        {
            this.geojson.features.map(i => {

                const lat = i.properties.lat;
                const lon = i.properties.lon;

                let userfullname = '';
                let userName = '';

                if (i.properties.smoking)
                {
                    let smoking = '';

                    if (i.properties.smoking.butts)         smoking += this.$t('litter.smoking.butts') + ': ' + i.properties.smoking.butts;
                    if (i.properties.smoking.lighters)      smoking += '<br>' + this.$t('litter.smoking.lighters') + ': ' + i.properties.smoking.lighters;
                    if (i.properties.smoking.cigaretteBox)  smoking += '<br>Cigarette Box: '     + i.properties.smoking.cigaretteBox;
                    if (i.properties.smoking.skins)         smoking += '<br>Rolling Papers: '    + i.properties.smoking.skins;
                    if (i.properties.smoking.tobaccoPouch)  smoking += '<br>Tobacco Pouch: '     + i.properties.smoking.tobaccoPouch;
                    if (i.properties.smoking.plastic)       smoking += '<br>Plastic Packaging: ' + i.properties.smoking.plastic;
                    if (i.properties.smoking.filters)       smoking += '<br>Filters: '           + i.properties.smoking.filters;
                    if (i.properties.smoking.filterbox)     smoking += '<br>Filters Box: '       + i.properties.smoking.filterbox;
                    if (i.properties.smoking.smokingOther)  smoking += '<br>Smoking (other): '   + i.properties.smoking.smokingOther;

                    L.marker([lat, lon]).addTo(smokingGroup).bindPopup(smoking
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.food)
                {
                    let food = '';

                    if (i.properties.food.sweetWrappers)        food += '<br>Sweet Wrappers: '              + i.properties.food.sweetWrappers;
                    if (i.properties.food.paperFoodPackaging)   food += '<br>Paper/Card Food Packaging: '   + i.properties.food.paperFoodPackaging;
                    if (i.properties.food.plasticFoodPackaging) food += '<br>Plastic Food Packaging: '      + i.properties.food.plasticFoodPackaging;
                    if (i.properties.food.plasticCutlery)       food += '<br>Plastic Cutlery: '             + i.properties.food.plasticCutlery;
                    if (i.properties.food.crisp_small)          food += '<br>Packet Crisps/Chips (small): ' + i.properties.food.crisp_small;
                    if (i.properties.food.crisp_large)          food += '<br>Packet Crisps/Chips (large): ' + i.properties.food.crisp_large;
                    if (i.properties.food.styrofoam_plate)      food += '<br>Styrofoam Plate: '             + i.properties.food.styrofoam_plate;
                    if (i.properties.food.napkins)              food += '<br>Napkins: '                     + i.properties.food.napkins;
                    if (i.properties.food.sauce_packet)         food += '<br>Sauce Packet: '                + i.properties.food.sauce_packet;
                    if (i.properties.food.glass_jar)            food += '<br>Glass Jar: '                   + i.properties.food.glass_jar;
                    if (i.properties.food.glass_jar_lid)        food += '<br>Glass Jar Lid: '               + i.properties.food.glass_jar_lid;
                    if (i.properties.food.foodOther)            food += '<br>Other (food): '                + i.properties.food.foodOther;

                    L.marker([lat, lon]).addTo(foodGroup).bindPopup(food +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.coffee)
                {
                    let coffee = '';

                    if (i.properties.coffee.coffeeCups)  coffee += '<br>Coffee Cups: '    + i.properties.coffee.coffeeCups;
                    if (i.properties.coffee.coffeeLids)  coffee += '<br>Coffee Lids: '    + i.properties.coffee.coffeeLids;
                    if (i.properties.coffee.coffeeOther) coffee += '<br>Other (coffee): ' + i.properties.coffee.coffeeOther;

                    L.marker([lat, lon]).addTo(coffeeGroup).bindPopup(coffee +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '<p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.alcohol)
                {
                    let alcohol = '';

                    if (i.properties.alcohol.beerBottle)                alcohol += '<br>Beer Bottles: ' + i.properties.alcohol.beerBottle;
                    if (i.properties.alcohol.beerCan)                   alcohol += '<br>Beer Can: ' + i.properties.alcohol.beerCan;
                    if (i.properties.alcohol.bottleTops)                alcohol += '<br>Bottle Tops: ' + i.properties.alcohol.bottleTops;
                    if (i.properties.alcohol.brokenGlass)               alcohol += '<br>Broken Glass: ' + i.properties.alcohol.brokenGlass;
                    if (i.properties.alcohol.paperCardAlcoholPackaging) alcohol += '<br>Paper Card Alcohol Packaging: ' + i.properties.alcohol.paperCardAlcoholPackaging;
                    if (i.properties.alcohol.plasticAlcoholPackaging)   alcohol += '<br>Plastic Alcohol Packaging: ' + i.properties.alcohol.plasticAlcoholPackaging;
                    if (i.properties.alcohol.spiritBottle)              alcohol += '<br>Spirit Bottles: ' + i.properties.alcohol.spiritBottle;
                    if (i.properties.alcohol.wineBottle)                alcohol += '<br>Wine Bottles: ' + i.properties.alcohol.wineBottle;
                    if (i.properties.alcohol.alcoholOther)              alcohol += '<br>Other (alcohol): ' + i.properties.alcohol.alcoholOther;

                    L.marker([lat, lon]).addTo(alcoholGroup).bindPopup(alcohol +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + i.properties.lat + ', ' + i.properties.lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.softdrinks)
                {
                    let softdrinks = '';

                    if (i.properties.softdrinks.waterBottle)        softdrinks += '<br>Plastic Bottle (Water): ' + i.properties.softdrinks.waterBottle;
                    if (i.properties.softdrinks.bottleLid)          softdrinks += '<br>Plastic Bottle Lid: ' + i.properties.softdrinks.bottleLid;
                    if (i.properties.softdrinks.fizzyDrinkBottle)   softdrinks += '<br>Plastic fizzy drink bottle: ' + i.properties.softdrinks.fizzyDrinkBottle;
                    if (i.properties.softdrinks.bottleLabel)        softdrinks += '<br>Plastic bottle label: ' + i.properties.softdrinks.bottleLabel;
                    if (i.properties.softdrinks.tinCan)             softdrinks += '<br>Tin Can: ' + i.properties.softdrinks.tinCan;
                    if (i.properties.softdrinks.sportsDrink)        softdrinks += '<br>Sports Drink: ' + i.properties.softdrinks.sportsDrink;
                    if (i.properties.softdrinks.straws)             softdrinks += '<br>Straws: ' + i.properties.softdrinks.straws;
                    if (i.properties.softdrinks.plastic_cups)       softdrinks += '<br>Plastic Cups: ' + i.properties.softdrinks.plastic_cups;
                    if (i.properties.softdrinks.plastic_cup_tops)   softdrinks += '<br>Plastic cup tops: ' + i.properties.softdrinks.plastic_cup_tops;
                    if (i.properties.softdrinks.milk_bottle)        softdrinks += '<br>Milk bottle: ' + i.properties.softdrinks.milk_bottle;
                    if (i.properties.softdrinks.milk_carton)        softdrinks += '<br>Milk Carton: ' + i.properties.softdrinks.milk_carton;
                    if (i.properties.softdrinks.paper_cups)         softdrinks += '<br>Paper Cups: ' + i.properties.softdrinks.paper_cups;
                    if (i.properties.softdrinks.juice_cartons)      softdrinks += '<br>Juice Cartons: ' + i.properties.softdrinks.juice_cartons;
                    if (i.properties.softdrinks.juice_bottles)      softdrinks += '<br>Juice Bottles: ' + i.properties.softdrinks.juice_bottles;
                    if (i.properties.softdrinks.juice_packet)       softdrinks += '<br>Juice Packets: ' + i.properties.softdrinks.juice_packet;
                    if (i.properties.softdrinks.ice_tea_bottles)    softdrinks += '<br>Ice Tea Bottles: ' + i.properties.softdrinks.ice_tea_bottles;
                    if (i.properties.softdrinks.ice_tea_can)        softdrinks += '<br>Ice Tea Cans: ' + i.properties.softdrinks.ice_tea_can;
                    if (i.properties.softdrinks.energy_can)         softdrinks += '<br>Energy Can: ' + i.properties.softdrinks.energy_can;
                    if (i.properties.softdrinks.styro_cup)          softdrinks += '<br>Styrofoam Cup: ' + i.properties.softdrinks.styro_cup;
                    if (i.properties.softdrinks.softDrinkOther)     softdrinks += '<br>Other (soft drink): ' + i.properties.softdrinks.softDrinkOther;

                    L.marker([lat, lon]).addTo(softdrinksGroup).bindPopup(softdrinks +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.sanitary)
                {
                    let sanitary = '';

                    if (i.properties.sanitary.condoms)       sanitary += '<br>Condoms: ' + i.properties.sanitary.condoms;
                    if (i.properties.sanitary.nappies)       sanitary += '<br>Nappies: ' + i.properties.sanitary.nappies;
                    if (i.properties.sanitary.menstral)      sanitary += '<br>Menstral: ' + i.properties.sanitary.menstral;
                    if (i.properties.sanitary.deodorant)     sanitary += '<br>Deodorant: ' + i.properties.sanitary.deodorant;
                    if (i.properties.sanitary.ear_swabs)     sanitary += '<br>Ear Swabs: ' + i.properties.sanitary.ear_swabs;
                    if (i.properties.sanitary.tooth_pick)    sanitary += '<br>Tooth Pick: ' + i.properties.sanitary.tooth_pick;
                    if (i.properties.sanitary.tooth_brush)   sanitary += '<br>Tooth Brush: ' + i.properties.sanitary.tooth_brush;
                    if (i.properties.sanitary.sanitaryOther) sanitary += '<br>Other (sanitary): ' + i.properties.sanitary.sanitaryOther;

                    L.marker([lat, lon]).addTo(sanitaryGroup).bindPopup(sanitary +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.other)
                {
                    let other = '';
                    let dogshit = '';
                    let dumping = '';

                    // some older items were mapped on Other but have since moved to separate categories

                    if (i.properties.other.dogshit)
                    {
                        dogshit += '<br>Pet Surprise: ' + i.properties.other.dogshit;
                        L.marker([lat, lon]).addTo(dogshitGroup).bindPopup(dogshit + '<br>'
                            + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                            + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                            + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                            + '<p>By: ' + userfullname + userName + '</p>'
                        );
                    }

                    if (i.properties.other.dump)
                    {
                        dumping += '<br>Illegal Dumping: ' + i.properties.other.dump;
                        L.marker([lat, lon]).addTo(dumpingGroup).bindPopup(dumping + '<br>'
                            + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                            + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                            + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                            + '<p>By: ' + userfullname + userName + '</p>'
                        );
                    }

                    if (i.properties.other.random_litter)    other += '<br>Random Litter '            + i.properties.other.random_litter;
                    if (i.properties.other.plastic)          other += '<br>Random plastic: '          + i.properties.other.plastic;
                    if (i.properties.other.metal)            other += '<br>Metal object: '            + i.properties.other.metal;
                    if (i.properties.other.batteries)        other += '<br>Batteries: '               + i.properties.other.batteries;
                    if (i.properties.other.elec_small)       other += '<br>Electrical item (small): ' + i.properties.other.elec_small;
                    if (i.properties.other.elec_large)       other += '<br>Electrical item (large): ' + i.properties.other.elec_large;
                    if (i.properties.other.plastic_bags)     other += '<br>Plastic Bags: '            + i.properties.other.plastic_bags;
                    if (i.properties.other.election_posters) other += '<br>Election Posters: '        + i.properties.other.election_posters;
                    if (i.properties.other.forsale_posters)  other += '<br>For Sale Posters: '        + i.properties.other.forsale_posters;
                    if (i.properties.other.books)            other += '<br>Books: '                   + i.properties.other.books;
                    if (i.properties.other.magazine)         other += '<br>Magazines: '               + i.properties.other.magazine;
                    if (i.properties.other.paper)            other += '<br>Paper: '                   + i.properties.other.paper;
                    if (i.properties.other.stationary)       other += '<br>Stationary: '              + i.properties.other.stationary;
                    if (i.properties.other.washing_up)       other += '<br>Washing-up bottle: '       + i.properties.other.washing_up;
                    if (i.properties.other.hair_tie)         other += '<br>Hair Tie: '                + i.properties.other.hair_tie;
                    if (i.properties.other.ear_plugs)        other += '<br>Ear Plugs (music): '       + i.properties.other.ear_plugs;
                    if (i.properties.other.other)            other += '<br>Unidentified item: '       + i.properties.other.other;

                    L.marker([lat, lon]).addTo(otherGroup).bindPopup(other +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.coastal)
                {
                    let coastal = '';

                    if (i.properties.coastal.microplastics)          coastal += '<br>Micro-plastics :' + i.properties.coastal.microplastics;
                    if (i.properties.coastal.mediumplastics)         coastal += '<br>Medium-plastics: ' + i.properties.coastal.mediumplastics;
                    if (i.properties.coastal.macroplastics)          coastal += '<br>Macro-plastics: ' + i.properties.coastal.marcoplastics;
                    if (i.properties.coastal.rope_small)             coastal += '<br>Rope (small): ' + i.properties.coastal.rope_small;
                    if (i.properties.coastal.rope_medium)            coastal += '<br>Rope (medium): ' + i.properties.coastal.rope_medium;
                    if (i.properties.coastal.rope_large)             coastal += '<br>Rope (large): ' + i.properties.coastal.rope_large;
                    if (i.properties.coastal.fishing_gear_nets)      coastal += '<br>Fishing Gear/Nets: ' + i.properties.coastal.fishing_gear_nets;
                    if (i.properties.coastal.buoys)                  coastal += '<br>Buoys: ' + i.properties.coastal.buoys;
                    if (i.properties.coastal.degraded_plasticbottle) coastal += '<br>Degraded Plastic Bottle: ' + i.properties.coastal.degraded_plasticbottle;
                    if (i.properties.coastal.degraded_plasticbag)    coastal += '<br>Degraded Plastic Bag: ' + i.properties.coastal.degraded_plasticbag;
                    if (i.properties.coastal.degraded_straws)        coastal += '<br>Degraded Straws: ' + i.properties.coastal.degraded_straws;
                    if (i.properties.coastal.degraded_lighters)      coastal += '<br>Degraded Lighters: ' + i.properties.coastal.degraded_lighters;
                    if (i.properties.coastal.balloons)               coastal += '<br>Ballons: ' + i.properties.coastal.balloons;
                    if (i.properties.coastal.lego)                   coastal += '<br>Lego: ' + i.properties.coastal.lego;
                    if (i.properties.coastal.shotgun_cartridges)     coastal += '<br>Shotgun Cartridges: ' + i.properties.coastal.shotgun_cartridges;
                    if (i.properties.coastal.styro_small)            coastal += '<br>Styrofoam small: ' + i.properties.coastal.styro_small;
                    if (i.properties.coastal.styro_medium)           coastal += '<br>Styrofoam medium: ' + i.properties.coastal.styro_medium;
                    if (i.properties.coastal.styro_large)            coastal += '<br>Styrofoam large: ' + i.properties.coastal.styro_large;
                    if (i.properties.coastal.coastal_other)          coastal += '<br>Coastal (other): ' + i.properties.coastal.coastal_other;

                    L.marker([lat, lon]).addTo(coastalGroup).bindPopup(coastal +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.brands)
                {
                    let brands = '';

                    if (i.properties.brands.adidas)     brands += '<br>Adidas: '    + i.properties.brands.adidas;
                    if (i.properties.brands.amazon)     brands += '<br>Amazon: '    + i.properties.brands.amazon;
                    if (i.properties.brands.apple)      brands += '<br>Apple: '     + i.properties.brands.apple;
                    if (i.properties.brands.budweiser)  brands += '<br>Budweiser: ' + i.properties.brands.budweiser;
                    if (i.properties.brands.camel)      brands += '<br>Camel: '     + i.properties.brands.camel;
                    if (i.properties.brands.coke)       brands += '<br>Coca-Cola: ' + i.properties.brands.coke;
                    if (i.properties.brands.colgate)    brands += '<br>Colgate: '   + i.properties.brands.colgate;
                    if (i.properties.brands.corona)     brands += '<br>Corona: '    + i.properties.brands.corona;
                    if (i.properties.brands.doritos)    brands += '<br>Doritos: '   + i.properties.brands.doritos;
                    if (i.properties.brands.fritolay)   brands += '<br>Frito-Lay: ' + i.properties.brands.fritolay;
                    if (i.properties.brands.gillette)   brands += '<br>Gillette: '  + i.properties.brands.gillette;
                    if (i.properties.brands.heineken)   brands += '<br>Heineken: '  + i.properties.brands.heineken;
                    if (i.properties.brands.kellogs)    brands += '<br>Kellogs: '   + i.properties.brands.kellogs;
                    if (i.properties.brands.lego)       brands += '<br>Lego: '      + i.properties.brands.lego;
                    if (i.properties.brands.loreal)     brands += '<br>Loreal: '    + i.properties.brands.loreal;
                    if (i.properties.brands.nescafe)    brands += '<br>Nescafé: '   + i.properties.brands.nescafe;
                    if (i.properties.brands.nestle)     brands += '<br>Nestlé: '    + i.properties.brands.nestle;
                    if (i.properties.brands.marlboro)   brands += '<br>Marlboro: '  + i.properties.brands.marlboro;
                    if (i.properties.brands.mcdonalds)  brands += '<br>McDonalds: ' + i.properties.brands.mcdonalds;
                    if (i.properties.brands.nike)       brands += '<br>Nike: '      + i.properties.brands.nike;
                    if (i.properties.brands.pepsi)      brands += '<br>Pepsi: '     + i.properties.brands.pepsi;
                    if (i.properties.brands.redbull)    brands += '<br>RedBull: '   + i.properties.brands.redbull;
                    if (i.properties.brands.samsung)    brands += '<br>Samsung: '   + i.properties.brands.samsung;
                    if (i.properties.brands.subway)     brands += '<br>Subway: '    + i.properties.brands.subway;
                    if (i.properties.brands.starbucks)  brands += '<br>Starbucks: ' + i.properties.brands.starbucks;
                    if (i.properties.brands.tayto)      brands += '<br>Tayto: '     + i.properties.brands.tayto;

                    L.marker([lat, lon]).addTo(brandsGroup).bindPopup(brands +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName+'</p>'
                    );
                }

                if (i.properties.dumping)
                {
                    let dumping = '';

                    if (i.properties.dumping.small)  dumping += '<br>Dumping (small): '  + i.properties.dumping.small;
                    if (i.properties.dumping.medium) dumping += '<br>Dumping (medium): ' + i.properties.dumping.medium;
                    if (i.properties.dumping.large)  dumping += '<br>Dumping (large): '  + i.properties.dumping.large;

                    L.marker([lat, lon]).addTo(dumpingGroup).bindPopup(dumping +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
                        + '<p>By: ' + userfullname + userName + '</p>'
                    );
                }

                if (i.properties.industrial)
                {
                    let industrial = '';

                    if (i.properties.industrial.oil)      industrial += '<br>Oil: '      + i.properties.industrial.oil;
                    if (i.properties.industrial.chemical) industrial += '<br>Chemical: ' + i.properties.industrial.chemical;
                    if (i.properties.industrial.plastic)  industrial += '<br>Plastic: '  + i.properties.industrial.plastic;
                    if (i.properties.industrial.bricks)   industrial += '<br>Bricks: '   + i.properties.industrial.bricks;
                    if (i.properties.industrial.tape)     industrial += '<br>Tape: '     + i.properties.industrial.tape;
                    if (i.properties.industrial.other)    industrial += '<br>Other: '    + i.properties.industrial.other;

                    L.marker([lat, lon]).addTo(dumpingGroup).bindPopup(industrial +'<br>'
                        + '<p>Taken on ' + i.properties.datetime + ' With a ' + i.properties.model + '</p>'
                        + '<img style="height: 150px;" src="' + i.properties.filename + '"/>'
                        + '<p>Lat, Lon: ' + i.properties.lat + ', '
                        + i.properties.lon + '</p>'
                        + '<p>By: ' + userfullname + userName + '</p>'
                    );
                }
            });
        }
    }
}
</script>

<style scoped>

    #map {
        height: calc(98.5vh - 72px);
        margin: 0;
        position: relative;
    }

</style>
