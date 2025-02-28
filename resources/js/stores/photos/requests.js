export const requests = {
    async GET_USERS_UNTAGGED_PHOTOS() {
        await axios
            .get('/api/v3/user/photos/untagged')
            .then((response) => {
                console.log('get_users_untagged_photos', response);

                this.paginated = response.data.photos;
                this.remaining = response.data.remaining;
                this.total = response.data.photos.total;
                this.previousCustomTags = response.data.custom_tags;
            })
            .catch((error) => {
                console.error('get_photos_for_tagging', error);
            });
    },

    async UPLOAD_TAGS({ photoId, tags }) {
        await axios
            .post('/api/v3/tags', {
                photo_id: photoId,
                tags: tags,
            })
            .then((response) => {
                console.log('upload_tags', response);
            })
            .catch((error) => {
                console.error('upload_tags', error);
            })
            .finally(() => {
                return { test: true };
            });
    },
};
