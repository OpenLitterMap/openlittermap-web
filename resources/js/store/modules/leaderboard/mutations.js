export const mutations = {
    /**
     * The global leaderboard request has been recived from the databaase
     */
    setGlobalLeaderboard (state, payload)
    {
        state.paginatedLeaderboard = payload;
    }
}
