import { createRouter, createWebHistory } from 'vue-router';

import Welcome from '../views/Welcome/Welcome.vue';
import About from '../views/About.vue';
import Upload from '../views/Upload/Upload.vue';

// Import Middleware
import middlewarePipeline from './middleware/middlewarePipeline';

import auth from './middleware/auth'

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
        component: Upload,
        meta: {
            middleware: [auth],
        },
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    if (!to.meta.middleware) return next();

    const middleware = to.meta.middleware;

    const context = { to, from, next };

    return middleware[0]({
        ...context,
        next: middlewarePipeline(context, middleware, 1),
    });
});

export default router;
