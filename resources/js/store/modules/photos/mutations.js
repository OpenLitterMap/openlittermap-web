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

        state.bulkPaginate = payload;
    },

    /**
     * Number of photos available (BulkTag)
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
        state.bulkPaginate = payload;
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
     * Called when BulkTag is loaded
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

        let photos = [...state.bulkPaginate.data];

        photos.forEach(photo => {
            photo.selected = state.selectAll;
        });

        state.bulkPaginate.data = photos;

        state.selectedCount = state.selectAll ? state.total : 0;
    },

    /**
     * Sets the picked up value for a single photo
     */
    setPhotoPickedUp (state, payload)
    {
        const photoIndex = state.bulkPaginate.data.findIndex(photo => photo.id === payload.photoId)
        let photo = state.bulkPaginate.data[photoIndex];

        photo.picked_up = payload.picked_up;

        state.bulkPaginate.data.splice(photoIndex, 1, photo);
    },

    /**
     * Adds a tag to a single photo
     */
    addTagToPhoto (state, payload)
    {
        const photoIndex = state.bulkPaginate.data.findIndex(photo => photo.id === payload.photoId)
        let photo = state.bulkPaginate.data[photoIndex];
        let tags = Object.assign({}, photo.tags ?? {});

        photo.tags = {
            ...tags,
            [payload.category]: {
                ...tags[payload.category],
                [payload.tag]: payload.quantity
            }
        };

        state.bulkPaginate.data.splice(photoIndex, 1, photo);
    },

    /**
     * Adds a custom tag to a single photo
     */
    addCustomTagToPhoto (state, payload)
    {
        const photoIndex = state.bulkPaginate.data.findIndex(photo => photo.id === payload.photoId)
        let photo = state.bulkPaginate.data[photoIndex];
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

        state.bulkPaginate.data.splice(photoIndex, 1, photo);
    },

    /**
     * Removes a tag from a photo
     * If the category is empty, delete the category
     */
    removeTagFromPhoto (state, payload)
    {
        const photoIndex = state.bulkPaginate.data.findIndex(photo => photo.id === payload.photoId);
        let photo = state.bulkPaginate.data[photoIndex];
        let tags = Object.assign({}, photo.tags ?? {});

        delete tags[payload.category][payload.tag];

        if (Object.keys(tags[payload.category]).length === 0)
        {
            delete tags[payload.category];
        }

        photo.tags = tags;

        state.bulkPaginate.data.splice(photoIndex, 1, photo);
    },

    /**
     * Removes a custom tag from a photo
     */
    removeCustomTagFromPhoto (state, payload)
    {
        const photoIndex = state.bulkPaginate.data.findIndex(photo => photo.id === payload.photoId)
        let photo = state.bulkPaginate.data[photoIndex];
        let tags = photo.custom_tags ?? [];

        tags = tags.filter(tag => tag !== payload.customTag);

        photo.custom_tags = tags;

        state.bulkPaginate.data.splice(photoIndex, 1, photo);
    },

    /**
     * Sets the photo to show the details of
     */
    setPhotoToShowDetails (state, photoId)
    {
        state.showDetailsPhotoId = photoId;
    },

    /**
     * Change the selected value of a photo
     *
     * BulkTag.vue
     */
    togglePhotoSelected (state, payload)
    {
        let photos = [...state.bulkPaginate.data];
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
        state.bulkPaginate.data = photos;
    }
}
