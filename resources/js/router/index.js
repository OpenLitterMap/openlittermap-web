import { createRouter, createWebHistory } from 'vue-router';

import About from '../views/About.vue';
import GlobalMap from "../views/Maps/GlobalMap.vue";
import History from "../views/General/History.vue";
import References from '../views/Academic/References.vue';
import Upload from '../views/Upload/Upload.vue';
import Welcome from '../views/Welcome/Welcome.vue';

// Import Middleware
import middlewarePipeline from './middleware/middlewarePipeline';
import auth from './middleware/auth'

const routes = [
    {
        path: '/about',
        name: 'About',
        component: About,
    },
    {
        path: '/history',
        name: 'History',
        component: History,
    },
    {
        path: '/global',
        name: 'GlobalMap',
        component: GlobalMap,
    },
    {
        path: '/references',
        name: 'References',
        component: References
    },
    {
        path: '/',
        name: 'Welcome',
        component: Welcome,
    },
    // Auth Reoutes
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
