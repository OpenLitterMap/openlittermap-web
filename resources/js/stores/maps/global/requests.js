import axios from 'axios';

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
    async GET_POINTS({
        zoom,
        bbox,
        year = null,
        fromDate = null,
        toDate = null,
        username = null,
        layers = null,
        filters = null,
        page = 1,
        per_page = 300,
        append = false,
    }) {
        // Prepare parameters for the new API
        const params = {
            zoom: Math.round(zoom),
            bbox: {
                left: bbox.left || bbox._sw?.lng,
                bottom: bbox.bottom || bbox._sw?.lat,
                right: bbox.right || bbox._ne?.lng,
                top: bbox.top || bbox._ne?.lat,
            },
            page,
            per_page,
        };

        // Add year filter if provided
        if (year) {
            params.year = year;
        }

        // Add date filters if provided
        if (fromDate) {
            params.from = fromDate; // Should be YYYY-MM-DD format
        }
        if (toDate) {
            params.to = toDate; // Should be YYYY-MM-DD format
        }

        // Add username filter if provided
        if (username) {
            params.username = username;
        }

        // Use provided filters or fall back to store's active filters
        const appliedFilters = filters || this.activeFilters;

        // Convert layers array to filter structure if provided
        if (layers && layers.length > 0) {
            // Assuming layers is an array of category names
            params.categories = layers;
        } else if (appliedFilters.categories?.length > 0) {
            params.categories = appliedFilters.categories;
        }

        // Add other filters
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

        await axios
            .get('/api/points', { params })
            .then((response) => {
                console.log('get_points', response);

                // Handle append mode for pagination
                if (append && this.pointsGeojson.features.length > 0) {
                    this.pointsGeojson.features = [...this.pointsGeojson.features, ...response.data.features];
                } else {
                    this.pointsGeojson = response.data;
                }

                // Update pagination metadata
                if (response.data.meta) {
                    this.pointsPagination = {
                        current_page: response.data.meta.current_page || page,
                        last_page: response.data.meta.last_page || 1,
                        per_page: response.data.meta.per_page || per_page,
                        total: response.data.meta.total || 0,
                        has_more: response.data.meta.has_more_pages || false,
                    };
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
