import { init } from './init'

export const mutations = {

    /**
     * Update filters[key] = value
     */
    filter_photos (state, payload)
    {
        state.filters[payload.key] = payload.v;
    },

    /**
     * The min and max date to filter the users photos by
     */
    filter_photos_calendar (state, payload)
    {
        state.filters.dateRange.start = payload.min;
        state.filters.dateRange.end = payload.max;
    },

    /**
     * Previous or Next photos have been received
     */
    paginatedPhotos (state, payload)
    {
        state.paginate = payload;
    },

    /**
     * Get unverified photos for tagging
     */
    photosForTagging (state, payload)
    {
        state.paginate = payload.photos;
        state.remaining = payload.remaining;
        state.total = payload.total;
    },

    /**
     * On User Dashboard, we can load a modal to show paginated array of photos
     */
    myProfilePhotos (state, payload)
    {
        state.paginate = payload;
    },

    /**
     * Reset state, when the user logs out
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Toggle selected value of all photos
     */
    selectAllPhotos (state, payload)
    {
        let photos = [...state.paginate.data];

        photos.forEach(photo => {
            photo.selected = payload;
        });

        state.paginate.data = photos;
    },

    /**
     * Change the selected value of a photo
     *
     * MyPhotos.vue
     */
    togglePhotoSelected (state, payload)
    {
        let photos = [...state.paginate.data];

        let photo = photos.find(photo => photo.id === payload);

        photo.selected = ! photo.selected;

        state.paginate.data = photos;
    }
}
