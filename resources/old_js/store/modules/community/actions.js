export const actions = {
    /**
     * Get the stats for the community page
     */
    async GET_STATS (context)
    {
        await axios.get('/community/stats')
            .then(response => {
                console.log('get_stats', response);

                context.commit('setStats', response.data);
            })
            .catch(error => {
                console.error('get_stats', error);
            });
    },
}
