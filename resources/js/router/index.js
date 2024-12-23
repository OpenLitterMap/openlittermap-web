import { createRouter, createWebHistory } from 'vue-router';

import Welcome from '../views/Welcome/Welcome.vue';
import About from '../views/About.vue';

const routes = [
    {
        path: '/',
        name: 'Welcome',
        component: Welcome,
    },
    {
        path: '/about',
        name: 'About',
        component: About,
    },
    {
        path: '/upload',
        name: 'Upload',

    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
