export const requests = {
    /**
     * Get a paginated array of global leaders x100
     */
    async GET_USERS_FOR_GLOBAL_LEADERBOARD (payload)
    {
        await axios.get('/global/leaderboard', {
            params: {
                timeFilter: payload
            }
        })
        .then(response => {
            console.log('get_users_for_global_leaderboard', response);

            this.leaderboard = response.data.users;
            this.hasNextPage = response.data.hasNextPage;

            // All time global users for GlobalMetaData
            // context.commit('setGlobalLeaders', response.data.users);
            // locations.globalLeaders
        })
        .catch(error => {
            console.error('get_users_for_global_leaderboard', error);
        });
    },

    /**
     * Get the users for one of the Location Leaderboards
     */
    async GET_USERS_FOR_LOCATION_LEADERBOARD (payload)
    {
        await axios.get('/global/leaderboard/location', {
            params: {
                timeFilter: payload?.timeFilter,
                locationType: payload?.locationType,
                locationId: payload?.locationId
            }
        })
        .then(response => {
            console.log('get_users_for_location_leaderboard', response);

            // context.commit('setGlobalLeaderboard', response.data);
            this.leaderboard = response.data.users;
            this.hasNextPage = response.data.hasNextPage;

            // Filter users by location
            this[payload.locationType][payload.locationId] = payload.users;

            this.selectedLocationId = payload.locationId;
            this.locationTabKey++;
        })
        .catch(error => {
            console.error('get_users_for_location_leaderboard', error);
        });
    },

    /**
     * Get the next page of Users for the Leaderboard
     */
    async GET_NEXT_LEADERBOARD_PAGE ()
    {
        this.currentPage++;

        await axios.get('/global/leaderboard', {
            params: {
                page: this.currentPage
            }
        })
        .then(response => {
            console.log('get_next_leaderboard_page', response);

            this.leaderboard = response.data.users;
            this.hasNextPage = response.data.hasNextPage;
        })
        .catch(error => {
            console.error('get_next_leaderboard_page', error);
        });
    },

    /**
     * Get the previous page of Users for the Leaderboard
     */
    async GET_PREVIOUS_LEADERBOARD_PAGE ()
    {
        this.currentPage--;

        await axios.get('/global/leaderboard', {
            params: {
                page: this.currentPage
            }
        })
        .then(response => {
            console.log('get_previous_leaderboard_page', response);

            this.leaderboard = response.data.users;
            this.hasNextPage = response.data.hasNextPage;
        })
        .catch(error => {
            console.error('get_previous_leaderboard_page', error);
        });
    }
}
