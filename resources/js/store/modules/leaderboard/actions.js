export const actions = {
    /**
     * Get a paginated array of global leaders x100
     */
    async GET_GLOBAL_LEADERBOARD (context)
    {
        await axios.get('/global/leaderboard')
            .then(response => {
                console.log('get_global_leaderboard', response);

                context.commit('setGlobalLeaderboard', response.data.paginated);
            })
            .catch(error => {
                console.error('get_global_leaderboard', error);
            });
    }
}
