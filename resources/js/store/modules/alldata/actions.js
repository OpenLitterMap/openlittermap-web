export const actions = {

    /**
     * Todo - move vuex state from user to a shared module
     */
    async GET_ALL_PHOTOS_PAGINATED (context, payload)
    {
        await axios.get('/history/paginated', {
            params: {
                filterTag: context.rootState.user.filterPhotos.filterTag,
                filterCustomTag: context.rootState.user.filterPhotos.filterCustomTag,
                filterDateFrom: context.rootState.user.filterPhotos.filterDateFrom,
                filterDateTo: context.rootState.user.filterPhotos.filterDateTo,
                filterCountry: context.rootState.user.filterPhotos.filterCountry,
                paginationAmount: context.rootState.user.filterPhotos.paginationAmount,
                loadPage: payload
            }
        })
        .then(response => {
            console.log('get_all_photos_paginated', response);

            if (response.data.success)
            {
                context.commit('setPaginatedHistoricalPhotos', response.data.photos);
            }
        })
        .catch(error => {
            console.error('get_all_photos_paginated', error);
        });
    }

};
