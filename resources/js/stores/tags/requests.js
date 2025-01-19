export const requests = {
    /**
     * Get all pre-defined tags for tagging
     */
    async GET_TAGS() {
        await axios
            .get('/api/tags')
            .then((response) => {
                console.log('GET_TAGS', response);

                // All Tags
                this.tags = response.data.tags;

                // Categories
                this.categories = response.data.tags.map((tag) => {
                    return {
                        id: tag.id,
                        key: tag.key,
                    };
                });

                this.setObjectsForCategories();
            })
            .catch((error) => {
                console.error('GET_TAGS', error);
            });
    },
};
