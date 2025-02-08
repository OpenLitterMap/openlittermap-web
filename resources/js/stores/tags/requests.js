export const requests = {
    /**
     * Get all pre-defined tags for tagging
     */
    async GET_TAGS() {
        await axios
            .get('/api/tags')
            .then((response) => {
                console.log('GET_TAGS', response);

                this.initTags(response.data);
            })
            .catch((error) => {
                console.error('GET_TAGS', error);
            });
    },
};
