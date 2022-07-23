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

        await axios.post('/admin/reset-tags', {
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
     *
     * Increments user_verification_count on Redis
     *
     * If user_verification_count reaches >= 100:
     * - A Littercoin is mined. Boss level 1 is completed.
     * - The user becomes Trusted.
     * - All remaining images are verified.
     * - Email sent to the user encouraging them to continue.
     *
     * Updates photo as verified
     * Updates locations, charts, time-series, teams, etc.
     *
     * Returns user_verification_count and number of images verified.
     */
    async ADMIN_VERIFY_CORRECT (context)
    {
        const title = i18n.t('notifications.success');
        const body = "Verified";

        await axios.post('/admin/verify-correct', {
            photoId: context.state.photo.id
        })
        .then(response => {
            console.log('admin_verify_correct', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                });

                if (response.data.userVerificationCount >= 100)
                {
                    setTimeout(() => {
                        Vue.$vToastify.success({
                            title: "User has been verified",
                            body: "Email sent and remaining photos verified",
                        });
                    }, 1000);
                }
            }

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
        })
        .catch(error => {
            console.error('admin_verify_correct', error);
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
        })
        .then(response => {
            console.log('admin_verify_delete', response);

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
        })
        .catch(error => {
            console.log('admin_verify_delete', error);
        });
    },

    /**
     * Verify the image, and update with new tags
     */
    async ADMIN_UPDATE_WITH_NEW_TAGS (context)
    {
        const photoId = context.state.photo.id;

        await axios.post('/admin/update-tags', {
            photoId: photoId,
            tags: context.rootState.litter.tags[photoId],
            custom_tags: context.rootState.litter.customTags[photoId]
        })
        .then(response => {
            console.log('admin_update_with_new_tags', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title: "Tags updated",
                    body: "Thank you for helping to verify OpenLitterMap data!",
                });
            }

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
        })
        .catch(error => {
            console.log('admin_update_with_new_tags', error);
        });
    },

    /**
     * Get the next photo to verify on admin account
     */
    async GET_NEXT_ADMIN_PHOTO (context)
    {
        // clear previous input on litter.js
        context.commit('resetLitter');
        context.commit('clearTags');

        await axios.get('/admin/get-image', {
            params: {
                country_id: context.state.filterByCountry,
                skip: context.state.skippedPhotos
            }
        })
        .then(response => {
            console.log('get_next_admin_photo', response);

            console.log(response.data.photo.user.user_verification_count);

            window.scroll({
                top: 0,
                left: 0,
                behavior: 'smooth'
            });

            // init photo data (admin.js)
            context.commit('initAdminPhoto', response.data.photo);

            // init litter data for verification (litter.js)
            if (response.data.photo?.verification > 0)
            {
                context.commit('initAdminItems', response.data.photo);
                context.commit('initAdminCustomTags', response.data.photo);
            }

            context.commit('initAdminMetadata', {
                not_processed: response.data.photosNotProcessed,
                awaiting_verification: response.data.photosAwaitingVerification
            });

            context.dispatch('ADMIN_GET_COUNTRIES_WITH_PHOTOS');
        })
        .catch(err => {
            console.error(err);
        });
    },

    /**
     * Get list of countries that contain photos for verification
     */
    async ADMIN_GET_COUNTRIES_WITH_PHOTOS (context)
    {
        await axios.get('/admin/get-countries-with-photos')
            .then(response => {
                console.log('admin_get_countries_with_photos', response);

                context.commit('setCountriesWithPhotos', response.data);
            })
            .catch(err => {
                console.error(err);
            });
    }
};
