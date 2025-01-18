export const requests = {
    /**
     * Get all pre-defined tags for tagging
     */
    async GET_TAGS() {
        await axios
            .get('/api/tags')
            .then((response) => {
                console.log('GET_TAGS', response);

                this.tags = response.data.tags;
                this.categories = response.data.tags.map((tag) => {
                    return {
                        id: tag.id,
                        category: tag.key,
                    };
                });
            })
            .catch((error) => {
                console.error('GET_TAGS', error);
            });
    },
};
