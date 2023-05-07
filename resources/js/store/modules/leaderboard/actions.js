export const actions = {
    /**
     * Get a paginated array of global leaders x100
     */
    async GET_GLOBAL_LEADERBOARD (context, payload = null)
    {
        await axios.get('/global/leaderboard', {
            params: {
                filter: payload
            }
        })
        .then(response => {
            console.log('get_global_leaderboard', response);

            context.commit('setGlobalLeaderboard', response.data);

            // for GlobalMetaData
            context.commit('setGlobalLeaders', response.data);
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
