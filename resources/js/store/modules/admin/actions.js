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
        await axios.post('/admin/incorrect', {
            photoId: this.photo.id
        })
        .then(response => {
            console.log('admin_reset_tags', response);

            context.dispatch('GET_NEXT_ADMIN_PHOTO');
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
     * Verify the previous + new tags
     */
    async ADMIN_VERIFY_KEEP (context)
    {
        await axios.post('/admin/contentsupdatekeep', {
            photoId: context.state.photo.id,
            // categories: this.$store.state.litter.categories // todo
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
        // clear previous input on litter.js
        context.commit('resetLitter');

        await axios.get('/admin/get-image')
            .then(resp => {

                console.log('get_next_admin_photo', resp);

                // init photo data (admin.js)
                context.commit('initAdminPhoto', resp.data.photo);

                // init litter data for verification (litter.js)
                if (resp.data.photoData) context.commit('initAdminItems', resp.data.photoData);

                context.commit('initAdminMetadata', {
                    not_processed: resp.data.photosNotProcessed,
                    awaiting_verification: resp.data.photosAwaitingVerification
                });
            })
            .catch(err => {
                console.error(err);
            });
    },

    /**
     * Get the next image to add bounding box
     */
    async GET_NEXT_BBOX (context)
    {
        await axios.get('next-bb-image')
        .then(response => {
            console.log('next_bb_img', response);

            context.commit('adminImage', {
                id: response.data.id,
                filename: response.data.filename
            });

            context.commit('adminLoading', false);
        })
        .catch(error => {
            console.log('error.next_bb_img', error);
        });
    }

};
