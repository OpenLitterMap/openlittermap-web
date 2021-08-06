export const init = {
    category: 'smoking', // currently selected category.
    hasAddedNewTag: false, // Has the admin added a new tag yet? If FALSE, disable "Update With New Tags button"
    presence: null, // true = remaining
    tag: 'butts', // currently selected item
    loading: false,
    photos: {}, // paginated photos object
    tags: {}, // added tags go here -> { photoId: { smoking: { butts: 1, lighters: 2 }, alcohol: { beer_cans: 3 } }, ... };
    submitting: false,
    recentTags: {}
};
