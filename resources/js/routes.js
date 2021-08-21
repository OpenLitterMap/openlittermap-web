import VueRouter from 'vue-router'
import store from './store'

// Middleware
import auth from './middleware/auth'
import admin from './middleware/admin'
import can_bbox from './middleware/can_bbox';
import can_verify_boxes from './middleware/can_verify_boxes'

import middlewarePipeline from './middleware/middlewarePipeline'

const router = new VueRouter({
    mode: 'history',
    linkActiveClass: 'is-active',
    routes: [
        // GUEST ROUTES
        {
            path: '/',
            component: require('./views/home/Welcome').default
        },
        {
            path: '/confirm/email/:token',
            component: require('./views/home/Welcome').default
        },
        {
            path: '/emails/unsubscribe/:token',
            component: require('./views/home/Welcome').default
        },
        {
            path: '/about',
            component: require('./views/home/About').default
        },
        {
            path: '/donate',
            component: require('./views/home/Donate').default
        },
        {
            path: '/global',
            component: require('./views/global/GlobalMapContainer').default
        },
        {
            path: '/signup',
            component: require('./views/Auth/SignUp').default
        },
        {
            path: '/join/:plan?',
            component: require('./views/Auth/Subscribe').default
        },
        {
            path: '/terms',
            component: require('./views/general/Terms').default
        },
        {
            path: '/privacy',
            component: require('./views/general/Privacy').default
        },
        {
            path: '/references',
            component: require('./views/general/References').default
        },
        {
            path: '/credits',
            component: require('./views/general/Credits').default
        },
        // Countries
        {
            path: '/world',
            component: require('./views/Locations/Countries').default
        },
        // States
        {
            path: '/world/:country',
            component: require('./views/Locations/States').default
        },
        // Cities
        {
            path: '/world/:country/:state',
            component: require('./views/Locations/Cities').default
        },
        // City - Map
        {
            path: '/world/:country/:state/:city/map/:minDate?/:maxDate?/:hex?',
            component: require('./views/Locations/CityMapContainer').default
        },
        // Admin
        {
            path: '/admin/photos',
            component: require('./views/admin/VerifyPhotos').default,
            meta: {
                middleware: [ auth, admin ]
            }
        },
        // AUTH ROUTES
        {
            path: '/upload',
            component: require('./views/general/Upload').default,
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/tag',
            component: require('./views/general/Tag').default,
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/profile',
            component: require('./views/general/Profile').default,
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/teams',
            component: require('./views/Teams/Teams').default,
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/settings',
            component: require('./views/Settings').default,
            meta: {
                middleware: [ auth ]
            },
            children: [
                {
                    path: 'password',
                    component: require('./views/Settings').default,
                },
                {
                    path: 'details',
                    component: require('./views/Settings/Details').default,
                },
                {
                    path: 'account',
                    component: require('./views/Settings/Account').default,
                },
                {
                    path: 'payments',
                    component: require('./views/Settings/Payments').default,
                },
                {
                    path: 'privacy',
                    component: require('./views/Settings/Privacy').default,
                },
                {
                    path: 'littercoin',
                    component: require('./views/Settings/Littercoin').default,
                },
                {
                    path: 'presence',
                    component: require('./views/Settings/Presence').default,
                },
                {
                    path: 'emails',
                    component: require('./views/Settings/Emails').default,
                },
                {
                    path: 'show-flag',
                    component: require('./views/Settings/GlobalFlag').default,
                },
                {
                    path: 'public-profile',
                    component: require('./views/Settings/PublicProfile').default
                },
                {
                    path: 'social-media',
                    component: require('./views/Settings/SocialMediaIntegration').default
                }
            ]
        },
        {
            path: '/bbox',
            component: require('./views/bbox/BoundingBox').default,
            meta: {
                middleware: [ auth, can_bbox ]
            }
        },
        {
            path: '/bbox/verify',
            component: require('./views/bbox/BoundingBox').default,
            meta: {
                middleware: [ auth, can_verify_boxes ]
            }
        },
        // Public Profile by Username
        {
            path: '/:username?',
            component: require('./views/general/Profile').default,
        }
    ]
});

/**
 * Pipeline for multiple middleware
 */
router.beforeEach((to, from, next) => {

    if (!to.meta.middleware) return next();

    // testing --- this allows store to init before router finishes and returns with auth false
    // await store.dispatch('CHECK_AUTH');

    const middleware = to.meta.middleware

    const context = { to, from, next, store };

    return middleware[0]({
        ...context,
        next: middlewarePipeline(context, middleware, 1)
    });

});

export default router;
