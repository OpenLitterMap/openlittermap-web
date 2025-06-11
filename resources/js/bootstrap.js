import _ from 'lodash';
import axios from 'axios';

window._ = _;
window.axios = axios;
axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

axios.get('/sanctum/csrf-cookie').catch((err) => {
    console.error('Failed to get Sanctum CSRF cookie:', err);
});

// Websockets
import './echo.js';

// Leaflet
import './views/Maps/helpers/SmoothWheelZoom.js';
import '@css/leaflet/MarkerCluster.css';
import '@css/leaflet/MarkerCluster.Default.css';
import 'leaflet/dist/leaflet.css';
