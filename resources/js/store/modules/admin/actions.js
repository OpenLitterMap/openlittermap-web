import Vue from 'vue';
import i18n from '../../../i18n';

export const actions = {

    /**
     * Delete an image and its records
     */
    async ADMIN_DELETE_IMAGE (context)
    {
        await axios.post('/admin/destroy', {
            photoId: context.state.photo.id
        })
        .then(response => {
            console.log('admin_delete_image', response);

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
        })
        .catch(error => {
            console.log(error);
        });
    },

    /**
     * Reset the tags + verification on an image
     */
    async ADMIN_RESET_TAGS (context)
    {
        const title = i18n.t('notifications.success');
        const body = 'Image has been reset';

        await axios.post('/admin/incorrect', {
            photoId: context.state.photo.id
        })
        .then(response => {
            console.log('admin_reset_tags', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.dispatch('GET_NEXT_ADMIN_PHOTO');
            }

        }).catch(error => {
            console.log(error);
        });
    },

    /**
     * Verify the image as correct (stage 2)
     */
    async ADMIN_VERIFY_CORRECT (context)
    {
        await axios.post('/admin/verifykeepimage', {
            photoId: context.state.photo.id
        })
        .then(resp => {
            console.log('admin_verifiy_correct', resp);

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
        })
        .catch(err => {
            console.error(err);
        });
    },

    /**
     * Verify tags and delete the image
     */
    async ADMIN_VERIFY_DELETE (context)
    {
        await axios.post('/admin/contentsupdatedelete', {
            photoId: context.state.photo.id,
            // categories: categories todo
        }).then(response => {
            console.log('admin_verify_delete', response);

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
        }).catch(error => {
            console.log(error);
        });
    },

    /**
     * Verify the image, and update with new tags
     */
    async ADMIN_UPDATE_WITH_NEW_TAGS (context)
    {
        let photoId = context.state.photo.id;

        await axios.post('/admin/update-tags', {
            photoId: photoId,
            tags: context.rootState.litter.tags[photoId]
        })
        .then(response => {
            console.log('admin_verify_keep', response);

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
        })
        .catch(error => {
            console.log(error);
        });
    },

    /**
     * Get the next photo to verify on admin account
     */
    async GET_NEXT_ADMIN_PHOTO (context)
    {
        // admin loading = true

        // clear previous input on litter.js
        context.commit('resetLitter');
        context.commit('clearTags');

        await axios.get('/admin/get-image')
            .then(resp => {
                console.log('get_next_admin_photo', resp);

                // init photo data (admin.js)
                context.commit('initAdminPhoto', resp.data.photo);

                // init litter data for verification (litter.js)
                if (resp.data.photo?.verification > 0) context.commit('initAdminItems', resp.data.photo);

                context.commit('initAdminMetadata', {
                    not_processed: resp.data.photosNotProcessed,
                    awaiting_verification: resp.data.photosAwaitingVerification
                });
            })
            .catch(err => {
                console.error(err);
            });

        // admin loading = false
    }

};
