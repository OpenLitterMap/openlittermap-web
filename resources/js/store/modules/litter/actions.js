import Vue from 'vue'
import i18n from "../../../i18n";

export const actions = {

    /**
     * Get filtered photos and add many tags at once
     */
    async ADD_MANY_TAGS_TO_MANY_PHOTOS (context)
    {
        const title = 'Success!';
        const body = 'Your tags were applied to the images';

        await axios.post('/user/profile/photos/tags/create', {
            selectAll: context.rootState.photos.selectAll,
            inclIds: context.rootState.photos.inclIds,
            exclIds: context.rootState.photos.exclIds,
            filters: context.rootState.photos.filters,
            tags: context.state.tags[0]
        })
        .then(response => {
            console.log('add_many_tags_to_many_photos', response);

            // success notification
            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.commit('hideModal');
            }
        })
        .catch(error => {
            console.error('add_many_tags_to_many_photos', error);
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
            tags: context.state.tags[photoId],
            presence: context.state.presence,
            photo_id: photoId
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
