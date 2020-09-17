export const actions = {

    /**
     * Get the next photo to verify on admin account
     */
    async GET_NEXT_ADMIN_PHOTO (context)
    {
        // clear previous input on litter.js
        context.commit('resetLitter');

        console.log('get_next_admin_photo...');

        await axios.get('/admin/get-image')
            .then(resp => {

                console.log('get_next_admin_photo', resp);

                this.photo = resp.data.photo;

                // init photo data (admin.js)
                context.commit('initAdminPhoto', resp.data.photo);

                // init litter data for verification (litter.js)
                if (resp.data.photoData) context.commit('initAdminItems', JSON.parse(resp.data.photoData));

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
