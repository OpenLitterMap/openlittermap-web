]<template>
    <div style="height: 100%;" @click="closeButtons">
        <div id="super" ref="super" />

        <!-- Change language -->
        <!-- <Languages />-->

        <!-- Change data -->
        <!-- <global-dates />-->

        <!-- Call to Action -->
        <!-- <global-info />-->

        <!-- Live Events -->
        <live-events />
    </div>
</template>

<script>
import Languages from '../../components/global/Languages';
// import GlobalDates from '../../components/global/GlobalDates'
import LiveEvents from '../../components/LiveEvents';
// import GlobalInfo from '../../components/global/GlobalInfo'
import {
    CLUSTER_ZOOM_THRESHOLD,
    MAX_ZOOM,
    MEDIUM_CLUSTER_SIZE,
    LARGE_CLUSTER_SIZE,
    MIN_ZOOM,
    ZOOM_STEP
} from '../../constants';

import L from 'leaflet';
import moment from 'moment';
import './SmoothWheelZoom.js';
import i18n from '../../i18n'

var map;
var markers;
var prevZoom = MIN_ZOOM;

const green_dot = L.icon({
    iconUrl: './images/vendor/leaflet/dist/dot.png',
    iconSize: [10, 10]
});

const grey_dot = L.icon({
    iconUrl: './images/vendor/leaflet/dist/grey-dot.jpg',
    iconSize: [13, 10]
});

/**
 * Create the cluster or point icon to display for each feature
 */
function createClusterIcon (feature, latlng)
{
    if (! feature.properties.cluster)
    {
        return feature.properties.verified === 2
            ? L.marker(latlng, { icon: green_dot })
            : L.marker(latlng, { icon: grey_dot });
    }

    let count = feature.properties.point_count;
    let size = count < MEDIUM_CLUSTER_SIZE ? 'small' : count < LARGE_CLUSTER_SIZE ? 'medium' : 'large';

    let icon = L.divIcon({
        html: '<div class="mi"><span class="ma">' + feature.properties.point_count_abbreviated + '</span></div>',
        className: 'marker-cluster-' + size,
        iconSize: L.point(40, 40)
    });

    return L.marker(latlng, { icon });
}

/**
 * On each feature, perform this action
 *
 * This is being performed whenever the user drags the map.
 *
 * Tranlsation should only occur when the user clicks on a point to open an image.
 */
function onEachFeature (feature, layer)
{
    if (! feature.properties.cluster)
    {
        let z = '';

        if (feature.properties.result_string)
        {
            let a = '';

            a = feature.properties.result_string.split(',');

            a.pop();

            a.forEach(i => {
                let b = i.split(' ');

                z += i18n.t('litter.' + b[0]) + ': ' + b[1] + ' ';
            });
        }
        else
        {
            z = i18n.t('litter.not-verified');
        }

        layer.bindPopup(
            '<p class="mb5p">' + z + ' </p>'
            + '<img src= "' + feature.properties.filename + '" class="mw100" />'
            + '<p>Taken on ' + moment(feature.properties.datetime).format('LLL') +'</p>'
        );
    }

    else
    {
        // Zoom in cluster when click to it
        layer.on('click', function (e) {
            map.setView(e.latlng, map.getZoom() + ZOOM_STEP);
        });
    }
}

/**
 * The user dragged or zoomed the map
 */
