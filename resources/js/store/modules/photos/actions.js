export const actions = {

    /**
     * Get unverified photos for tagging
     */
    async GET_PHOTOS_FOR_TAGGING (context, payload)
    {
        await axios.get('photos')
            .then(response => {
                console.log('photos', response);

                context.commit('photosForTagging', response.data);
            })
            .catch(error => {
                console.log('error.photos', error);
            });
    }
}
