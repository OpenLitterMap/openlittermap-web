import VueRouter from 'vue-router'
import store from './store'

// Middleware
import auth from './middleware/auth'
import admin from './middleware/admin'
import can_bbox from './middleware/can_bbox';
import can_verify_boxes from './middleware/can_verify_boxes'

import middlewarePipeline from './middleware/middlewarePipeline'

// The earlier a route is defined, the higher its priority.
const router = new VueRouter({
    mode: 'history',
    // base: process.env.BASE_URL, // not sure if we need this?
    linkActiveClass: 'is-active',
    routes: [
        // GUEST ROUTES
        {
            path: '/',
            component: () => import('./views/home/Welcome.vue')
        },
        {
            path: '/confirm/email/:token',
            component: () => import('./views/home/Welcome.vue')
        },
        {
            path: '/password/reset',
            component: () => import('./views/Auth/passwords/Email.vue')
        },
        {
            path: '/password/reset/:token',
            component: () => import('./views/Auth/passwords/Reset.vue'),
            props: true
        },
        {
            path: '/emails/unsubscribe/:token',
            component: () => import('./views/home/Welcome.vue')
        },
        {
            path: '/about',
            component: () => import('./views/home/About.vue')
        },
        {
            path: '/cleanups',
            component: () => import('./views/home/Cleanups.vue'),
            children: [
                {
                    path: ':invite_link/join',
                    component: () => import('./views/home/Cleanups.vue')
                }
            ]
        },
        {
            path: '/history',
            component: () => import('./views/general/History.vue')
        },
        // {
        //     path: '/littercoin',
        //     component: () => import('./views/home/Littercoin.vue')
        // },
        {
            path: '/littercoin/merchants',
            component: () => import('./views/home/Merchants.vue')
        },
        {
            path: '/donate',
            component: () => import('./views/home/Donate.vue')
        },
        {
            path: '/contact-us',
            component: () => import('./views/home/ContactUs.vue')
        },
        {
            path: '/community',
            component: () => import('./views/home/Community/Index.vue')
        },
        {
            path: '/faq',
            component: () => import('./views/home/FAQ.vue')
        },
        {
            path: '/global',
            component: () => import('./views/global/GlobalMapContainer.vue')
        },
        {
            path: '/tags',
            component: () => import('./views/home/TagsViewer.vue')
        },
        {
            path: '/signup',
            component: () => import('./views/Auth/SignUp.vue')
        },
        {
            path: '/join/:plan?',
            component: () => import('./views/Auth/Subscribe.vue')
        },
        {
            path: '/terms',
            component: () => import('./views/general/Terms.vue')
        },
        {
            path: '/privacy',
            component: () => import('./views/general/Privacy.vue')
        },
        {
            path: '/references',
            component: () => import('./views/general/References.vue')
        },
        {
            path: '/leaderboard',
            component: () => import('./views/Leaderboard/Leaderboard.vue')
        },
        {
            path: '/credits',
            component: () => import('./views/general/Credits.vue')
        },
        // Countries
        {
            path: '/world',
            component: () => import('./views/Locations/Countries.vue')
        },
        // States
        {
            path: '/world/:country',
            component: () => import('./views/Locations/States.vue')
        },
        // Cities
        {
            path: '/world/:country/:state',
            component: () => import('./views/Locations/Cities.vue')
        },
        // City - Map
        {
            path: '/world/:country/:state/:city/map/:minDate?/:maxDate?/:hex?',
            component: () => import('./views/Locations/CityMapContainer.vue')
        },
        // Admin
        {
            path: '/admin/photos',
            component: () => import('./views/admin/VerifyPhotos.vue'),
            meta: {
                middleware: [ auth, admin ]
            }
        },
        {
            path: '/admin/merchants',
            component: () => import('./views/admin/Merchants.vue'),
            meta: {
                middleware: [ auth, admin ]
            }
        },
        // AUTH ROUTES
        {
            path: '/upload',
            component: () => import('./views/general/Upload.vue'),
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/submit', // old route
            component: () => import('./views/general/Upload.vue'),
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/tag',
            component: () => import('./views/general/Tag.vue'),
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/bulk-tag',
            component: () => import('./views/general/BulkTag.vue'),
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/profile',
            component: () => import('./views/general/Profile.vue'),
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/my-uploads',
            component: () => import('./views/general/MyUploads.vue'),
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/teams',
            component: () => import('./views/Teams/Teams.vue'),
            meta: {
                middleware: [ auth ]
            }
        },
        {
            path: '/settings',
            component: () => import('./views/Settings.vue'),
            meta: {
                middleware: [ auth ]
            },
            children: [
                {
                    path: 'password',
                    component: () => import('./views/Settings.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'details',
                    component: () => import('./views/settings/Details.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'social',
                    component: () => import('./views/settings/Social.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'account',
                    component: () => import('./views/settings/Account.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'payments',
                    component: () => import('./views/settings/Payments.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'privacy',
                    component: () => import('./views/settings/Privacy.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'littercoin',
                    component: () => import('./views/settings/Littercoin.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'picked-up',
                    component: () => import('./views/settings/PickedUp.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'emails',
                    component: () => import('./views/settings/Emails.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                {
                    path: 'show-flag',
                    component: () => import('./views/settings/GlobalFlag.vue'),
                    meta: {
                        middleware: [ auth ]
                    },
                },
                // {
                // 	path: 'phone',
                // 	component: () => import('./views/Phone.vue')
                // }
            ]
        },
        {
            path: '/bbox',
            component: () => import('./views/bbox/BoundingBox.vue'),
            meta: {
                middleware: [ auth, can_bbox ]
            }
        },
        {
            path: '/bbox/verify',
            component: () => import('./views/bbox/BoundingBox.vue'),
            meta: {
                middleware: [ auth, can_verify_boxes ]
            }
        }
    ]
});

/**
 * Pipeline for multiple middleware
 */
router.beforeEach((to, from, next) => {

    if (! to.meta.middleware) return next();

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
