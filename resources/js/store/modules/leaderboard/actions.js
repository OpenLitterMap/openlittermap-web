export const actions = {
    /**
     * Get a paginated array of global leaders x100
     */
    async GET_USERS_FOR_LEADERBOARD (context, payload = null)
    {
        await axios.get('/global/leaderboard', {
            params: {
                timeFilter: payload?.option ?? null,
                locationType: payload?.locationType ?? null,
                locationId: payload?.locationId ?? null
            }
        })
        .then(response => {
            console.log('get_global_leaderboard', response);

            context.commit('setGlobalLeaderboard', response.data);

            if (payload.locationType && payload.locationId) {
                
                // Filter users by location
                context.commit('setLocationLeaderboard', {
                    locationType: payload.locationType,
                    locationId: payload.locationId,
                    users: response.data.users
                });

                context.commit('setSelectedLocationId', payload.locationId);
                context.commit('updateLocationTabKey');

            } else {
                // All time global users
                // for GlobalMetaData
                context.commit('setGlobalLeaders', response.data.users);
            }
        })
        .catch(error => {
            console.error('get_global_leaderboard', error);
        });
    },

    /**
     * Get the next page of Users for the Leaderboard
     */
    async GET_NEXT_LEADERBOARD_PAGE (context)
    {
        context.commit('incrementLeaderboardPage');

        await axios.get('/global/leaderboard', {
            params: {
                page: context.state.currentPage
            }
        })
        .then(response => {
            console.log('get_next_leaderboard_page', response);

            context.commit('setGlobalLeaderboard', response.data);
        })
        .catch(error => {
            console.error('get_next_leaderboard_page', error);
        });
    },

    /**
     * Get the previous page of Users for the Leaderboard
     */
    async GET_PREVIOUS_LEADERBOARD_PAGE (context)
    {
        context.commit('decrementLeaderboardPage');

        await axios.get('/global/leaderboard', {
            params: {
                page: context.state.currentPage
            }
        })
        .then(response => {
            console.log('get_previous_leaderboard_page', response);

            context.commit('setGlobalLeaderboard', response.data);
        })
        .catch(error => {
            console.error('get_previous_leaderboard_page', error);
        });
    }
}
