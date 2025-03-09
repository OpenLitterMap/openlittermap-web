import { useToast } from 'vue-toastification';
import i18n from '../../i18n.js';
const toast = useToast();
const t = i18n.global.t;

export const requests = {
    async GET_USERS_UNTAGGED_PHOTOS(page = 1) {
        await axios
            .get(`/api/v3/user/photos/untagged?page=${page}`)
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
            .then(async (response) => {
                console.log('upload_tags', response);

                if (response.data.success) {
                    const title = t('notifications.tags.uploaded-success');

                    toast.success(title);

                    // Check if there is another photo
                    if (this.remaining > 0) {
                        // load next photo
                        await this.GET_USERS_UNTAGGED_PHOTOS(this.paginated.current_page + 1);
                    } else {
                        toast.info('No more photos left to tag');
                    }
                }
            })
            .catch((error) => {
                console.error('upload_tags', error);
            })
            .finally(() => {
                return { test: true };
            });
    },
};
