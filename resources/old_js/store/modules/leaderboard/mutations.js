export const mutations = {
    /**
     * The global leaderboard request has been received from the databaase
     */
    setGlobalLeaderboard (state, payload)
    {
        state.users = payload.users;
        state.hasNextPage = payload.hasNextPage;
    },

    incrementLeaderboardPage (state)
    {
        state.currentPage++;
    },

    decrementLeaderboardPage (state)
    {
        state.currentPage--;
    },

    /**
     * One of the Location.Leaderboard tabs was selected
     */
    setLocationLeaderboard (state, payload)
    {
        state[payload.locationType][payload.locationId] = payload.users;
    }
}