async function update ()
{
    const bounds = map.getBounds();

    let bbox = {
        'left': bounds.getWest(),
        'bottom': bounds.getSouth(),
        'right': bounds.getEast(),
        'top': bounds.getNorth(),
    };
    let zoom = Math.round(map.getZoom());

    // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
    // At these levels, we just load all global data for now
    if (zoom === 2 && zoom === prevZoom) return;
    if (zoom === 3 && zoom === prevZoom) return;
    if (zoom === 4 && zoom === prevZoom) return;
    if (zoom === 5 && zoom === prevZoom) return;

    // If the zoom is less than 17, we want to load cluster data
    if (zoom < CLUSTER_ZOOM_THRESHOLD)
    {
        await axios.get('clusters', {
            params: { zoom, bbox }
        })
        .then(response => {
            console.log('get_clusters.update', response);

            markers.clearLayers();
            markers.addData(response.data);
        })
        .catch(error => {
            console.error('get_clusters.update', error);
        });
    }
    // otherwise, get point data
    else
    {
        await axios.get('global-points', {
            params: { zoom, bbox, },
        })
        .then(response => {
            console.log('get_global_points', response);

            // Clear layer if prev layer is cluster.
            if (prevZoom < CLUSTER_ZOOM_THRESHOLD)
            {
                markers.clearLayers();
            }

            // markers.addData(response.data);

            // New
            // test data
            // const data = [[50.10164799,14.44891924],[50.22763919,14.09765223],[49.98581544,14.15183071],[50.10728931,13.71442186],[50.67929326,13.86138742],[50.05775298,14.43533886],[50.08837499,14.41751829],[50.4950314,13.66133656],[50.72570461,15.15851486],[49.23212433,17.68167867],[50.17182042,12.6608715],[49.5026322,16.52083723],[49.80754202,18.24596618],[49.76212188,16.47547337],[49.96282452,15.27562567],[49.16196452,15.20627902],[50.0764765,14.42292431],[50.73742831,15.61324983],[49.01704412,14.61003771],[49.87388396,16.307951],[50.68874552,14.55279533],[49.27745683,16.99897449],[50.66150412,13.845258],[49.8726264,18.42703152],[49.39551393,13.29505429],[50.13874984,14.37847971],[49.73286217,13.75698347],[50.46708135,13.43173748],[50.74138761,15.4834258],[50.67466866,14.04562691],[50.92113358,15.08818123],[48.82011914,16.39973619],[49.06531192,17.44673169],[50.68988071,14.55282302],[49.00830466,17.13305183],[49.96527202,16.97060832],[49.54910372,18.20815253],[49.21099776,16.60051448],[50.10767765,14.3914687],[49.55270137,17.72626505],[50.0382183,14.32874955],[49.29816086,14.74736873],[48.96701044,14.48132144],[50.28755841,14.83708816],[50.03711116,14.52345154],[50.42373028,14.24578414],[50.16748785,16.46403921],[50.25309004,14.51876002],[50.0799951,14.42218367],[48.84891765,17.13244437],[48.75895888,16.88203015],[50.10435324,14.50362955],[49.88676632,16.88188537],[48.91692294,17.09004174],[50.488014,13.43957757],[50.03276395,15.56121677],[49.20266854,16.65427166],[50.34090555,15.93269274],[50.02383421,15.1830145],[49.15315877,16.34810124],[49.95435058,15.79448807],[50.65603875,13.84294408],[49.23618682,17.66488998],[50.24726976,14.31726012],[50.18580583,15.82799581],[50.07534516,15.67789469],[49.41870074,14.67935482],[48.81090789,14.31520754],[50.13124702,14.11593903],[49.62355202,12.95532619],[49.47184731,17.12435278],[49.86927045,18.42674071],[50.67522607,14.10649153],[50.07531589,14.45662019],[50.42961054,14.90443828],[50.61900367,13.60802233],[50.08093482,14.42311408],[49.62672633,17.86017043],[49.33864175,17.9961902],[42.94888243,13.87916543],[49.67559518,18.33055309],[49.46821721,17.12749644],[49.2031043,16.60673699],[50.11195981,14.39360062],[50.11046423,14.39628639],[50.08937823,14.42402099],[50.06448661,14.410229],[49.28921999,16.66590323],[49.57288939,13.32625702],[49.57568897,17.25123089],[50.51112668,14.0620429],[50.05551531,14.50765085],[49.40937851,14.67370972],[50.68002069,14.11656909],[50.06555779,14.45700727],[49.97622433,12.70144729],[49.46850338,17.12028624],[49.40976318,15.61671911],[49.74306191,15.08906917],[50.49873293,13.63881909],[49.10491158,16.36676232],[49.01016097,17.12252795],[50.74669126,15.05802187],[50.21189179,12.63725536],[48.75827125,16.85326059],[50.03794114,15.5707874],[50.10435324,14.50362955],[50.23520482,12.87789652],[50.76120794,15.28077991],[49.30370749,14.14390502],[50.03654969,14.38558771],[50.07609703,14.42814176],[48.94940321,14.91342936],[49.14679822,18.01258192],[50.69054552,14.52778215],[50.0708736,14.46027396],[49.5814884,17.25697925],[49.94772355,17.9072588],[49.4803113,18.09451094],[50.22325736,12.88758909],[49.33589953,18.01917137],[50.21281047,15.84356185],[49.93685899,17.90454509],[49.95193179,14.03425383],[50.49852692,13.43327666],[50.476788,16.10024634],[49.75441834,13.38017338],[50.14660449,13.95528204],[49.41651623,15.58933925],[50.04789334,14.56130329],[50.17977371,15.04466244],[50.6279141,15.61292513],[50.72592781,15.15908587],[50.14808054,13.9042693],[50.5372726,14.14104874],[50.35919857,15.63322265],[49.97352047,14.50706809],[49.33976338,17.98749111],[49.22429108,17.50932225],[50.72585675,14.55637242],[49.31933986,17.9774749],[50.22957907,12.86996794],[48.98005047,14.4770046],[48.80555352,16.63779522],[50.09578236,16.98490133],[49.77817199,18.25997526],[49.96462725,14.06365246],[50.0835493,14.4341413],[49.2901716,17.39300266],[49.09328122,17.73918259],[49.47837922,17.10478268],[50.68501585,13.88871793],[50.66053017,14.0357681],[50.18528258,15.84835116],[49.95125418,15.66922107],[50.31146116,12.94900855],[50.46120657,13.41120326],[50.07293096,14.48482575],[49.05824149,17.48415094],[50.21922221,14.53563078],[50.73496064,15.18988753],[49.20579923,15.88859965],[49.73916482,13.38076246],[50.10185513,14.44390742],[50.63313867,13.844977],[50.4971449,14.14814799],[49.97043561,16.97191925],[49.83987438,13.91029118],[50.488014,13.43957757],[49.89170515,18.19490681],[50.08479206,14.34413098],[49.44270307,14.37170749],[48.85670144,17.12382856],[50.64650335,13.85441482],[49.97119964,12.70388559],[49.949297,14.03391579],[50.04139153,15.80762651],[50.73781267,15.0564454],[48.96537402,14.47330825],[50.1439015,14.45046018],[50.07497756,15.07647598],[50.03351641,14.41807832],[50.73446859,15.48976853],[50.1869368,15.83689998],[50.10270324,14.40067465],[50.03675838,15.7738979],[49.83999892,18.2657838],[50.04832936,14.4304866],[50.6284019,14.53378909],[50.08177499,14.44327404],[50.21922221,14.53563078],[49.89648849,13.38070169],[49.96631241,14.51788058],[48.80071877,14.30894164],[49.24639163,13.91543585],[50.10405655,14.45424563],[49.96137651,16.97855923],[49.94819615,12.706658],[50.76076987,15.07216141],[49.06898559,17.46738229],[50.02502125,14.22382356],[50.10353711,14.40312055],[50.54560494,14.1232477],[50.61825435,15.6573486],[49.30396113,14.14308343],[49.46197921,17.97149834],[50.14958137,15.12068859],[50.18394993,14.65835628]];

            // Todo -
            // Loop over points
            // Extract XY into array?
            // or else pre-process array on the backend and return here?
            // lookup data by XY?

            glify.points({
                map,
                data,
                size: 10,
                // click: (e, pointOrGeoJsonFeature, xy) => {
                //     // do something when a point is clicked
                //     // return false to continue traversing
                //     console.log('clicked');
                // },
                // hover: (e, pointOrGeoJsonFeature, xy) => {
                //     // do something when a point is hovered
                //     console.log('hovered');
                // }
            });
        })
        .catch(error => {
            console.error('get_global_points', error);
        });
    }
    prevZoom = zoom; // hold previous zoom
}

