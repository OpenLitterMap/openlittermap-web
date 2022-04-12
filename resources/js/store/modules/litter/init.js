export const init = {
    category: 'ordnance', // currently selected category.
    hasAddedNewTag: false, // Has the admin added a new tag yet? If FALSE, disable "Update With New Tags button"
    pickedUp: null, // true = picked up
    tag: 'small_arms', // currently selected item
    customTag: '', // currently selected custom tag
    loading: false,
    photos: {}, // paginated photos object
    tags: {}, // added tags go here -> { photoId: { smoking: { butts: 1, lighters: 2 }, alcohol: { beer_cans: 3 } }, ... };
    customTags: {},
    customTagsError: '',
    submitting: false,
    recentTags: {},
    recentCustomTags: []
};
