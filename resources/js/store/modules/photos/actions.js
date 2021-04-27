export const actions = {

    /**
     * Get unverified photos for tagging
     */
    async GET_PHOTOS_FOR_TAGGING (context, payload)
    {
        await axios.get('photos')
            .then(response => {
                console.log('get_photos_for_tagging', response);

                context.commit('clearTags');
                context.commit('photosForTagging', response.data);
            })
            .catch(error => {
                console.error('get_photos_for_tagging', error);
            });
    },

    /**
     * Return paginated filtered array of the users photos,
     */
    async GET_USERS_FILTERED_PHOTOS (context)
    {
        await axios.get('/user/profile/photos/filter', {
            params: {
                filters: context.state.filters
            }
        })
        .then(response => {
            console.log('get_users_filtered_photos', response);

            // update count
            context.commit('myProfilePhotos', response.data.paginate);
        })
        .catch(error => {
            console.error('get_users_filtered_photos', error);
        });
    },

    /**
     * Get non filtered paginated array of the users photos
     */
    async LOAD_MY_PHOTOS (context)
    {
        await axios.get('/user/profile/photos/index')
            .then(response => {
                console.log('load_my_photos', response);

                context.commit('myProfilePhotos', response.data);
            })
            .catch(error => {
                console.error('load_my_photos', error);
            });
    },

    /**
     * When an image is submitted, we want to get the next image for tagging
     *
     * Since there is 1 less pagination, we want to reload the same page we are currently on
     */
    async LOAD_NEXT_IMAGE (context)
    {
        await axios.get('/photos?page=' + context.state.paginate.current_page)
            .then(response => {
                console.log('load_next_image', response);

                context.commit('clearTags');
                context.commit('photosForTagging', response.data);
            })
            .catch(error => {
                console.error('load_next_image', error);
            });
    },

    /**
     * Load the next image from pagination
     */
    async NEXT_IMAGE (context)
    {
        await axios.get(context.state.paginate.next_page_url)
            .then(response => {
                console.log('next_image', response);

                context.commit('clearTags');
                context.commit('photosForTagging', response.data);
            })
            .catch(error => {
                console.error('next_image', error);
            });
    },

    /**
     * Load the next previous from pagination
     */
    async PREVIOUS_IMAGE (context)
    {
        await axios.get(context.state.paginate.prev_page_url)
            .then(response => {
                console.log('previous_image', response);

                context.commit('clearTags');
                context.commit('photosForTagging', response.data);
            })
            .catch(error => {
                console.error('previous_image', error);
            });
    },

    /**
     * Load the previous page of photos
     */
    async PREVIOUS_PHOTOS_PAGE (context)
    {
        await axios.get(context.state.paginate.prev_page_url)
            .then(response => {
                console.log('previous_photos_url', response);

                // update photos
                context.commit('paginatedPhotos', response.data);
            })
            .catch(error => {
                console.error('previous_photos_url', error);
            });
    },

    /**
     * Load the next page of photos
     */
    async NEXT_PHOTOS_PAGE (context)
    {
        await axios.get(context.state.paginate.next_page_url)
            .then(response => {
                console.log('next_photos_page', response);

                // update photos
                context.commit('paginatedPhotos', response.data);
            })
            .catch(error => {
                console.error('next_photos_page', error);
            });
    },

    /**
     * Select page from pagination
     *
     * @payload = pageSelected
     */
    async SELECT_IMAGE (context, payload)
    {
        await axios.get(`/photos?page=${payload}`)
            .then(response => {
                console.log('select_image', response);

                context.commit('clearTags');
                context.commit('photosForTagging', response.data);
            })
            .catch(error => {
                console.error('select_image', error);
            });
    },
}
