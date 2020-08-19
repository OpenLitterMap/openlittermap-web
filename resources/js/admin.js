require('./admin-bootstrap')
import Vue from 'vue'
import axios from 'axios'
import store from './store'
import VueRouter from 'vue-router'
import router from './routes'
import VerifyPhotos from './views/admin/VerifyPhotos'
import AdminUserGraph from './components/AdminUserGraph.vue'
import VerificationBar from './components/VerificationBar'
import VueKonva from 'vue-konva'

Vue.use(VueRouter)
Vue.use(VueKonva)

window.axios = axios

const avm = new Vue({
  el: '#admin',
  router,
  store,
  components: {
  	AdminUserGraph,
  	VerificationBar,
    VerifyPhotos
  }
});