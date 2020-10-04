require('./bootstrap')

import Vue from 'vue'
import axios from 'axios'
import store from './store'
import VueRouter from 'vue-router'
import router from './routes'
import i18n from './i18n'
import VueLocalStorage from 'vue-localstorage'
import VueSweetalert2 from 'vue-sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'
import VueToastify from 'vue-toastify'
// import VueMask from 'v-mask' // needed to cancel some error on CreditCard.vue which we are not yet using
import VueNumber from 'vue-number-animation'
import VueEcho from 'vue-echo-laravel'

import RootContainer from './views/RootContainer'

// assign global variables
window.axios = axios

Vue.use(VueRouter)
Vue.use(VueLocalStorage)
Vue.use(VueSweetalert2)
Vue.use(VueToastify)
// Vue.use(VueMask)
Vue.use(VueNumber)
Vue.use(VueEcho, window.Echo)

// Format a number with commas: "10,000"
Vue.filter('commas', value => {
    return typeof(value) == 'number' ? `${Number(value).toLocaleString()}` : value;
});

const vm = new Vue({
    el: '#app',
    store,
    router,
    i18n,
    created ()
    {
        console.log('App created');
        // ProgressBar
        this.$on('percent', function(pcnt) {
            this.progressPercent = pcnt;
        });
    },
    components: {
        RootContainer
    },
    methods: {

        /**
         * Delete the welcome div when a user verifies their email address
         */
        deleteEmailSession() {
            document.getElementById('#emaildiv').delay(500).slideUp(300);
        },

        /**
         * For admins
         */
        verifyImage(photoId, status) {
            this.disabled = true;
            axios({
                method: 'post',
                url: '/verify',
                data: {
                    photoId: photoId,
                    status: status
                }
            }).then(response => {
                // this.verification = response.data.newVerification;
                alert('Thank you. The image has been updated.');
                this.status = response.data.status;
                window.location.href = window.location.href
            }).catch(error => {
                console.log(error);
                alert('Error! Please try again');
             });
        },
    }
});
