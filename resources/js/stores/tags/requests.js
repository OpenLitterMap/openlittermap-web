export const requests = {
    /**
     * Get all pre-defined tags for tagging
     */
    async GET_TAGS() {
        await axios
            .get('/api/tags')
            .then((response) => {
                console.log('GET_TAGS', response);

                this.initTags(response.data.tags);
            })
            .catch((error) => {
                console.error('GET_TAGS', error);
            });
    },

    async GET_ALL_TAGS() {
        await axios
            .get('/api/tags/all')
            .then((response) => {
                console.log('GET_ALL_TAGS', response);

                this.initAllTags(response.data);
            })
            .catch((error) => {
                console.error('GET_ALL_TAGS', error);
            });
    },
};
