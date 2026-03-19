import axios from 'axios';

// points/requests.js
export const requests = {
    // Get points for a specific bounding box and zoom level
    async GET_POINTS({
        zoom,
        bbox,
        year = null,
        fromDate = null,
        toDate = null,
        username = null,
        tagFilter = null,
        page = 1,
        signal = null,
    }) {
        try {
            this.setLoading(true);
            this.setError(null);

            const params = {
                zoom: Math.round(zoom),
                'bbox[left]': bbox.left,
                'bbox[bottom]': bbox.bottom,
                'bbox[right]': bbox.right,
                'bbox[top]': bbox.top,
            };

            if (page > 1) params.page = page;
            if (year) params.year = year;
            if (fromDate) params.from = fromDate;
            if (toDate) params.to = toDate;
            if (username) params.username = username;

            // Apply tag filter
            if (tagFilter) {
                const filterMap = {
                    category: 'categories[0]',
                    object: 'litter_objects[0]',
                    brand: 'brands[0]',
                    material: 'materials[0]',
                    contributor: 'username',
                };
                const paramKey = filterMap[tagFilter.type];
                if (paramKey) {
                    params[paramKey] = tagFilter.id;
                }
            }

            const config = { params };
            if (signal) config.signal = signal;

            const response = await axios.get('/api/points', config);

            this.setPointsGeojson(response.data);
            this.setLoading(false);

            return response.data;
        } catch (error) {
            if (error.name === 'AbortError' || axios.isCancel(error)) {
                this.setLoading(false);
                return null;
            }

            console.error('Failed to load points:', error);
            this.setError(error.response?.data?.message || error.message);
            this.setLoading(false);
            this.setPointsGeojson(null);
            throw error;
        }
    },

    // Get points with category filtering (for smoking data, etc.)
    async GET_POINTS_BY_CATEGORY({ zoom, bbox, category = null, placeId = null }) {
        try {
            this.setLoading(true);
            this.setError(null);

            const params = {
                zoom: Math.round(zoom),
                'bbox[left]': bbox.left,
                'bbox[bottom]': bbox.bottom,
                'bbox[right]': bbox.right,
                'bbox[top]': bbox.top,
            };

            if (category) {
                params['categories[0]'] = category;
            }

            const response = await axios.get('/api/points', { params });

            const storageKey = placeId ? `${category || 'all'}:${placeId}` : category || 'all';
            this.categoryData[storageKey] = response.data;
            this.setLoading(false);

            return response.data;
        } catch (error) {
            console.error(`Failed to load ${category} points:`, error);
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
