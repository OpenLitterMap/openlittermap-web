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
    async GET_CLUSTERS ({ zoom, year, bbox = null })
    {
        await axios.get('/global/clusters', {
            params: { zoom, year, bbox }
        })
        .then(response => {
            console.log('get_clusters', response);

            this.clustersGeojson = response.data;
        })
        .catch(error => {
            console.error('get_clusters', error);
        });
    },

    /**
     * Get points for the global map
     */
    async GET_POINTS ({
        zoom,
        bbox,
        layers,
        year,
        fromDate,
        toDate,
        username
    })
    {
        await axios.get('/global/points', {
            params: {
                zoom,
                bbox,
                layers,
                year,
                fromDate,
                toDate,
                username
            }
        })
        .then(response => {
            console.log('get_points', response);

            this.pointsGeojson = response.data;
        })
        .catch(error => {
            console.error('get_points', error);
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
