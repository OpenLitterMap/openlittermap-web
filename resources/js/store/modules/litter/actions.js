import Vue from 'vue'
import i18n from "../../../i18n";

export const actions = {

    /**
     * The user has added tags to an image and now wants to add them to the database
     */
    async ADD_TAGS_TO_IMAGE (context, payload)
    {
        let title = i18n.t('notifications.success');
        let body  = i18n.t('notifications.tags-added');

        await axios.post('add-tags', {
            tags: context.state.tags,
            presence: context.state.presence,
            photo_id: context.rootState.photos.paginate.data[0].id
        })
        .then(response => {
            console.log('add_tags_to_image', response);

            /* improve this */
            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });

            // todo - update XP bar

            context.commit('clearTags');
            context.dispatch('LOAD_NEXT_IMAGE');
        })
        .catch(error => console.log(error));
    }
}
