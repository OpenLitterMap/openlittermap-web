import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import i18n from './i18n';

import Nav from './components/Nav.vue';

const app = createApp(App);
app.component('Nav', Nav);
app.use(i18n);
app.use(router);
app.mount('#app');
