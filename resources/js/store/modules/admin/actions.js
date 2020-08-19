export const actions = {

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