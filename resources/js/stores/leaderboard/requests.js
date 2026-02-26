export const requests = {
    /**
     * Unified leaderboard fetch with optional location filtering
     */
    async FETCH_LEADERBOARD({ timeFilter = 'all-time', locationType = null, locationId = null, page = 1 } = {}) {
        this.currentFilters = { timeFilter, locationType, locationId };
        this.currentPage = page;
        this.loading = true;
        this.error = null;

        try {
            const params = { timeFilter, page };

            if (locationType && locationId) {
                params.locationType = locationType;
                params.locationId = locationId;
            }

            const { data } = await axios.get('/api/leaderboard', { params });

            this.leaderboard = data.users;
            this.hasNextPage = data.hasNextPage;
            this.total = data.total ?? 0;
            this.currentUserRank = data.currentUserRank ?? null;
        } catch (e) {
            this.error = e.response?.status === 401 ? 'unauthenticated' : 'Failed to load leaderboard';
        } finally {
            this.loading = false;
        }
    },

    /**
     * Load country list for location filter dropdown
     */
    async FETCH_COUNTRIES() {
        if (this.countries.length > 0) return;

        try {
            const { data } = await axios.get('/api/v1/locations');
            this.countries = (data.locations || []).map((c) => ({ id: c.id, name: c.name }));
        } catch (e) {
            // Non-critical — silently fail
        }
    },

    /**
     * Load states for a given country
     */
    async FETCH_STATES(countryId) {
        this.states = [];
        this.cities = [];

        try {
            const { data } = await axios.get(`/api/v1/locations/country/${countryId}`);
            this.states = (data.locations || []).map((s) => ({ id: s.id, name: s.name }));
        } catch (e) {
            // Non-critical — silently fail
        }
    },

    /**
     * Load cities for a given state
     */
    async FETCH_CITIES(stateId) {
        this.cities = [];

        try {
            const { data } = await axios.get(`/api/v1/locations/state/${stateId}`);
            this.cities = (data.locations || []).map((c) => ({ id: c.id, name: c.name }));
        } catch (e) {
            // Non-critical — silently fail
        }
    },

    // Backward-compatible wrappers
    async GET_USERS_FOR_GLOBAL_LEADERBOARD(timeFilter = 'all-time') {
        await this.FETCH_LEADERBOARD({ timeFilter });
    },

    async GET_USERS_FOR_LOCATION_LEADERBOARD(payload) {
        await this.FETCH_LEADERBOARD({
            timeFilter: payload?.timeFilter || 'all-time',
            locationType: payload?.locationType,
            locationId: payload?.locationId,
        });
    },

    async GET_NEXT_LEADERBOARD_PAGE() {
        await this.FETCH_LEADERBOARD({
            ...this.currentFilters,
            page: this.currentPage + 1,
        });
    },

    async GET_PREVIOUS_LEADERBOARD_PAGE() {
        await this.FETCH_LEADERBOARD({
            ...this.currentFilters,
            page: this.currentPage - 1,
        });
    },
};
