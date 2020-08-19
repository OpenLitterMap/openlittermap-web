import './global-bootstrap'
import Vue from 'vue' // window.Vue = require('vue'); may be needed for notification message
import Vuex from 'vuex'
import store from './store'
// import LoginModal from './components/LoginModal'
import VueEcho from 'vue-echo-laravel'
import GlobalDates from './components/global/GlobalDates'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
// import VueToastify from 'vue-toastify'
import GlobalMap from './views/global/GlobalMap'
import VueLocalStorage from 'vue-localstorage'

Vue.use(VueEcho, window.Echo)
Vue.use(VueLocalStorage)

// Register components
// Vue.component('vue-toastify', VueToastify)

// for event notifications
window.EventBus = new Vue();

const globalvm = new Vue({
    el: '#global-div',
    store,
    components: {
        Loading,
    	// LoginModal,
        GlobalDates,
        GlobalMap
    },
    data: {
    	showLoginModal: false,
    },
    methods: {
    	/**
         * Delete the welcome div when a user verifies their email address
         */
        deleteEmailSession() {
            $('#emaildiv').delay(500).slideUp(300);
        },
    }
});
