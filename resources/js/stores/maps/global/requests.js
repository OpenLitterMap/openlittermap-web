export const requests = {
    /**
     * Get the art point data for the global map
     */
    async GET_ART_DATA ()
    {
        await axios.get('/global/art-data')
            .then(response => {
                console.log('get_art_data', response);

                this.artData = response.data;
            })
            .catch(error => {
                console.error('get_art_data', error);
            });
    },

    /**
     * Get clusters for the global map
     */
    async GET_CLUSTERS (payload)
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

            this.geojson = response.data;
        })
        .catch(error => {
            console.error('get_clusters', error);
        });
    },

    /**
     * Load custom tags for the global map
     */
    async SEARCH_CUSTOM_TAGS (payload)
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
                this.customTagsFound = response.data.tags;
            }
        })
        .catch(error => {
            console.error('search_custom_tags', error);
        });
    }
}
