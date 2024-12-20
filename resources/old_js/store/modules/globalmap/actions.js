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
                zoom: payload.zoom,
                year: payload.year,
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
     *
     */
    async SEARCH_CUSTOM_TAGS (context, payload)
    {
        await axios.get('/global/search/custom-tags', {
            params: {
                search: payload
            }
        })
        .then(response => {
            console.log('search_custom_tags', response);

            if (response.data.success)
            {
                context.commit('setCustomTagsFound', response.data.tags);
            }
        })
        .catch(error => {
            console.error('search_custom_tags', error);
        });
    }
}
