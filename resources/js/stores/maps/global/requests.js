export const requests = {
    /**
     * Get the art point data for the global map
     */
    async GET_ART_DATA() {
        await axios
            .get('/global/art-data')
            .then((response) => {
                console.log('get_art_data', response);
                this.artData = response.data;
            })
            .catch((error) => {
                console.error('get_art_data', error);
            });
    },

    /**
     * Get clusters for the global map
     */
    async GET_CLUSTERS({ zoom, year, bbox = null }) {
        await axios
            .get('/api/clusters', {
                params: { zoom, year, bbox },
            })
            .then((response) => {
                console.log('get_clusters', response);
                this.clustersGeojson = response.data;
            })
            .catch((error) => {
                console.error('get_clusters', error);
            });
    },

    /**
     * Get points for the global map using the new API
     */
    async GET_POINTS({ zoom, bbox, from = null, to = null, username = null, filters = null }) {
        // Prepare parameters for the new API
        const params = {
            zoom: Math.round(zoom), // API requires integer zoom
            bbox: {
                left: bbox.left || bbox._sw?.lng,
                bottom: bbox.bottom || bbox._sw?.lat,
                right: bbox.right || bbox._ne?.lng,
                top: bbox.top || bbox._ne?.lat,
            },
        };

        // Add date filters if provided
        if (from) {
            params.from = from; // Should be YYYY-MM-DD format
        }
        if (to) {
            params.to = to; // Should be YYYY-MM-DD format
        }

        // Add username filter if provided
        if (username) {
            params.username = username;
        }

        // Use provided filters or fall back to store's active filters
        const appliedFilters = filters || this.activeFilters;

        // Add filters from either source
        if (appliedFilters.categories?.length > 0) {
            params.categories = appliedFilters.categories;
        }
        if (appliedFilters.litter_objects?.length > 0) {
            params.litter_objects = appliedFilters.litter_objects;
        }
        if (appliedFilters.materials?.length > 0) {
            params.materials = appliedFilters.materials;
        }
        if (appliedFilters.brands?.length > 0) {
            params.brands = appliedFilters.brands;
        }
        if (appliedFilters.custom_tags?.length > 0) {
            params.custom_tags = appliedFilters.custom_tags;
        }

        // Optional: Add pagination for lower zoom levels
        // params.per_page = 300; // Default is 300
        // params.page = 1;

        await axios
            .get('/api/points', { params })
            .then((response) => {
                console.log('get_points', response);

                this.pointsGeojson = response.data;

                // Handle metadata if needed
                if (response.data.meta) {
                    console.log('Points metadata:', response.data.meta);
                    // You can store metadata in state if needed
                }
            })
            .catch((error) => {
                console.error('get_points', error);

                // Handle validation errors
                if (error.response?.status === 422) {
                    console.error('Validation errors:', error.response.data.errors);
                }
            });
    },

    /**
     * Load custom tags for the global map
     */
    async SEARCH_CUSTOM_TAGS(payload) {
        await axios
            .get('/global/search/custom-tags', {
                params: {
                    search: payload,
                },
            })
            .then((response) => {
                console.log('search_custom_tags', response);

                if (response.data.success) {
                    this.customTagsFound = response.data.tags;
                }
            })
            .catch((error) => {
                console.error('search_custom_tags', error);
            });
    },
};
