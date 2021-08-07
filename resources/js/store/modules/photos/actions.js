export const actions = {

    /**
     * MyPhotos.vue the user has confirmed they want to delete the selected photos
     */
    async DELETE_SELECTED_PHOTOS (context)
    {
        await axios.post('/user/profile/photos/delete', {
            selectAll: context.state.photos.selectAll,
            inclIds: context.state.photos.inclIds,
            exclIds: context.state.photos.exclIds,
            filters: context.state.photos.filters,
        })
        .then(response => {
            console.log('delete_selected_photos', response);

            // success notification

            // filter out selected photos
        })
        .catch(error => {
            console.error('delete_selected_photos', error);
        });
    },

    /**
     * Get unverified photos for tagging
     */
    async GET_PHOTOS_FOR_TAGGING (context)
    {
        await axios.get('photos')
            .then(response => {
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

            context.commit('myProfilePhotos', response.data.paginate);
            context.commit('photosCount', response.data.count);
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

                context.commit('myProfilePhotos', response.data.paginate);
                context.commit('photosCount', response.data.count);
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
        // When on the last page, next_page_url is null
        // So we need to use current_page - 1 as the correct value
        let currentPage = context.state.paginate.next_page_url || context.state.paginate.current_page === 1
                ? context.state.paginate.current_page
                : context.state.paginate.current_page - 1;

        await axios.get('/photos?page=' + currentPage)
            .then(response => {
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
                context.commit('paginatedPhotos', response.data.paginate);
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
                context.commit('paginatedPhotos', response.data.paginate);
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
                context.commit('photosForTagging', response.data);
            })
            .catch(error => {
                console.error('select_image', error);
            });
    },
}
