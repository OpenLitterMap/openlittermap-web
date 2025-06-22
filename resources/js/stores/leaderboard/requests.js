export const requests = {
    /**
     * Get a paginated array of global leaders x100
     */
    async GET_USERS_FOR_GLOBAL_LEADERBOARD(timeFilter = 'all-time') {
        // Store current filter for pagination
        this.currentFilters = {
            timeFilter,
            locationType: null,
            locationId: null,
        };
        this.currentPage = 1;

        await axios
            .get('/api/leaderboard', {
                params: {
                    timeFilter,
                    page: 1,
                },
            })
            .then((response) => {
                console.log('get_users_for_global_leaderboard', response);

                this.leaderboard = response.data.users;
                this.hasNextPage = response.data.hasNextPage;
            })
            .catch((error) => {
                console.error('get_users_for_global_leaderboard', error);
            });
    },

    /**
     * Get the users for one of the Location Leaderboards
     */
    async GET_USERS_FOR_LOCATION_LEADERBOARD(payload) {
        // Store current filters for pagination
        this.currentFilters = {
            timeFilter: payload?.timeFilter || 'all-time',
            locationType: payload?.locationType,
            locationId: payload?.locationId,
        };
        this.currentPage = 1;

        await axios
            .get('/api/leaderboard', {
                params: {
                    timeFilter: payload?.timeFilter,
                    locationType: payload?.locationType,
                    locationId: payload?.locationId,
                    page: 1,
                },
            })
            .then((response) => {
                console.log('get_users_for_location_leaderboard', response);

                this.leaderboard = response.data.users;
                this.hasNextPage = response.data.hasNextPage;

                // Store location-specific data if needed
                if (payload.locationType && payload.locationId) {
                    this[payload.locationType][payload.locationId] = response.data.users;
                }

                this.selectedLocationId = payload.locationId;
                this.locationTabKey++;
            })
            .catch((error) => {
                console.error('get_users_for_location_leaderboard', error);
            });
    },

    /**
     * Get the next page of Users for the Leaderboard
     */
    async GET_NEXT_LEADERBOARD_PAGE() {
        this.currentPage++;

        await axios
            .get('/api/leaderboard', {
                params: {
                    ...this.currentFilters,
                    page: this.currentPage,
                },
            })
            .then((response) => {
                console.log('get_next_leaderboard_page', response);

                this.leaderboard = response.data.users;
                this.hasNextPage = response.data.hasNextPage;
            })
            .catch((error) => {
                console.error('get_next_leaderboard_page', error);
                this.currentPage--; // Revert on error
            });
    },

    /**
     * Get the previous page of Users for the Leaderboard
     */
    async GET_PREVIOUS_LEADERBOARD_PAGE() {
        this.currentPage--;

        await axios
            .get('/api/leaderboard', {
                params: {
                    ...this.currentFilters,
                    page: this.currentPage,
                },
            })
            .then((response) => {
                console.log('get_previous_leaderboard_page', response);

                this.leaderboard = response.data.users;
                this.hasNextPage = response.data.hasNextPage;
            })
            .catch((error) => {
                console.error('get_previous_leaderboard_page', error);
                this.currentPage++; // Revert on error
            });
    },
};
