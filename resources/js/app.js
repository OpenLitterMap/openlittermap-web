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

import RootContainer from './views/RootContainer'

// assign global variables
window.axios = axios

Vue.use(VueRouter)
Vue.use(VueLocalStorage)
Vue.use(VueSweetalert2)

// Format a number with commas: "10,000"
Vue.filter('commas', value => {
    return typeof(value) == 'number' ? `${Number(value).toLocaleString()}` : value;
});

const vm = new Vue({
    el: '#app',
    router,
    store,
    i18n,
    created ()
    {
        console.log('APP CREATED');
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
