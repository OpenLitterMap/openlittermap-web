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

        let photos = context.rootState.photos.bulkPaginate.data
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
        const title = i18n.t('notifications.success');
        const body  = i18n.t('notifications.tags-added');
        const photoId = context.rootState.photos.paginate.data[0].id;

        await axios.post('add-tags', {
            photo_id: photoId,
            tags: context.state.tags[photoId],
            custom_tags: context.state.customTags[photoId],
            picked_up: context.state.pickedUp,
        })
        .then(response => {

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.commit('clearTags', photoId);

                if (!context.rootState.user.user.verification_required)
                {
                    context.commit('incrementUsersNextLittercoinScore');

                    if (context.rootState.user.user.littercoin_progress === 100)
                    {
                        context.commit('incrementLittercoinScore');

                        Vue.$vToastify.success({
                            title,
                            body: "You just earned a Littercoin!",
                            position: 'top-right'
                        });
                    }
                }
            }

            context.dispatch('LOAD_NEXT_IMAGE');
        })
        .catch(error => console.log(error));
    }
}
