import VueRouter from 'vue-router'

// The earlier a route is defined, the higher its priority.

let routes = [
	// GUEST ROUTES
	{
		path: '/',
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
		component: require('./views/global/GlobalMap').default
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
		path: '/world/:country/:state/:city/map',
		component: require('./views/Locations/CityMap').default
	},
	// Admin todo - apply middleware
	{
		path: '/admin/photos',
		component: require('./views/admin/VerifyPhotos').default
	},
	{
		path: '/admin/bbox',
		component: require('./views/admin/BoundingBox').default
	},
	// AUTH ROUTES - Todo, apply middleware
	{
		path: '/upload',
		component: require('./views/general/Upload').default
	},
	{
		path: '/submit', // old route
		component: require('./views/general/Upload').default
	},
	{
		path: '/tag',
		component: require('./views/general/Tag').default
	},
	{
		path: '/profile',
		component: require('./views/general/Profile').default
	},
	{
		path: '/settings',
		component: require('./views/Settings').default,
		children: [
			{
				path: 'password',
				component: require('./views/settings/Password').default
			},
			{
				path: 'details',
				component: require('./views/settings/Details').default
			},
			{
				path: 'account',
				component: require('./views/settings/Account').default
			},
			{
				path: 'payments',
				component: require('./views/settings/Payments').default
			},
			{
				path: 'privacy',
				component: require('./views/settings/Privacy').default
			},
			{
				path: 'littercoin',
				component: require('./views/settings/Littercoin').default
			},
			{
				path: 'presence',
				component: require('./views/settings/Presence').default
			},
			{
				path: 'email',
				component: require('./views/settings/Emails').default
			},
			{
				path: 'show-flag',
				component: require('./views/settings/GlobalFlag').default
			}
			// {
			// 	path: 'teams',
			// 	component: require('./views/Teams').default
			// },
			// {
			// 	path: 'phone',
			// 	component: require('./views/Phone').default
			// }
		]
	}
];

export default new VueRouter({
	mode: 'history',
	routes,
	linkActiveClass: 'is-active',
});