import glify from 'leaflet.glify';

export default {
    name: 'Supercluster',
    components: {
        Languages,
        // GlobalDates,
        LiveEvents,
        // GlobalInfo
    },
    mounted ()
    {
        /** 1. Create map object */
        map = L.map('super', {
            center: [0, 0],
            zoom: MIN_ZOOM,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 1,
        });

        map.scrollWheelZoom = true;

        const date = new Date();
        const year = date.getFullYear();

        /** 2. Add tiles, attribution, set limits */
        const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: MAX_ZOOM,
            minZoom: MIN_ZOOM
        }).addTo(map);

        map.attributionControl.addAttribution('Litter data &copy OpenLitterMap & Contributors ' + year + ' Clustering @ MapBox');

        // Empty Layer Group that will receive the clusters data on the fly.
        markers = L.geoJSON(null, {
            pointToLayer: createClusterIcon,
            onEachFeature: onEachFeature,
        }).addTo(map);

        markers.addData(this.$store.state.globalmap.geojson.features);

        map.on('moveend', function ()
        {
            update();
        });

        // todo - getClusterExpansionZoom(clusterId);
    },

    methods: {

        /**
         * Close dates and language dropdowns
         */
        closeButtons ()
        {
            this.$store.commit('closeDatesButton');
            this.$store.commit('closeLangsButton');
        }
    }
};
</script>

<style>

    #super {
        height: 100%;
        margin: 0;
        position: relative;
    }

    .leaflet-marker-icon {
        border-radius: 20px;
    }

    .mb5p {
        margin-bottom: 5px;
    }

    .mw100 {
        max-width: 100%;
    }

    .mi {
        height: 100%;
        margin: auto;
        display: flex;
        justify-content: center;
        border-radius: 20px;
    }

</style>
