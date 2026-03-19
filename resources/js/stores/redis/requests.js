export const requests = {
    /**
     * Fetch main Redis data overview
     */
    async FETCH_REDIS_DATA() {
        this.loading = true;
        this.error = null;

        try {
            const response = await axios.get('/api/redis-data', {
                params: {
                    limit: this.userLimit,
                },
            });

            console.log('fetch_redis_data', response);

            this.initializeData(response.data);

            return response.data;
        } catch (error) {
            console.error('error.fetch_redis_data', error);
            this.error = error.response?.data?.message || 'Failed to fetch Redis data';
            throw error;
        } finally {
            this.loading = false;
        }
    },

    /**
     * Fetch specific user's Redis data
     */
    async FETCH_USER_REDIS_DATA(userId) {
        this.loading = true;
        this.error = null;

        try {
            const response = await axios.get(`/api/redis-data/${userId}`);

            console.log('fetch_user_redis_data', response);

            this.setSelectedUser(response.data);

            return response.data;
        } catch (error) {
            console.error('error.fetch_user_redis_data', error);
            this.error = error.response?.data?.message || 'Failed to fetch user data';
            throw error;
        } finally {
            this.loading = false;
        }
    },

    /**
     * Fetch Redis performance metrics
     */
    async FETCH_PERFORMANCE_DATA() {
        this.loading = true;
        this.error = null;

        try {
            const response = await axios.get('/api/redis-data/performance');

            console.log('fetch_performance_data', response);

            this.setPerformanceData(response.data);

            return response.data;
        } catch (error) {
            console.error('error.fetch_performance_data', error);
            this.error = error.response?.data?.message || 'Failed to fetch performance data';
            throw error;
        } finally {
            this.loading = false;
        }
    },

    /**
     * Fetch Redis key analysis
     */
    async FETCH_KEY_ANALYSIS() {
        this.loading = true;
        this.error = null;

        try {
            const response = await axios.get('/api/redis-data/key-analysis');

            console.log('fetch_key_analysis', response);

            this.setKeyAnalysis(response.data);

            return response.data;
        } catch (error) {
            console.error('error.fetch_key_analysis', error);
            this.error = error.response?.data?.message || 'Failed to fetch key analysis';
            throw error;
        } finally {
            this.loading = false;
        }
    },

    /**
     * Clear all Redis data (dangerous operation)
     */
    async CLEAR_REDIS_DATA(confirmation) {
        if (confirmation !== 'DELETE_ALL_REDIS_DATA') {
            throw new Error('Invalid confirmation');
        }

        this.loading = true;
        this.error = null;

        try {
            const response = await axios.delete('/api/redis-data', {
                data: {
                    confirm: confirmation,
                },
            });

            console.log('clear_redis_data', response);

            // Reset store after clearing data
            this.resetStore();

            return response.data;
        } catch (error) {
            console.error('error.clear_redis_data', error);
            this.error = error.response?.data?.message || 'Failed to clear Redis data';
            throw error;
        } finally {
            this.loading = false;
        }
    },

    /**
     * Export Redis data as JSON
     */
    async EXPORT_REDIS_DATA(format = 'json') {
        try {
            const response = await axios.get('/api/redis-data/export', {
                params: { format },
                responseType: 'blob',
            });

            // Create download link
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `redis-data-${Date.now()}.${format}`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            return true;
        } catch (error) {
            console.error('error.export_redis_data', error);
            this.error = error.response?.data?.message || 'Failed to export data';
            throw error;
        }
    },

    /**
     * Search users by name or email
     */
    async SEARCH_USERS(query) {
        if (!query || query.length < 2) {
            return [];
        }

        try {
            const response = await axios.get('/api/redis-data/search/users', {
                params: { q: query },
            });

            console.log('search_users', response);

            return response.data;
        } catch (error) {
            console.error('error.search_users', error);
            throw error;
        }
    },

    /**
     * Get Redis data for specific date range
     */
    async FETCH_REDIS_DATA_BY_DATE(startDate, endDate) {
        this.loading = true;
        this.error = null;

        try {
            const response = await axios.get('/api/redis-data/date-range', {
                params: {
                    start: startDate,
                    end: endDate,
                },
            });

            console.log('fetch_redis_data_by_date', response);

            return response.data;
        } catch (error) {
            console.error('error.fetch_redis_data_by_date', error);
            this.error = error.response?.data?.message || 'Failed to fetch data for date range';
            throw error;
        } finally {
            this.loading = false;
        }
    },

    /**
     * Refresh specific metric type
     */
    async REFRESH_METRIC(metricType) {
        try {
            const response = await axios.get(`/api/redis-data/metric/${metricType}`);

            console.log('refresh_metric', response);

            // Update only the specific metric in state
            if (metricType in this.global) {
                this.global[metricType] = response.data;
            }

            return response.data;
        } catch (error) {
            console.error('error.refresh_metric', error);
            throw error;
        }
    },

    /**
     * Get top items for a specific dimension
     */
    async FETCH_TOP_ITEMS(dimension, limit = 10) {
        try {
            const response = await axios.get(`/api/redis-data/top/${dimension}`, {
                params: { limit },
            });

            console.log('fetch_top_items', response);

            return response.data;
        } catch (error) {
            console.error('error.fetch_top_items', error);
            throw error;
        }
    },
};
