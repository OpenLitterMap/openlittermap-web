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
        if (state.inclIds.length > 0 || state.exclIds.length > 0 || state.selectAll)
        {
            payload.data = payload.data.map(photo => {

                if (state.selectAll) photo.selected = true;
                if (state.inclIds.includes(photo.id)) photo.selected = true;
                if (state.exclIds.includes(photo.id)) photo.selected = false;

                return photo;
            });
        }

        state.paginate = payload;
    },

    /**
     * Number of photos available (MyPhotos)
     */
    photosCount (state, payload)
    {
        state.total = payload;
    },

    /**
     * Get unverified photos for tagging
     */
    photosForTagging (state, payload)
    {
        state.paginate = payload.photos;
        state.remaining = payload.remaining;
        state.total = payload.total;
        state.previousCustomTags = payload.custom_tags;
    },

    /**
     * On User Dashboard, we can load a modal to show paginated array of photos
     */
    myProfilePhotos (state, payload)
    {
        state.paginate = payload;
    },

    /**
     * Store the user's previously added custom tags
     */
    setPreviousCustomTags (state, payload)
    {
        state.previousCustomTags = payload;
    },

    /**
     * Called when the user logs out
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Called when MyPhotos is loaded
     */
    resetPhotoState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Toggle selected value of all photos
     *
     * Update selectedCount
     */
    selectAllPhotos (state)
    {
        state.selectAll = ! state.selectAll;

        let photos = [...state.paginate.data];

        photos.forEach(photo => {
            photo.selected = state.selectAll;
        });

        state.paginate.data = photos;

        state.selectedCount = state.selectAll ? state.total : 0;
    },

    addTagToPhoto (state, payload)
    {
        const photoIndex = state.paginate.data.findIndex(photo => photo.id === payload.photoId)
        let photo = state.paginate.data[photoIndex];
        let tags = Object.assign({}, photo.tags ?? {});

        photo.tags = {
            ...tags,
            [payload.category]: {
                ...(tags[payload.photoId] ? tags[payload.photoId][payload.category] : {}),
                [payload.tag]: payload.quantity
            }
        };

        state.paginate.data.splice(photoIndex, 1, photo);
    },

    addCustomTagToPhoto (state, payload)
    {
        const photoIndex = state.paginate.data.findIndex(photo => photo.id === payload.photoId)
        let photo = state.paginate.data[photoIndex];
        let tags = photo.custom_tags ?? [];

        // Case-insensitive check for existing tags
        if (tags.find(tag => tag.toLowerCase() === payload.customTag.toLowerCase()) !== undefined)
        {
            return;
        }

        if (tags.length >= 3)
        {
            return;
        }

        tags.unshift(payload.customTag);

        photo.custom_tags = tags;

        state.paginate.data.splice(photoIndex, 1, photo);
    },

    setChosenPhoto (state, photoId)
    {
        state.chosenPhotoId = photoId;
    },

    /**
     * Change the selected value of a photo
     *
     * MyPhotos.vue
     */
    togglePhotoSelected (state, payload)
    {
        let photos = [...state.paginate.data];
        let inclIds = [...state.inclIds];
        let exclIds = [...state.exclIds];

        let photo = photos.find(photo => photo.id === payload);

        photo.selected = ! photo.selected;

        if (photo.selected)
        {
            state.selectedCount++;

            if (state.selectAll)
            {
                exclIds = exclIds.filter(id => id !== photo.id);
            }
            else
            {
                inclIds.push(photo.id);
            }
        }
        else
        {
            state.selectedCount--;

            if (state.selectAll)
            {
                exclIds.push(photo.id);
            }
            else
            {
                inclIds = inclIds.filter(id => id !== photo.id);
            }
        }

        (state.selectAll)
            ? state.exclIds.push(photo.id)
            : state.inclIds.push(photo.id);

        state.inclIds = inclIds;
        state.exclIds = exclIds;
        state.paginate.data = photos;
    }
}
