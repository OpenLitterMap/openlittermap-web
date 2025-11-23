import { createRouter, createWebHistory } from 'vue-router';

import About from '../views/General/About.vue';
import AddTags from '../views/General/Tagging/AddTags.vue';
import CreateAccount from '../views/Account/CreateAccount.vue';
import GlobalMap from '../views/Maps/GlobalMap.vue';
import History from '../views/General/History.vue';
import Leaderboard from '../views/General/Leaderboards/Leaderboard.vue';
import References from '../views/Academic/References.vue';
import Upload from '../views/Upload/Upload.vue';
import Welcome from '../views/Welcome/Welcome.vue';

// Import Middleware
import middlewarePipeline from './middleware/middlewarePipeline';
import auth from './middleware/auth';
import Countries from '../views/Locations/Countries.vue';
import Achievements from '../views/Achievements/Achievements.vue';
import Redis from '../views/Admin/Redis.vue';
import Terms from '../views/General/Terms.vue';
import Privacy from '../views/General/Privacy.vue';
import World from '../views/Locations/World.vue';
import Uploads from '../views/User/Uploads/Uploads.vue';
import Changelog from '../views/General/Changelog.vue';
import NewAddTags from '../views/General/Tagging/v2/NewAddTags.vue';

const routes = [
    // Public routes
    {
        path: '/about',
        name: 'About',
        component: About,
    },
    {
        path: '/changelog',
        name: 'Changelog',
        component: Changelog,
    },
    {
        path: '/terms',
        name: 'Terms',
        component: Terms,
    },
    {
        path: '/privacy',
        name: 'Privacy',
        component: Privacy,
    },
    {
        path: '/world',
        name: 'World',
        component: World,
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
        path: '/leaderboard',
        name: 'Leaderboard',
        component: Leaderboard,
    },
    {
        path: '/references',
        name: 'References',
        component: References,
    },
    {
        path: '/',
        name: 'Welcome',
        component: Welcome,
    },
    {
        path: '/signup',
        name: 'CreateAccount',
        component: CreateAccount,
    },
    // Auth Routes
    {
        path: '/oldtag',
        name: 'OldTag',
        component: AddTags,
        meta: {
            middleware: [auth],
        },
    },
    {
        path: '/tag',
        name: 'AddTags',
        component: NewAddTags,
        meta: {
            middleware: [auth],
        },
    },
    {
        path: '/upload',
        name: 'Upload',
        component: Upload,
        meta: {
            middleware: [auth],
        },
    },
    {
        path: '/uploads',
        name: 'Uploads',
        component: Uploads,
        meta: {
            middleware: [auth],
        },
    },
    {
        path: '/achievements',
        name: 'Achievements',
        component: Achievements,
        meta: {
            middleware: [auth],
        },
    },
    {
        path: '/admin/redis/:userId?',
        name: 'AdminRedis',
        component: Redis,
        meta: {
            middleware: [auth], // admin
        },
    },
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
