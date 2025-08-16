import axios from 'axios';

// points/requests.js
export const requests = {
    // Get points for a specific bounding box and zoom level
    async GET_POINTS({ zoom, bbox }) {
        try {
            this.setLoading(true);
            this.setError(null);

            // Use bracket notation as shown in the working component
            const params = {
                zoom: Math.round(zoom),
                'bbox[left]': bbox.left,
                'bbox[bottom]': bbox.bottom,
                'bbox[right]': bbox.right,
                'bbox[top]': bbox.top,
            };

            console.log('Sending params with bracket notation:', params);

            const response = await axios.get('/api/points', {
                params: params,
            });

            console.log('GET_POINTS response:', response.data);
            console.log('Response status:', response.status);
            console.log('Features count:', response.data?.features?.length || 0);

            // Store the response data in the expected format
            this.setPointsGeojson(response.data);
            this.setLoading(false);

            return response.data;
        } catch (error) {
            console.error('Failed to load points:', error);
            console.error('Error response:', error.response?.data);
            console.error('Error status:', error.response?.status);

            // Log the actual error details
            if (error.response?.data?.errors) {
                console.error('Validation errors:', error.response.data.errors);
                Object.keys(error.response.data.errors).forEach((key) => {
                    console.error(`  ${key}:`, error.response.data.errors[key]);
                });
            }

            this.setError(error.response?.data?.message || error.message);
            this.setLoading(false);
            this.setPointsGeojson(null);
            throw error;
        }
    },

    // Get points with category filtering (for smoking data, etc.)
    async GET_POINTS_BY_CATEGORY({ zoom, bbox, category = null, placeId = null }) {
        console.log('GET_POINTS_BY_CATEGORY called with:', { zoom, bbox, category, placeId });

        try {
            this.setLoading(true);
            this.setError(null);

            // Use bracket notation for bbox parameters
            const params = {
                zoom: Math.round(zoom),
                'bbox[left]': bbox.left,
                'bbox[bottom]': bbox.bottom,
                'bbox[right]': bbox.right,
                'bbox[top]': bbox.top,
            };

            // Handle category filtering - backend expects categories array
            if (category) {
                // Map simple category names to the backend's expected format
                const categoryMappings = {
                    smoking: 'smoking',
                    alcohol: 'alcohol',
                    food: 'food',
                    coffee: 'coffee',
                    brands: 'brands',
                };

                const mappedCategory = categoryMappings[category] || category;
                params['categories[0]'] = mappedCategory;
            }

            console.log('Sending params with category:', params);

            const response = await axios.get('/api/points', {
                params: params,
            });

            console.log('GET_POINTS_BY_CATEGORY response:', response.data);
            console.log('Response status:', response.status);
            console.log('Features count:', response.data?.features?.length || 0);

            // Store the response data with category+placeId key for better isolation
            const storageKey = placeId ? `${category || 'all'}:${placeId}` : category || 'all';
            this.categoryData[storageKey] = response.data;
            this.setLoading(false);

            return response.data;
        } catch (error) {
            console.error(`Failed to load ${category} points:`, error);
            console.error('Error response:', error.response?.data);
            console.error('Error status:', error.response?.status);

            // Log validation errors if present
            if (error.response?.data?.errors) {
                console.error('Validation errors:', error.response.data.errors);
                Object.keys(error.response.data.errors).forEach((key) => {
                    console.error(`  ${key}:`, error.response.data.errors[key]);
                });
            }

            this.setError(error.response?.data?.message || error.message);
            this.setLoading(false);
            throw error;
        }
    },

    // Set loading state (kept for compatibility but use setLoading instead)
    SET_LOADING(loading) {
        this.setLoading(loading);
    },

    // Clear error state
    CLEAR_ERROR() {
        this.setError(null);
    },

    // Clear all points data
    CLEAR_POINTS() {
        this.setPointsGeojson(null);
        this.categoryData = {};
        this.setError(null);
        this.setLoading(false);
    },
};
