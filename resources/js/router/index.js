import { createRouter, createWebHistory } from 'vue-router';

// Middleware
import middlewarePipeline from './middleware/middlewarePipeline';
import auth from './middleware/auth';

// Components
import About from '../views/General/About.vue';
import CreateAccount from '../views/Account/CreateAccount.vue';
import ForgotPassword from '../views/Auth/ForgotPassword.vue';
import ResetPassword from '../views/Auth/ResetPassword.vue';
import GlobalMap from '../views/Maps/GlobalMap.vue';
import History from '../views/General/History.vue';
import Leaderboard from '../views/General/Leaderboards/Leaderboard.vue';
import References from '../views/Academic/References.vue';
import Upload from '../views/Upload/Upload.vue';
import Welcome from '../views/Welcome/Welcome.vue';
import Achievements from '../views/Achievements/Achievements.vue';
import Redis from '../views/Admin/Redis.vue';
import Terms from '../views/General/Terms.vue';
import Privacy from '../views/General/Privacy.vue';
import Uploads from '../views/User/Uploads/Uploads.vue';
import Changelog from '../views/General/Changelog.vue';
import AddTags from '../views/General/Tagging/v2/AddTags.vue';
import Locations from '../views/Locations/Locations.vue';

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
        path: '/locations',
        name: 'locations.global',
        component: Locations,
    },
    {
        path: '/locations/country/:id',
        name: 'locations.country',
        component: Locations,
        props: (route) => ({ type: 'country', id: route.params.id }),
    },
    {
        path: '/locations/state/:id',
        name: 'locations.state',
        component: Locations,
        props: (route) => ({ type: 'state', id: route.params.id }),
    },
    {
        path: '/locations/city/:id',
        name: 'locations.city',
        component: Locations,
        props: (route) => ({ type: 'city', id: route.params.id }),
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
    {
        path: '/password/reset',
        name: 'ForgotPassword',
        component: ForgotPassword,
    },
    {
        path: '/password/reset/:token',
        name: 'ResetPassword',
        component: ResetPassword,
    },
    // Auth Routes
    {
        path: '/tag',
        name: 'AddTags',
        component: AddTags,
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
