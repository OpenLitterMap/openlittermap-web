require('./bootstrap',);

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

import RootContainer from './views/RootContainer';

// assign global variables
window.axios = axios;

Vue.use(VueRouter);
Vue.use(VueLocalStorage);
Vue.use(VueSweetalert2);
Vue.use(VueToastify, {
    theme: 'light',
    errorDuration: 5000,
    position: 'top-right'
},);
// Vue.use(VueMask)
Vue.use(VueNumber);
Vue.use(VueEcho, window.Echo);

// Format a number with commas: "10,000"
Vue.filter('commas', value =>
{
    return parseInt(value).toLocaleString();
},);

const vm = new Vue({
    el: '#app',
    store,
    router,
    i18n,
    components: {
        RootContainer
    },
    created ()
    {
        // ProgressBar
        this.$on('percent', function (pcnt)
        {
            this.progressPercent = pcnt;
        });
    },
    methods: {
        /**
         * Delete the welcome div when a user verifies their email address
         */
        deleteEmailSession ()
        {
            document.getElementById('#emaildiv').delay(500).slideUp(300);
        }
    }
});
