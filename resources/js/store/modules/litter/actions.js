export const actions = {

    /**
     * The user has added tags to an image and now wants to add them to the database
     */
    async ADD_TAGS_TO_IMAGE (context, payload)
    {
        await axios.post('add-tags', {
            tags: context.state.tags,
            presence: context.state.presence,
            photo_id: context.rootState.photos.photos.data[0].id
        })
        .then(response => {
            console.log(response);

            // display success notification

            // todo - update XP bar

            // load next image

            // alert('Excellent work! This image has been submitted for verification. THANK YOU FOR HELPING TO KEEP OUR PLANET CLEAN! WELL DONE.');
            // window.location.href = window.location.href;
        })
        .catch(error => console.log(error));
    }
}
