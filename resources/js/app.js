import './bootstrap.js';
import '../css/app.css';

// Main app files
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import i18n from './i18n';

// Pinia global store
import { createPinia } from 'pinia';
const pinia = createPinia();
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
pinia.use(piniaPluginPersistedstate);

// Load libraries
import Toast from 'vue-toastification';
import { LoadingPlugin } from 'vue-loading-overlay';
import { RecycleScroller } from 'vue-virtual-scroller';
import FloatingVue from 'floating-vue';

// Global global components
import Nav from './components/Nav.vue';
import Modal from './components/Modal/Modal.vue';

// Import CSS
import 'vue-toastification/dist/index.css';
import 'vue-loading-overlay/dist/css/index.css';
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css';
import 'floating-vue/dist/style.css';

// Disable on mobile
FloatingVue.options.themes.tooltip.disabled = window.innerWidth <= 768;

// Register app, components and use plugins
const app = createApp(App, window.initialProps);

app.component('Nav', Nav);
app.component('Modal', Modal);
app.component('RecycleScroller', RecycleScroller);

app.use(i18n);
app.use(router);
app.use(pinia);
app.use(Toast, { position: 'bottom-right' });
app.use(LoadingPlugin);
app.use(FloatingVue);
app.mount('#app');
