import { defineStore } from 'pinia';
import axios from 'axios';

export const useLocationsStore = defineStore('locations', {
    state: () => ({
        // Location hierarchy
        locationType: 'country', // 'country', 'state', 'city'
        locations: [], // Current list of locations
        selectedLocation: null,

        // Navigation breadcrumbs
        countryName: '',
        countryId: null,
        stateName: '',
        stateId: null,
        cityName: '',
        cityId: null,

        // Global statistics
        globalStats: {
            total_litter: 0,
            total_photos: 0,
            total_contributors: 0,
            total_countries: 0,
            level: 0,
            current_xp: 0,
            previous_xp: 0,
            next_xp: 0,
            progress: 0,
        },

        // Sorting and filtering
        sortBy: 'total_litter_redis',
        sortDirection: 'desc',
        searchQuery: '',
        viewMode: 'grid', // 'grid' or 'list'

        // Pagination
        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 12,
            total: 0,
            from: 0,
            to: 0,
        },

        // Loading states
        loading: false,
        loadingDetails: false,

        // Cached data
        categoryBreakdown: {},
        timeSeries: {},
        leaderboards: {},

        // UI State
        selectedTab: 'leaderboard',
        selectedPeriod: 'all_time',

        // Legacy support
        littercoin: 0,
        globalLeaders: [],
    }),

    getters: {
        // Get sorted and filtered locations
        sortedLocations(state) {
            let filtered = [...state.locations];

            // Apply search filter
            if (state.searchQuery) {
                const query = state.searchQuery.toLowerCase();
                filtered = filtered.filter((loc) => {
                    const name = loc[state.locationType]?.toLowerCase() || loc.name?.toLowerCase() || '';
                    return name.includes(query);
                });
            }

            // Apply sorting
            filtered.sort((a, b) => {
                let aVal, bVal;

                switch (state.sortBy) {
                    case 'alphabetical':
                        aVal = a[state.locationType] || a.name || '';
                        bVal = b[state.locationType] || b.name || '';
                        return state.sortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);

                    case 'most-data':
                        aVal = a.total_litter_redis || 0;
                        bVal = b.total_litter_redis || 0;
                        break;

                    case 'most-data-per-person':
                        aVal = a.avg_litter_per_user || 0;
                        bVal = b.avg_litter_per_user || 0;
                        break;

                    case 'total-contributors':
                        aVal = a.total_contributors_redis || 0;
                        bVal = b.total_contributors_redis || 0;
                        break;

                    case 'most-recently-updated':
                        aVal = new Date(a.updated_at || 0).getTime();
                        bVal = new Date(b.updated_at || 0).getTime();
                        break;

                    case 'first-created':
                        aVal = new Date(a.created_at || 0).getTime();
                        bVal = new Date(b.created_at || 0).getTime();
                        break;

                    case 'most-recently-created':
                        aVal = new Date(a.created_at || 0).getTime();
                        bVal = new Date(b.created_at || 0).getTime();
                        break;

                    default:
                        aVal = a[state.sortBy] || 0;
                        bVal = b[state.sortBy] || 0;
                }

                if (state.sortDirection === 'asc') {
                    return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                } else {
                    return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                }
            });

            return filtered;
        },

        // Breadcrumb trail for navigation
        breadcrumbs(state) {
            const crumbs = [{ name: 'World', path: '/world' }];

            if (state.countryName) {
                crumbs.push({
                    name: state.countryName,
                    path: `/world/${state.countryName}`,
                });
            }

            if (state.stateName) {
                crumbs.push({
                    name: state.stateName,
                    path: `/world/${state.countryName}/${state.stateName}`,
                });
            }

            if (state.cityName) {
                crumbs.push({
                    name: state.cityName,
                    path: `/world/${state.countryName}/${state.stateName}/${state.cityName}`,
                });
            }

            return crumbs;
        },

        // For backwards compatibility
        total_litter: (state) => state.globalStats.total_litter,
        total_photos: (state) => state.globalStats.total_photos,
        total_contributors: (state) => state.globalStats.total_contributors,
        total_countries: (state) => state.globalStats.total_countries,
    },

    actions: {
        // Fetch locations based on type and parent
        async fetchLocations(type = 'country', parentId = null, page = 1) {
            this.loading = true;
            this.locationType = type;

            try {
                const params = {
                    page,
                    per_page: this.pagination.per_page,
                    sort_by: this.sortBy,
                    sort_dir: this.sortDirection,
                };

                if (parentId) {
                    params.parent_id = parentId;
                }

                const response = await axios.get(`/api/locations/${type}`, { params });

                this.locations = response.data.locations || [];
                this.pagination = response.data.pagination || {};

                // Update aggregated stats if provided
                if (response.data.aggregated_stats) {
                    this.updateAggregatedStats(response.data.aggregated_stats);
                }

                return response.data;
            } catch (error) {
                console.error(`Error fetching ${type} locations:`, error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        // Fetch world cup data (countries with global stats)
        async fetchWorldCupData() {
            this.loading = true;

            try {
                const response = await axios.get('/api/locations/world-cup', {
                    params: {
                        page: this.pagination.current_page,
                        per_page: this.pagination.per_page,
                        sort_by: this.sortBy,
                        sort_dir: this.sortDirection,
                    },
                });

                this.locations = response.data.countries || [];
                this.pagination = response.data.pagination || {};
                this.globalStats = response.data.global_stats || {};
                this.globalLeaders = response.data.global_leaderboard || [];
                this.locationType = 'country';

                // Legacy support
                this.littercoin = response.data.littercoin || 0;

                return response.data;
            } catch (error) {
                console.error('Error fetching world cup data:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        // Fetch details for a specific location
        async fetchLocationDetails(type, id) {
            this.loadingDetails = true;

            try {
                const response = await axios.get(`/api/locations/${type}/${id}`);
                this.selectedLocation = response.data;

                // Cache category breakdown if included
                if (response.data.category_breakdown) {
                    this.categoryBreakdown[`${type}-${id}`] = response.data.category_breakdown;
                }

                // Cache time series if included
                if (response.data.time_series) {
                    this.timeSeries[`${type}-${id}`] = response.data.time_series;
                }

                // Cache leaderboard if included
                if (response.data.leaderboard) {
                    this.leaderboards[`${type}-${id}`] = response.data.leaderboard;
                }

                return response.data;
            } catch (error) {
                console.error(`Error fetching ${type} details:`, error);
                throw error;
            } finally {
                this.loadingDetails = false;
            }
        },

        // Fetch category breakdown
        async fetchCategoryBreakdown(type, id) {
            const cacheKey = `${type}-${id}`;

            // Return cached if available
            if (this.categoryBreakdown[cacheKey]) {
                return this.categoryBreakdown[cacheKey];
            }

            try {
                const response = await axios.get(`/api/locations/${type}/${id}/categories`);
                this.categoryBreakdown[cacheKey] = response.data;
                return response.data;
            } catch (error) {
                console.error('Error fetching category breakdown:', error);
                throw error;
            }
        },

        // Fetch time series data
        async fetchTimeSeries(type, id, period = 'daily') {
            const cacheKey = `${type}-${id}-${period}`;

            // Return cached if available
            if (this.timeSeries[cacheKey]) {
                return this.timeSeries[cacheKey];
            }

            try {
                const response = await axios.get(`/api/locations/${type}/${id}/timeseries`, {
                    params: { period },
                });
                this.timeSeries[cacheKey] = response.data;
                return response.data;
            } catch (error) {
                console.error('Error fetching time series:', error);
                throw error;
            }
        },

        // Fetch leaderboard
        async fetchLeaderboard(type, id, period = 'all_time') {
            const cacheKey = `${type}-${id}-${period}`;

            // Return cached if available
            if (this.leaderboards[cacheKey]) {
                return this.leaderboards[cacheKey];
            }

            try {
                const response = await axios.get(`/api/locations/${type}/${id}/leaderboard`, {
                    params: { period },
                });
                this.leaderboards[cacheKey] = response.data;
                return response.data;
            } catch (error) {
                console.error('Error fetching leaderboard:', error);
                throw error;
            }
        },

        // Fetch global statistics
        async fetchGlobalStats() {
            try {
                const response = await axios.get('/api/locations/global');
                this.globalStats = response.data.statistics || {};
                return response.data;
            } catch (error) {
                console.error('Error fetching global stats:', error);
                throw error;
            }
        },

        // Navigation methods
        navigateToCountry(country) {
            this.countryName = country.country;
            this.countryId = country.id;
            this.stateName = '';
            this.stateId = null;
            this.cityName = '';
            this.cityId = null;
            this.locationType = 'state';
            this.locations = [];
            this.fetchLocations('state', country.id);
        },

        navigateToState(state) {
            this.stateName = state.state;
            this.stateId = state.id;
            this.cityName = '';
            this.cityId = null;
            this.locationType = 'city';
            this.locations = [];
            this.fetchLocations('city', state.id);
        },

        navigateToCity(city) {
            this.cityName = city.city;
            this.cityId = city.id;
            this.selectedLocation = city;
        },

        // Setters
        setSortBy(value) {
            this.sortBy = value;
        },

        setSortDirection(value) {
            this.sortDirection = value;
        },

        setSearchQuery(value) {
            this.searchQuery = value;
        },

        setViewMode(value) {
            this.viewMode = value;
        },

        setSelectedLocation(location) {
            this.selectedLocation = location;
        },

        setSelectedTab(tab) {
            this.selectedTab = tab;
        },

        setSelectedPeriod(period) {
            this.selectedPeriod = period;
        },

        setPage(page) {
            this.pagination.current_page = page;
        },

        // Update methods
        updateAggregatedStats(stats) {
            if (stats.total_photos !== undefined) {
                this.globalStats.total_photos = stats.total_photos;
            }
            if (stats.total_litter !== undefined) {
                this.globalStats.total_litter = stats.total_litter;
            }
            if (stats.total_contributors !== undefined) {
                this.globalStats.total_contributors = stats.total_contributors;
            }
        },

        incrementTotalPhotos() {
            this.globalStats.total_photos++;
        },

        incrementTotalLitter(amount = 1) {
            this.globalStats.total_litter += amount;
        },

        // Clear/Reset methods
        clearLocations() {
            this.locations = [];
            this.pagination = {
                current_page: 1,
                last_page: 1,
                per_page: 12,
                total: 0,
                from: 0,
                to: 0,
            };
        },

        resetFilters() {
            this.searchQuery = '';
            this.sortBy = 'total_litter_redis';
            this.sortDirection = 'desc';
        },

        // Legacy support methods
        setSortLocationsBy(sort) {
            const sortMap = {
                alphabetical: 'alphabetical',
                'most-data': 'total_litter_redis',
                'most-data-per-person': 'avg_litter_per_user',
                'total-contributors': 'total_contributors_redis',
                'first-created': 'created_at',
                'most-recently-created': 'created_at',
                'most-recently-updated': 'updated_at',
            };

            this.sortBy = sortMap[sort] || sort;

            // Adjust direction based on sort type
            if (['first-created', 'alphabetical'].includes(sort)) {
                this.sortDirection = 'asc';
            } else {
                this.sortDirection = 'desc';
            }
        },

        // Legacy method names for backwards compatibility
        async GET_WORLD_CUP_DATA() {
            return this.fetchWorldCupData();
        },

        async GET_LOCATIONS_PAGE(page) {
            this.setPage(page);
            return this.fetchLocations(this.locationType, null, page);
        },
    },
});
