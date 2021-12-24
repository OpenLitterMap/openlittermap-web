export const actions = {
    /**
     * Get the art point data for the global map
     */
    async GET_ART_DATA (context)
    {
        await axios.get('/global/art-data')
            .then(response => {
                console.log('get_art_data', response);

                context.commit('globalArtData', response.data);
            })
            .catch(error => {
                console.error('get_art_data', error);
            });
    },

    /**
     * Get clusters for the global map
     */
    async GET_CLUSTERS (context, payload)
    {
        await axios.get('/global/clusters', {
            params: {
                zoom: payload,
                bbox: null
            }
        })
        .then(response => {
            console.log('get_clusters', response);

            context.commit('updateGlobalData', response.data);
        })
        .catch(error => {
            console.error('get_clusters', error);
        });
    },

    /**
     * Get clusters for the teams map
     */
    async GET_TEAMS_CLUSTERS (context, payload)
    {
        await axios.get(`/teams/clusters/${payload.team_id}`, {
            params: {
                zoom: payload.zoom,
                bbox: null
            }
        })
        .then(response => {
            console.log('get_teams_clusters', response);

            context.commit('updateGlobalData', response.data);
        })
        .catch(error => {
            console.error('get_teams_clusters', error);
        });
    },

    /**
     * Get the art point data for the teams map
     */
    async GET_TEAMS_ART_DATA (context, payload)
    {
        await axios.get(`/teams/art-data/${payload.team_id}`)
        .then(response => {
            console.log('get_teams_art_data', response);

            context.commit('globalArtData', response.data);
        })
        .catch(error => {
            console.error('get_teams_art_data', error);
        });
    }
}
