import _ from 'lodash';
import axios from 'axios';

window._ = _;
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';
const Echo = import('laravel-echo');

// console.log({ Echo });

window.Pusher = import('pusher-js');

// let useTLSOverride = process.env.MIX_WEBSOCKET_USE_TLS == "true" ? true : false
// if( !useTLSOverride){
//     window.Pusher.Runtime.getProtocol = function() {return 'http:';}
// }

// Echo is not a constructor
// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: 'aa1eecefcf9deb983617',
//     wsHost: window.location.hostname,
//     wssHost: window.location.hostname,
//     wsPort:  window.APP_DEBUG === 'true' ? 6001 : 6002,
//     wssPort: window.APP_DEBUG === 'true' ? 6001 : 6002,
//     disableStats: true,
//     encrypted: false,
//     enabledTransports: ['ws', 'wss']
// });

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
