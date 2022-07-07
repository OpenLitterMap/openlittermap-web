import Vue from 'vue'
import i18n from "../../../i18n";

export const actions = {

    /**
     * Get filtered photos and add many tags at once
     */
    async BULK_TAG_PHOTOS (context)
    {
        const title = 'Success!';
        const body = 'Your tags were applied to the images';

        let photos = context.rootState.photos.paginate.data
            .filter(photo => {
                const hasTags = photo.tags && Object.keys(photo.tags).length;
                const hasCustomTags = photo.custom_tags?.length;
                return hasTags || hasCustomTags;
            })
            .reduce((map, photo) => {
                map[photo.id] = {
                    tags: photo.tags ?? {},
                    custom_tags: photo.custom_tags ?? [],
                    picked_up: !! photo.picked_up
                };
                return map;
            }, {});

        await axios.post('/user/profile/photos/tags/bulkTag', {photos})
            .then(response => {
                console.log('bulk_tag_photos', response);

                if (response.data.success) {
                    Vue.$vToastify.success({
                        title,
                        body,
                        position: 'top-right'
                    });
                }
            })
            .catch(error => {
                console.error('bulk_tag_photos', error);
            });
    },

    /**
     * The user has added tags to an image and now wants to add them to the database
     */
    async ADD_TAGS_TO_IMAGE (context, payload)
    {
        let title = i18n.t('notifications.success');
        let body  = i18n.t('notifications.tags-added');
        let photoId = context.rootState.photos.paginate.data[0].id;

        await axios.post('add-tags', {
            photo_id: photoId,
            tags: context.state.tags[photoId],
            custom_tags: context.state.customTags[photoId],
            picked_up: context.state.pickedUp,
        })
        .then(response => {
            /* improve this */
            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });

            // todo - update XP bar

            context.commit('clearTags', photoId);
            context.dispatch('LOAD_NEXT_IMAGE');
        })
        .catch(error => console.log(error));
    }
}
