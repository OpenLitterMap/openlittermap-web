export const actions = {

    /**
     * Add annotations to an image
     */
    async ADD_BOXES_TO_IMAGE (context)
    {
        await axios.post('/admin/bbox/create', {
            photo_id: context.rootState.admin.id,
            boxes: context.state.boxes
        })
        .then(response => {
            console.log('add_boxes_to_image', response);


        })
        .catch(error => {
            console.error('add_boxes_to_image', error);
        });
    },

    /**
     * Mark this image as unable to use for bbox
     *
     * Load the next image
     */
    async BBOX_SKIP_IMAGE (context)
    {
        await axios.post('/admin/bbox/skip', {
            photo_id: context.rootState.admin.id
        })
        .then(response => {
            console.log('bbox_skip_image', response);

            // notification

            // load next image
        })
        .catch(error => {
            console.error('bbox_skip_image', error);
        });
    },

    /**
     * Get the next image to add bounding box
     */
    async GET_NEXT_BBOX (context)
    {
        await axios.get('/admin/bbox/index')
            .then(response => {
                console.log('next_bb_img', response);

                context.commit('adminImage', {
                    id: response.data.id,
                    filename: response.data.filename
                });

                // litter.js
                context.commit('initAdminItems', response.data);

                // bbox.js
                context.commit('initBboxTags', response.data);

                context.commit('adminLoading', false);
            })
            .catch(error => {
                console.log('error.next_bb_img', error);
            });
    }
}
