require('./bootstrap');

import Vue from 'vue';
import axios from 'axios';
import store from './store';
import VueRouter from 'vue-router';
import router from './routes';
import i18n from './i18n';
import VueLocalStorage from 'vue-localstorage';
import VueSweetalert2 from 'vue-sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import VueToastify from 'vue-toastify';
// import VueMask from 'v-mask' // needed to cancel some error on CreditCard.vue which we are not yet using
import VueNumber from 'vue-number-animation';
import VueEcho from 'vue-echo-laravel';
import Buefy from 'buefy';
import fullscreen from 'vue-fullscreen';
import LaravelPermissionToVueJS from 'laravel-permission-to-vuejs';
import VueImg from 'v-img';
import VueTypedJs from 'vue-typed-js'

import RootContainer from './views/RootContainer';

// assign global variables
window.axios = axios;

Vue.use(Buefy);
Vue.use(VueRouter);
Vue.use(VueLocalStorage);
Vue.use(VueSweetalert2);
Vue.use(VueToastify, {
    theme: 'dark',
    errorDuration: 5000,
});
// Vue.use(VueMask)
Vue.use(VueNumber);
Vue.use(VueEcho, window.Echo);
Vue.use(fullscreen);
Vue.use(LaravelPermissionToVueJS);
Vue.use(VueImg);
Vue.use(VueTypedJs);

// Format a number with commas: "10,000"
Vue.filter('commas', value => {
    return parseInt(value).toLocaleString();
});

const vm = new Vue({
    el: '#app',
    store,
    router,
    i18n,
    components: {
        RootContainer
    }
});
