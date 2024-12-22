import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import i18n from './i18n';
import { createPinia } from 'pinia'
const pinia = createPinia()
// import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
// pinia.use(piniaPluginPersistedstate);
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";
import Nav from './components/Nav.vue';

const app = createApp(App);
app.component('Nav', Nav);
app.use(i18n);
app.use(router);
app.use(pinia);
app.use(Toast);
app.mount('#app');
