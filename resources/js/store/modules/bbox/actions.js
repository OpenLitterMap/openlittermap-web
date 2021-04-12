import Vue from 'vue';
import routes from '../../../routes';

export const actions = {

    /**
     * Add annotations to an image
     */
    async ADD_BOXES_TO_IMAGE (context)
    {
        await axios.post('/bbox/create', {
            photo_id: context.rootState.admin.id,
            boxes: context.state.boxes
        })
        .then(response => {
            console.log('add_boxes_to_image', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title: 'Success!',
                    body: 'Thank you for helping us clean the planet!',
                    position: 'top-right'
                });

                context.dispatch('GET_NEXT_BBOX');
            }
        })
        .catch(error => {
            console.error('add_boxes_to_image', error);
        });
    },

    /**
     * Mark this image as unable to use for bbox
     *
     * Load the next image
     *
     * @payload bool (isVerifying)
     */
    async BBOX_SKIP_IMAGE (context, payload)
    {
        await axios.post('/bbox/skip', {
            photo_id: context.rootState.admin.id
        })
        .then(response => {
            console.log('bbox_skip_image', response);

            Vue.$vToastify.success({
                title: 'Skipping',
                body: 'This image will not be used for AI',
                position: 'top-right'
            });

            // load next image
            (payload)
                ? context.dispatch('GET_NEXT_BOXES_TO_VERIFY')
                : context.dispatch('GET_NEXT_BBOX');
        })
        .catch(error => {
            console.error('bbox_skip_image', error);
        });
    },

    /**
     * Update the tags for a bounding box image
     */
    async BBOX_UPDATE_TAGS (context)
    {
        await axios.post('/bbox/tags/update', {
            photoId: context.rootState.admin.id,
            tags: context.rootState.litter.tags
        })
        .then(response => {
            console.log('bbox_update_tags', response);

            Vue.$vToastify.success({
                title: 'Updated',
                body: 'The tags for this image have been updated',
                position: 'top-right'
            });

            const boxes = [...context.state.boxes];

            // Update boxes based on tags in the image
            context.commit('initBboxTags', context.rootState.litter.tags);

            context.commit('updateBoxPositions', boxes);
        })
        .catch(error => {
            console.error('bbox_update_tags', error);
        });
    },

    /**
     * Non-admins cannot update tags.
     *
     * Normal users can mark a box with incorrect tags that an Admin must inspect.
     */
    async BBOX_WRONG_TAGS (context)
    {
        await axios.post('/bbox/tags/wrong', {
            photoId: context.rootState.admin.id,
        })
        .then(response => {
            console.log('bbox_wrong_tags', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title: 'Thanks for helping!',
                    body: 'An admin will update these tags',
                    position: 'top-right'
                });
            }
        })
        .catch(error => {
            console.error('bbox_wrong_tags', error);
        });
    },

    /**
     * Get the next image to add bounding box
     */
    async GET_NEXT_BBOX (context)
    {
        await axios.get('/bbox/index')
            .then(response => {
                console.log('next_bb_img', response);

                context.commit('adminImage', {
                    id: response.data.photo.id,
                    filename: response.data.photo.five_hundred_square_filepath // filename
                });

                // litter.js
                context.commit('initAdminItems', response.data.photo);

                // bbox.js
                context.commit('initBboxTags', response.data.photo);

                // box counts
                context.commit('bboxCount', {
                    usersBoxCount: response.data.usersBoxCount,
                    totalBoxCount: response.data.totalBoxCount
                })

                context.commit('adminLoading', false);
            })
            .catch(error => {
                console.log('error.next_bb_img', error);
            });
    },

    /**
     * Get the next image that has boxes to be verified
     */
    async GET_NEXT_BOXES_TO_VERIFY (context)
    {
        await axios.get('/bbox/verify/index')
            .then(response => {
                console.log('verify_next_box', response);

                if (response.data.photo)
                {
                    context.commit('adminImage', {
                        id: response.data.photo.id,
                        filename: response.data.photo.five_hundred_square_filepath // filename
                    });

                    // litter.js
                    context.commit('initAdminItems', response.data.photo);

                    // bbox.js
                    context.commit('initBoxesToVerify', response.data.photo.boxes);

                    // bbox.js
                    // context.commit('bboxCount', {
                    //     usersBoxCount: response.data.usersBoxCount,
                    //     totalBoxCount: response.data.totalBoxCount
                    // })

                    context.commit('adminLoading', false);
                }
                else
                {
                    // todo
                    // routes.push({ path: '/bbox' });
                }
            })
            .catch(error => {
                console.log('error.verify_next_box', error);
            });
    },

    /**
     * Verify the boxes are placed correctly and match the tags
     */
    async VERIFY_BOXES (context)
    {
        await axios.post('/bbox/verify/update', {
            photo_id: context.rootState.admin.id,
            hasChanged: context.state.hasChanged,
            boxes: context.state.boxes
        })
        .then(response => {
            console.log('verify_boxes', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title: 'Verified',
                    body: 'Stage 4 level achieved!',
                    position: 'top-right'
                });

                context.dispatch('GET_NEXT_BOXES_TO_VERIFY');
            }
        })
        .catch(error => {
            console.error('verify_boxes', error);
        });
    }
}
