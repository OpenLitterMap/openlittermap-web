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
    }
}
